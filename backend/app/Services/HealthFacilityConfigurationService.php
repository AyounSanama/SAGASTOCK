<?php

namespace App\Services;

use App\Models\CatalogReference;
use App\Models\HealthFacility;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AM-162 (lot c1) — Configuration d'une FOSA à partir de la configuration
 * validée de son projet : niveau de soins, catégorie, populations,
 * pathologies, paramètres d'approvisionnement (DEC-08), site principal (DEC-05).
 */
class HealthFacilityConfigurationService
{
    public function __construct(
        private readonly ProjectMedicalConfigurationService $projectConfiguration,
        private readonly StandardListGenerationService $standardLists,
    ) {}

    /**
     * Choix proposés à l'Admin Projet : uniquement ce que la Coordination a validé.
     *
     * @return array{care_levels: Collection, target_populations: Collection, pathologies: Collection, facility_categories: Collection, supply_defaults: array}
     */
    public function options(Project $project): array
    {
        $configuration = $this->projectConfiguration->get($project);
        $levelIds = $this->standardLists->expandDescendants(collect($configuration['care_levels'])->pluck('id'));

        return [
            'care_levels' => CatalogReference::whereIn('id', $levelIds)->where('is_active', true)->orderBy('depth')->orderBy('name')->get(['id', 'parent_id', 'depth', 'code', 'name']),
            'target_populations' => collect($configuration['target_populations'])->values(),
            'pathologies' => collect($configuration['pathologies'])->values(),
            'facility_categories' => CatalogReference::where('reference_type', 'facility_category')->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $project->organization_id))
                ->orderBy('name')->get(['id', 'code', 'name']),
            'supply_defaults' => [
                'order_period_months' => $project->order_period_months,
                'delivery_lead_time_months' => $project->delivery_lead_time_months,
                'safety_stock_months' => $project->safety_stock_months !== null ? (float) $project->safety_stock_months : null,
                'inventory_date' => $project->inventory_date?->format('Y-m-d'),
                'order_submission_date' => $project->order_submission_date?->format('Y-m-d'),
                'order_receipt_date' => $project->order_receipt_date?->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Applique la configuration V1 d'une FOSA (création ou modification).
     *
     * @param  array<string, mixed>  $configuration
     */
    public function apply(HealthFacility $facility, array $configuration, ?Project $project, ?User $actor, bool $creating): void
    {
        DB::transaction(function () use ($facility, $configuration, $project, $actor, $creating): void {
            if (array_key_exists('target_population_ids', $configuration)) {
                $facility->targetPopulations()->sync($configuration['target_population_ids'] ?? []);
            }
            if (array_key_exists('pathology_ids', $configuration)) {
                $facility->pathologies()->sync($configuration['pathology_ids'] ?? []);
            }

            $supply = collect(HealthFacility::SUPPLY_FIELDS)
                ->filter(fn (string $field) => array_key_exists($field, $configuration))
                ->mapWithKeys(fn (string $field) => [$field => $configuration[$field]])
                ->all();
            // DEC-08 : préremplissage depuis le projet à la création uniquement.
            if ($creating && $project) {
                foreach (HealthFacility::SUPPLY_FIELDS as $field) {
                    if (($supply[$field] ?? null) === null && $project->{$field} !== null) {
                        $supply[$field] = $project->{$field};
                    }
                }
            }
            if ($supply !== []) {
                $facility->fill($supply);
            }
            $supplyChanged = $creating || $facility->isDirty(HealthFacility::SUPPLY_FIELDS);
            $facility->save();
            if ($supplyChanged && collect(HealthFacility::SUPPLY_FIELDS)->contains(fn ($field) => $facility->{$field} !== null)) {
                $this->recordSupplySettings($facility, $creating ? 'creation' : 'update', $actor);
            }

            $this->ensurePrimarySite($facility);
        });
    }

    /**
     * Action explicite « Appliquer à toutes les FOSA » (DEC-08) : jamais automatique.
     */
    public function applyProjectSupplyToFacilities(Project $project, ?User $actor): int
    {
        $count = 0;
        $project->healthFacilities()->get()->each(function (HealthFacility $facility) use ($project, $actor, &$count): void {
            $facility->fill([
                'order_period_months' => $project->order_period_months,
                'delivery_lead_time_months' => $project->delivery_lead_time_months,
                'safety_stock_months' => $project->safety_stock_months,
                // Niveau 2 : dates du projet (inventaire, soumission, réception).
                'inventory_date' => $project->inventory_date,
                'order_submission_date' => $project->order_submission_date,
                'order_receipt_date' => $project->order_receipt_date,
            ]);
            if ($facility->isDirty(HealthFacility::SUPPLY_FIELDS)) {
                $facility->save();
                $this->recordSupplySettings($facility, 'project_apply', $actor);
                $count++;
            }
        });

        return $count;
    }

    /** DEC-05 : un site principal par FOSA, invisible pour l'utilisateur. */
    public function ensurePrimarySite(HealthFacility $facility): Site
    {
        $existing = $facility->sites()->where('is_primary', true)->first();
        if ($existing) {
            return $existing;
        }
        // Une FOSA qui a déjà un seul site : il devient le site principal.
        $sites = $facility->sites()->get();
        if ($sites->count() === 1) {
            $sites->first()->update(['is_primary' => true]);

            return $sites->first();
        }

        return $facility->sites()->create([
            'organization_id' => $facility->organization_id,
            'code' => Str::limit(Str::upper(Str::slug($facility->code, '-')), 40, '').'-PRINCIPAL',
            'name' => 'Pharmacie principale',
            'site_type' => 'stock_and_dispensing',
            'is_active' => true,
            'is_primary' => true,
        ]);
    }

    private function recordSupplySettings(HealthFacility $facility, string $source, ?User $actor): void
    {
        DB::table('health_facility_supply_settings_history')->insert([
            'id' => (string) Str::uuid(),
            'health_facility_id' => $facility->id,
            ...collect(HealthFacility::SUPPLY_FIELDS)->mapWithKeys(fn (string $field) => [$field => $facility->getRawOriginal($field)])->all(),
            'source' => $source,
            'changed_by' => $actor?->id,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
