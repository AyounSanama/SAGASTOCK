<?php

namespace App\Services;

use App\Http\Requests\SaveHealthFacilityRequest;
use App\Models\HealthFacility;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductStandardMapping;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AM-162 (lot c1) — Déclaration et modification des FOSA, partagées par le Web
 * et l'API (même Form Request, mêmes règles).
 */
class HealthFacilityManagementService
{
    public function __construct(
        private readonly HealthFacilityConfigurationService $configuration,
        private readonly StandardListGenerationService $standardLists,
        private readonly UserScopeService $scopes,
    ) {}

    /** L'Admin Projet déclare une FOSA : elle reste « en attente » jusqu'à la validation de la Coordination. */
    public function declare(SaveHealthFacilityRequest $request, Organization $organization): HealthFacility
    {
        $data = $request->facilityData();
        $projectIds = $data['project_ids'] ?? [];
        unset($data['project_ids']);
        if ($request->isProjectAdmin()) {
            $data['validation_status'] = HealthFacility::STATUS_PENDING;
            $data['declared_by'] = $request->user()->id;
        } else {
            $data['validation_status'] = HealthFacility::STATUS_VALIDATED;
            $data['validated_at'] = now();
        }

        return DB::transaction(function () use ($organization, $data, $projectIds, $request) {
            $facility = $organization->healthFacilities()->create($data);
            $facility->projects()->sync($projectIds);
            $this->configuration->apply($facility, $request->configurationData(), $request->project(), $request->user(), creating: true);

            return $facility;
        });
    }

    public function update(SaveHealthFacilityRequest $request, HealthFacility $facility): void
    {
        $data = $request->facilityData();
        $projectIds = $data['project_ids'] ?? null;
        unset($data['project_ids']);
        // Une FOSA refusée et corrigée repasse « en attente » (décision E2).
        if ($request->isProjectAdmin() && $facility->validation_status === HealthFacility::STATUS_REFUSED) {
            $data['validation_status'] = HealthFacility::STATUS_PENDING;
        }

        DB::transaction(function () use ($facility, $data, $projectIds, $request): void {
            $facility->update($data);
            if ($projectIds !== null) {
                $facility->projects()->sync($projectIds);
            }
            $this->configuration->apply($facility, $request->configurationData(), $request->project(), $request->user(), creating: false);
        });
    }

    /** Projet de référence pour les choix d'une FOSA (projet unique de l'Admin Projet). */
    public function projectFor(User $user, Organization $organization, ?string $projectId = null): Project
    {
        $ids = app(GovernanceService::class)->roleCode($user) === GovernanceService::PROJECT_ADMIN
            ? $this->scopes->directProjectIds($user)->unique()->values()
            : $this->scopes->projectIds($user);
        $id = $projectId ?? ($ids->count() === 1 ? $ids->first() : null);
        abort_unless($id && $ids->contains($id), 404);

        return Project::whereKey($id)->where('organization_id', $organization->id)->firstOrFail();
    }

    /**
     * Liste Standard générée pour la FOSA : critères de la FOSA (niveau,
     * catégorie, populations, pathologies) appliqués aux règles de la Liste
     * Standard ; limitée aux produits publiés par la Coordination pour le projet.
     *
     * Niveau 6 : un produit ajouté à la main par la Coordination (sans
     * correspondance de catalogue) vaut pour toutes les FOSA du projet ; les
     * articles décochés pour la FOSA sont retirés ($applyExclusions).
     */
    public function standardList(HealthFacility $facility, bool $applyExclusions = true): Collection
    {
        $project = $facility->projects()->first();
        if (! $project || ! $facility->care_level_id) {
            return collect();
        }
        $products = $this->standardLists->generate($project, [
            'care_level_id' => $facility->care_level_id,
            'facility_category_id' => $facility->facility_category_id,
            'target_population_ids' => $facility->targetPopulations()->pluck('catalog_references.id')->all(),
            'pathology_ids' => $facility->pathologies()->pluck('catalog_references.id')->all(),
        ]);
        $published = $this->standardLists->publishedProductIds($project);
        if ($published !== null) {
            $mapped = ProductStandardMapping::where('organization_id', $project->organization_id)
                ->whereIn('product_id', $published)->distinct()->pluck('product_id');
            $manual = collect($published)->diff($mapped)->diff($products->pluck('id'));
            $products = $products->whereIn('id', $published)
                ->concat(Product::whereIn('id', $manual)->where('is_active', true)->with(['baseUnit:id,name', 'dosageForm:id,name', 'category:id,name'])->get())
                ->sortBy('name')->values();
        }
        if ($applyExclusions) {
            $excluded = DB::table('health_facility_product_exclusions')->where('health_facility_id', $facility->id)->pluck('product_id');
            $products = $products->whereNotIn('id', $excluded)->values();
        }

        return $products;
    }

    /** Fiche FOSA renvoyée par l'API. */
    public function present(HealthFacility $facility): HealthFacility
    {
        return $facility->load([
            'organization:id,code,name', 'mission.country', 'projects:id,organization_id,mission_id,code,name',
            'facilityCategory:id,code,name', 'careLevel:id,code,name,depth',
            'targetPopulations:id,code,name', 'pathologies:id,code,name',
        ]);
    }
}
