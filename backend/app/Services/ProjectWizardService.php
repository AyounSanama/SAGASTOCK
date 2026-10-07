<?php

namespace App\Services;

use App\Models\CatalogReference;
use App\Models\Mission;
use App\Models\Product;
use App\Models\ProductStandardMapping;
use App\Models\Project;
use App\Models\StandardList;
use App\Models\StandardListVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Niveau 2 — Assistant « Créer un projet / programme » (Coordination).
 *
 * Étapes 1-2 : identité du projet, enregistrée en brouillon dès la fin de
 * l'étape 2. Étape 3 : configuration médicale et Liste Standard (produits
 * retenus ou non). Étape 4 : paramètres d'approvisionnement, puis passage
 * « Actif » et validation de la Liste Standard.
 *
 * Réutilise ProjectProvisioningService, ProjectMedicalConfigurationService et
 * StandardListGenerationService : mêmes règles que le reste de l'application.
 */
class ProjectWizardService
{
    public const STEPS = [
        'identity' => 'Informations générales',
        'donor' => 'Bailleur et projet',
        'standard-list' => 'Configuration de la Liste Standard',
        'supply' => 'Paramètres d’approvisionnement',
    ];

    public function __construct(
        private ProjectProvisioningService $provisioning,
        private ProjectMedicalConfigurationService $medical,
        private StandardListGenerationService $generator,
        private UserScopeService $scopes,
    ) {}

    /** Coordinations (missions) dans lesquelles l'acteur peut créer un projet. */
    public function missions(User $actor): Collection
    {
        return Mission::whereIn('id', $this->scopes->coordinationMissionIds($actor))
            ->with(['country:id,name', 'organization:id,name'])->orderBy('name')->get();
    }

    /** Étapes 1-2 : création (brouillon) ou modification de l'identité. */
    public function saveIdentity(?Project $project, array $data, int $actorId): Project
    {
        $mission = Mission::findOrFail($data['mission_id']);
        $national = $data['type'] === 'national_program';
        $attributes = [
            'mission_id' => $mission->id,
            'type' => $data['type'],
            'implementing_partner' => $data['implementing_partner'],
            'code' => $data['code'],
            'name' => $data['name'],
            'donor_reference_code' => $national ? null : $data['code'],
            'moh_program_code' => $national ? $data['code'] : null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'donor_ids' => array_values(array_filter([$data['donor_id'] ?? null])),
        ];

        if (! $project) {
            return $this->provisioning->create($mission->organization, [
                ...$attributes,
                'status' => 'draft',
                'is_active' => false,
            ], $actorId)['project'];
        }

        return $this->provisioning->update($project, [
            ...$attributes,
            'status' => $project->status,
            'is_active' => $project->is_active,
            // Les programmes rattachés avant la fusion projet / programme sont conservés.
            'program_ids' => $project->programs()->pluck('programs.id')->all(),
        ], $actorId);
    }

    /**
     * Étape 3 — Choix et liste affichés : depuis la saisie en cours ($input)
     * ou, à défaut, depuis la configuration et la dernière version enregistrées.
     *
     * @return array{care_level_ids: list<string>, target_population_ids: list<string>, pathology_ids: list<string>, rows: Collection, retained_count: int, total: int}
     */
    public function standardListState(Project $project, ?array $input = null): array
    {
        $saved = $this->medical->get($project);
        $levels = $input !== null ? $this->ids($input['care_level_ids'] ?? []) : collect($saved['care_levels'])->pluck('id')->values();
        $populations = $input !== null ? $this->ids($input['target_population_ids'] ?? []) : collect($saved['target_populations'])->pluck('id')->values();
        $pathologies = $input !== null ? $this->ids($input['pathology_ids'] ?? []) : collect($saved['pathologies'])->pluck('id')->values();

        $generated = $this->generated($project, $levels, $populations, $pathologies);
        $version = $this->latestVersion($project);
        $versionIds = $version?->products()->pluck('products.id') ?? collect();

        if ($input !== null) {
            $excluded = $this->ids($input['listed'] ?? [])->diff($this->ids($input['retained'] ?? []));
            $added = $this->ids($input['added'] ?? [])->diff($excluded);
        } else {
            $excluded = $version ? $generated->pluck('id')->diff($versionIds) : collect();
            $added = $versionIds->diff($generated->pluck('id'));
        }

        $addedProducts = $this->organizationProducts($project, $added->diff($generated->pluck('id')));
        $all = $generated->concat($addedProducts);
        $pathologyNames = $this->pathologyNames($project, $all->pluck('id'), $pathologies);
        $rows = $all->map(fn (Product $product) => [
            'product' => $product,
            'retained' => ! $excluded->contains($product->id),
            'added' => $addedProducts->contains('id', $product->id),
            'pathology' => $pathologyNames->get($product->id),
        ])->values();

        return [
            'care_level_ids' => $levels->all(),
            'target_population_ids' => $populations->all(),
            'pathology_ids' => $pathologies->all(),
            'rows' => $rows,
            'retained_count' => $rows->where('retained', true)->count(),
            'total' => $rows->count(),
        ];
    }

    /** Étape 3 — Enregistre la configuration médicale et la Liste Standard (version brouillon). */
    public function saveStandardList(Project $project, array $data, bool $draft): array
    {
        $levels = $this->ids($data['care_level_ids'] ?? []);
        $populations = $this->ids($data['target_population_ids'] ?? []);
        $pathologies = $this->ids($data['pathology_ids'] ?? []);
        if ($levels->isEmpty() || $populations->isEmpty()) {
            if ($draft) {
                return ['saved' => false, 'retained' => 0];
            }
            throw ValidationException::withMessages(['care_level_ids' => 'Sélectionnez au moins un niveau de soins et une population cible.']);
        }

        $generatedIds = $this->generated($project, $levels, $populations, $pathologies)->pluck('id');
        $added = $this->organizationProducts($project, $this->ids($data['added'] ?? [])->diff($generatedIds))->pluck('id');
        $retained = $this->ids($data['retained'] ?? [])->intersect($generatedIds->concat($added))->values();
        if ($retained->isEmpty() && $generatedIds->concat($added)->isNotEmpty() && ! $draft) {
            throw ValidationException::withMessages(['retained' => 'Retenez au moins un produit dans la Liste Standard.']);
        }

        return DB::transaction(function () use ($project, $levels, $populations, $pathologies, $retained): array {
            $this->medical->save($project, [
                'care_level_ids' => $levels->all(),
                'target_population_ids' => $populations->all(),
                'pathologies' => $this->pathologyLinks($project, $pathologies, $populations),
            ]);

            if ($retained->isNotEmpty()) {
                $list = StandardList::firstOrCreate(
                    ['organization_id' => $project->organization_id, 'scope_type' => 'project', 'scope_id' => $project->id],
                    ['code' => $project->code.'_STD', 'name' => 'Liste standard · '.$project->name, 'is_active' => true],
                );
                $context = [
                    'care_level_id' => $levels->first(),
                    'facility_category_id' => null,
                    'target_population_ids' => $populations->all(),
                    'pathology_ids' => $pathologies->all(),
                    'laboratory_exam_ids' => [],
                ];
                $version = $list->versions()->where('status', 'draft')->orderByDesc('version_number')->first();
                if ($version) {
                    $version->update($context);
                } else {
                    $version = $list->versions()->create([
                        ...$context,
                        'version_number' => ((int) $list->versions()->max('version_number')) + 1,
                        'status' => 'draft',
                    ]);
                }
                $version->products()->sync($retained->all());
            }

            return ['saved' => true, 'retained' => $retained->count()];
        });
    }

    /** Étape 4 — Paramètres d'approvisionnement ; $activate : projet « Actif » et Liste Standard validée. */
    public function saveSupply(Project $project, array $data, bool $activate, int $actorId): Project
    {
        return DB::transaction(function () use ($project, $data, $activate, $actorId): Project {
            $project->fill(collect($data)->only(ProjectProvisioningService::SUPPLY_FIELDS)->all());
            if ($activate) {
                $project->status = 'active';
            }
            $supplyChanged = $project->isDirty(ProjectProvisioningService::SUPPLY_FIELDS);
            $project->save();
            if ($supplyChanged) {
                $this->provisioning->recordSupplySettings($project, $actorId);
            }

            if ($activate) {
                $version = $this->latestVersion($project);
                if ($version?->status === 'draft' && $version->products()->exists()) {
                    $version->standardList->versions()->where('status', 'published')->update(['status' => 'superseded']);
                    $version->update(['status' => 'published', 'published_by' => $actorId, 'published_at' => now()]);
                }
            }

            return $project->refresh();
        });
    }

    /** Première étape à compléter, pour reprendre un brouillon. */
    public function resumeStep(Project $project): string
    {
        if (! $this->medical->get($project)['is_configured']) {
            return 'standard-list';
        }

        return 'supply';
    }

    /** Récapitulatif affiché à droite de chaque étape. */
    public function summary(?Project $project): array
    {
        if (! $project) {
            return [];
        }
        $project->loadMissing(['mission.country:id,name', 'organization:id,name', 'donors:id,code,name']);
        $configuration = $this->medical->get($project);
        $version = $this->latestVersion($project);

        return [
            'type' => $project->type_label,
            'mission' => $project->mission?->country?->name,
            'organization' => $project->implementing_partner ?: $project->organization?->name,
            'donor' => $project->donors->first()?->name ?? ($project->type === 'national_program' ? Project::NATIONAL_PROGRAM_DEFAULT_DONOR : null),
            'code' => $project->code,
            'name' => $project->name,
            'care_levels' => collect($configuration['care_levels'])->pluck('name')->all(),
            'populations' => count($configuration['target_populations']),
            'pathologies' => count($configuration['pathologies']),
            'retained' => $version?->products()->count(),
            'order_period_months' => $project->order_period_months,
            'delivery_lead_time_months' => $project->delivery_lead_time_months,
            'safety_stock_months' => $project->safety_stock_months,
        ];
    }

    /** Bailleurs proposés à l'étape 2 (organisations des coordinations de l'acteur). */
    public function donors(Collection $missions): Collection
    {
        return \App\Models\Donor::whereIn('organization_id', $missions->pluck('organization_id')->unique())
            ->where('is_active', true)->orderBy('name')->get(['id', 'organization_id', 'code', 'name']);
    }

    /** « + Ajouter un bailleur » : ajouté au référentiel de l'organisation de la mission. */
    public function createDonor(Mission $mission, array $data): \App\Models\Donor
    {
        return $mission->organization->donors()->create([...$data, 'is_active' => true]);
    }

    /**
     * Étape 3 — Valeurs sélectionnables : niveaux de soins (arbre à plat),
     * populations cibles et pathologies (référentiel global + organisation).
     *
     * Niveau 6 : « Pathologies et activités » regroupe pathologies et examens
     * de laboratoire ; chacune porte `suggested` selon le couple niveau de
     * soins × population choisi (`suggestions` : faux si le catalogue n'a
     * encore aucune correspondance pour ces choix, tout est alors proposé).
     *
     * @param  array{care_level_ids?: list<string>, target_population_ids?: list<string>, pathology_ids?: list<string>}  $state
     */
    public function standardListOptions(Project $project, array $state = []): array
    {
        $references = fn (string $type) => CatalogReference::where('reference_type', $type)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $project->organization_id))
            ->orderBy('name')->get(['id', 'name'])->map(fn (CatalogReference $reference) => $reference->only(['id', 'name']))->values();

        return [
            'care_levels' => collect($this->flatten(app(CareLevelHierarchyService::class)->tree($project->organization))),
            'target_populations' => $references('target_population'),
            ...$this->activityOptions($project, $state),
        ];
    }

    /** @return array{pathologies: Collection, suggestions: bool} */
    private function activityOptions(Project $project, array $state): array
    {
        $suggested = $this->generator->suggestedActivityIds(
            $project->organization_id,
            $this->ids($state['care_level_ids'] ?? []),
            $this->ids($state['target_population_ids'] ?? []),
        );
        $selected = $this->ids($state['pathology_ids'] ?? []);
        $activities = CatalogReference::whereIn('reference_type', StandardListGenerationService::ACTIVITY_TYPES)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $project->organization_id))
            ->orderBy('name')->get(['id', 'name', 'reference_type'])
            ->map(fn (CatalogReference $reference) => [
                'id' => $reference->id,
                'name' => $reference->name,
                'type' => $reference->reference_type,
                'suggested' => $suggested->isEmpty() || $suggested->contains($reference->id) || $selected->contains($reference->id),
            ])
            // Proposées d'abord, puis les autres.
            ->sortBy(fn (array $activity) => ($activity['suggested'] ? '0' : '1').mb_strtolower($activity['name']))->values();

        return ['pathologies' => $activities, 'suggestions' => $suggested->isNotEmpty()];
    }

    /**
     * Niveau 6 — Produits ajoutés pendant l'étape 3 (« + Ajouter un produit »,
     * import Excel) : la configuration en cours est enregistrée en brouillon
     * avec ces produits retenus.
     *
     * @param  list<string>  $productIds
     */
    public function addProducts(Project $project, array $input, array $productIds): array
    {
        $state = $this->standardListState($project, [...$input, 'added' => [...($input['added'] ?? []), ...$productIds]]);
        $rows = $state['rows'];
        $retained = $this->ids($input['retained'] ?? [])->concat($productIds)
            ->concat($rows->reject(fn (array $row) => in_array($row['product']->id, $input['listed'] ?? [], true))->pluck('product.id'))
            ->unique()->values();

        return $this->saveStandardList($project, [
            'care_level_ids' => $state['care_level_ids'],
            'target_population_ids' => $state['target_population_ids'],
            'pathology_ids' => $state['pathology_ids'],
            'retained' => $retained->all(),
            'added' => $rows->where('added', true)->pluck('product.id')->concat($productIds)->unique()->values()->all(),
        ], draft: true);
    }

    /** Produits du catalogue que la Coordination peut ajouter hors génération. */
    public function addableProducts(Project $project, Collection $excludeIds): Collection
    {
        return Product::where('organization_id', $project->organization_id)->where('is_active', true)
            ->whereNotIn('id', $excludeIds)->orderBy('name')->get(['id', 'code', 'name', 'strength']);
    }

    private function generated(Project $project, Collection $levels, Collection $populations, Collection $pathologies): Collection
    {
        if ($levels->isEmpty() || $populations->isEmpty()) {
            return collect();
        }

        return $this->generator->generateFor($project, $levels, [
            'target_population_ids' => $populations->all(),
            'pathology_ids' => $pathologies->all(),
        ]);
    }

    private function latestVersion(Project $project): ?StandardListVersion
    {
        return StandardListVersion::whereHas('standardList', fn ($query) => $query->where('scope_type', 'project')->where('scope_id', $project->id))
            ->whereIn('status', ['draft', 'published'])
            ->with('standardList')->orderByDesc('version_number')->first();
    }

    private function organizationProducts(Project $project, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::where('organization_id', $project->organization_id)->where('is_active', true)
            ->whereIn('id', $ids)->with(['baseUnit:id,name', 'dosageForm:id,name', 'category:id,name'])->orderBy('name')->get();
    }

    /** Pathologie affichée par produit : la première pathologie retenue qui le propose. */
    private function pathologyNames(Project $project, Collection $productIds, Collection $pathologyIds): Collection
    {
        if ($productIds->isEmpty() || $pathologyIds->isEmpty()) {
            return collect();
        }
        $names = CatalogReference::whereIn('id', $pathologyIds)->pluck('name', 'id');

        return ProductStandardMapping::where('organization_id', $project->organization_id)
            ->whereIn('product_id', $productIds)->whereIn('pathology_id', $pathologyIds)
            ->get(['product_id', 'pathology_id'])
            ->groupBy('product_id')
            ->map(fn (Collection $mappings) => $names->get($mappings->first()->pathology_id));
    }

    /**
     * Pathologies × populations : l'assistant présente les pathologies à plat ;
     * une association déjà affinée (configuration médicale) est conservée.
     */
    private function pathologyLinks(Project $project, Collection $pathologies, Collection $populations): array
    {
        $existing = DB::table('project_pathology_populations')->where('project_id', $project->id)->get()->groupBy('pathology_id');

        return $pathologies->map(function (string $pathologyId) use ($existing, $populations) {
            $kept = collect($existing->get($pathologyId, []))->pluck('target_population_id')->intersect($populations)->values();

            return ['pathology_id' => $pathologyId, 'target_population_ids' => ($kept->isNotEmpty() ? $kept : $populations)->all()];
        })->values()->all();
    }

    /** Arbre des niveaux de soins à plat, ordre de l'arbre conservé. */
    private function flatten(iterable $nodes, array &$result = []): array
    {
        foreach ($nodes as $node) {
            $result[] = ['id' => $node['id'], 'name' => $node['name'], 'depth' => $node['depth'] ?? 1, 'level_label' => $node['level_label'] ?? ''];
            $this->flatten($node['children'] ?? [], $result);
        }

        return $result;
    }

    private function ids(mixed $values): Collection
    {
        return collect(is_array($values) ? $values : [])->filter(fn ($value) => is_string($value) && $value !== '')->unique()->values();
    }
}
