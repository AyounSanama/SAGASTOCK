<?php

namespace App\Services;

use App\Models\CatalogReference;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AM-112 — Configuration médicale d'un projet :
 * Projet → Niveaux de soins → Populations cibles → Pathologies par population.
 *
 * Écriture réservée à la Coordination ; lecture pour tout le périmètre du
 * projet. Web et API passent par ce service.
 */
class ProjectMedicalConfigurationService
{
    public const REFERENCE_TYPES = ['target_population', 'pathology', 'laboratory_exam'];

    /** Configuration actuelle, prête pour l'affichage Web, l'API et le mobile. */
    public function get(Project $project): array
    {
        $careLevels = $this->references($project, 'care_level')
            ->whereIn('id', DB::table('project_care_levels')->where('project_id', $project->id)->pluck('care_level_id'));
        $populations = $this->references($project, 'target_population')
            ->whereIn('id', DB::table('project_target_populations')->where('project_id', $project->id)->pluck('target_population_id'));
        $links = DB::table('project_pathology_populations')->where('project_id', $project->id)->get();
        $pathologies = $this->references($project, StandardListGenerationService::ACTIVITY_TYPES)->whereIn('id', $links->pluck('pathology_id')->unique());

        return [
            'care_levels' => $careLevels->values(),
            'target_populations' => $populations->values(),
            'pathologies' => $pathologies->map(fn (CatalogReference $pathology) => [
                ...$pathology->only(['id', 'code', 'name', 'organization_id', 'reference_type']),
                'target_population_ids' => $links->where('pathology_id', $pathology->id)->pluck('target_population_id')->values(),
            ])->values(),
            'is_configured' => $careLevels->isNotEmpty() && $populations->isNotEmpty(),
        ];
    }

    /** Options sélectionnables (référentiel global + organisation, actifs). */
    public function options(Project $project): array
    {
        return [
            'care_level_tree' => app(CareLevelHierarchyService::class)->tree($project->organization),
            'target_populations' => $this->references($project, 'target_population')->where('is_active', true)->values(),
            'pathologies' => $this->references($project, StandardListGenerationService::ACTIVITY_TYPES)->where('is_active', true)->values(),
        ];
    }

    /**
     * @param  array{care_level_ids: list<string>, target_population_ids: list<string>, pathologies?: list<array{pathology_id: string, target_population_ids: list<string>}>}  $data
     *
     * @throws ValidationException
     */
    public function save(Project $project, array $data): array
    {
        $careLevelIds = collect($data['care_level_ids'])->unique()->values();
        $populationIds = collect($data['target_population_ids'])->unique()->values();
        $this->assertReferences($project, 'care_level', $careLevelIds, 'care_level_ids');
        $this->assertReferences($project, 'target_population', $populationIds, 'target_population_ids');

        $links = collect($data['pathologies'] ?? [])->flatMap(function (array $row, int $index) use ($populationIds) {
            $targets = collect($row['target_population_ids'] ?? [])->unique();
            if ($targets->isEmpty()) {
                throw ValidationException::withMessages(["pathologies.$index.target_population_ids" => 'Associez chaque pathologie à au moins une population cible.']);
            }
            if ($targets->diff($populationIds)->isNotEmpty()) {
                throw ValidationException::withMessages(["pathologies.$index.target_population_ids" => 'Une pathologie ne peut être associée qu’aux populations cibles retenues pour le projet.']);
            }

            return $targets->map(fn (string $populationId) => ['pathology_id' => $row['pathology_id'], 'target_population_id' => $populationId]);
        })->unique(fn (array $link) => $link['pathology_id'].'|'.$link['target_population_id'])->values();
        $this->assertReferences($project, StandardListGenerationService::ACTIVITY_TYPES, $links->pluck('pathology_id')->unique()->values(), 'pathologies');

        DB::transaction(function () use ($project, $careLevelIds, $populationIds, $links): void {
            $now = now();
            DB::table('project_pathology_populations')->where('project_id', $project->id)->delete();
            DB::table('project_target_populations')->where('project_id', $project->id)->delete();
            DB::table('project_care_levels')->where('project_id', $project->id)->delete();
            DB::table('project_care_levels')->insert($careLevelIds->map(fn ($id) => ['project_id' => $project->id, 'care_level_id' => $id, 'created_at' => $now, 'updated_at' => $now])->all());
            DB::table('project_target_populations')->insert($populationIds->map(fn ($id) => ['project_id' => $project->id, 'target_population_id' => $id, 'created_at' => $now, 'updated_at' => $now])->all());
            DB::table('project_pathology_populations')->insert($links->map(fn ($link) => [...$link, 'project_id' => $project->id, 'created_at' => $now, 'updated_at' => $now])->all());
        });

        return $this->get($project);
    }

    /** Ajout d'une population cible ou d'une pathologie au référentiel de l'organisation. */
    public function createReference(Project|\App\Models\Organization $scope, string $type, array $input): CatalogReference
    {
        abort_unless(in_array($type, self::REFERENCE_TYPES, true), 404);
        $organization = $scope instanceof Project ? $scope->organization : $scope;

        return $organization->catalogReferences()->create([
            'reference_type' => $type,
            'code' => $input['code'],
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'is_active' => true,
        ]);
    }

    /** @param  string|list<string>  $type */
    private function references(Project $project, string|array $type): Collection
    {
        return CatalogReference::whereIn('reference_type', (array) $type)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $project->organization_id))
            ->orderBy('depth')->orderBy('name')
            ->get(['id', 'organization_id', 'reference_type', 'parent_id', 'depth', 'code', 'name', 'is_active']);
    }

    /** @param  string|list<string>  $type */
    private function assertReferences(Project $project, string|array $type, Collection $ids, string $field): void
    {
        $valid = CatalogReference::whereIn('id', $ids)->whereIn('reference_type', (array) $type)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $project->organization_id))
            ->count();
        if ($valid !== $ids->count()) {
            throw ValidationException::withMessages([$field => 'Une valeur sélectionnée n’existe pas ou n’est pas autorisée pour cette organisation.']);
        }
    }
}
