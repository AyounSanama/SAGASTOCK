<?php

namespace App\Services;

use App\Models\CatalogReference;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * AM-111 — Hiérarchie des niveaux de soins : Niveau → Catégorie → Programme.
 *
 * Seul le type `care_level` est hiérarchique. Les règles sont appliquées
 * côté serveur pour le Web et l'API.
 */
class CareLevelHierarchyService
{
    public const TYPE = 'care_level';

    public const MAX_DEPTH = 3;

    public const DEPTH_LABELS = [1 => 'Niveau de soins', 2 => 'Catégorie', 3 => 'Programme'];

    /**
     * Complète les données validées d'une référence avec `parent_id` et `depth`.
     *
     * @throws ValidationException
     */
    public function apply(Organization $organization, array $data, ?string $parentId, ?CatalogReference $reference = null): array
    {
        $type = $data['reference_type'] ?? $reference?->reference_type;
        if ($type !== self::TYPE || ! $parentId) {
            if ($parentId && $type !== self::TYPE) {
                throw ValidationException::withMessages(['parent_id' => 'Seuls les niveaux de soins peuvent avoir un parent.']);
            }
            $this->guardMoveWithChildren($reference, null);

            return [...$data, 'parent_id' => null, 'depth' => 1];
        }

        $parent = CatalogReference::whereKey($parentId)
            ->where('reference_type', self::TYPE)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))
            ->first();
        if (! $parent) {
            throw ValidationException::withMessages(['parent_id' => 'Le parent doit être un niveau de soins de cette organisation.']);
        }
        if ($reference && $this->ancestorIds($parent)->push($parent->id)->contains($reference->id)) {
            throw ValidationException::withMessages(['parent_id' => 'Un élément ne peut pas être rattaché à lui-même ou à l’un de ses descendants.']);
        }
        $depth = $parent->depth + 1;
        if ($depth > self::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => 'La hiérarchie est limitée à trois niveaux : Niveau → Catégorie → Programme.']);
        }
        $this->guardMoveWithChildren($reference, $parent->id);

        return [...$data, 'parent_id' => $parent->id, 'depth' => $depth];
    }

    /**
     * Création d'un nœud par la Coordination (Web et API partagent ce chemin).
     *
     * @param  array{code: string, name: string, description?: ?string, parent_id?: ?string}  $input
     */
    public function createNode(Organization $organization, array $input): CatalogReference
    {
        $data = $this->apply($organization, [
            'reference_type' => self::TYPE,
            'code' => $input['code'],
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'is_active' => true,
        ], $input['parent_id'] ?? null);

        return $organization->catalogReferences()->create($data);
    }

    /** Règles de saisie d'un nœud, identiques Web / API. */
    public function rules(Organization $organization): array
    {
        return [
            'code' => ['required', 'alpha_dash', 'max:60', \Illuminate\Validation\Rule::unique('catalog_references')
                ->where(fn ($query) => $query->where('organization_id', $organization->id)->where('reference_type', self::TYPE))],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'uuid'],
        ];
    }

    public function archiveNode(Organization $organization, CatalogReference $reference): void
    {
        abort_unless($reference->organization_id === $organization->id && $reference->reference_type === self::TYPE, 404);
        $this->guardArchive($reference);
        $reference->update(['is_active' => false]);
        $reference->delete();
    }

    /** @throws ValidationException */
    public function guardArchive(CatalogReference $reference): void
    {
        if ($reference->reference_type === self::TYPE
            && CatalogReference::where('parent_id', $reference->id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'Archivez d’abord les éléments rattachés à ce niveau de soins.']);
        }
    }

    /** Arbre des niveaux de soins visibles par l'organisation (référentiel global inclus). */
    public function tree(Organization $organization, bool $activeOnly = true): Collection
    {
        $nodes = CatalogReference::query()
            ->where('reference_type', self::TYPE)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'organization_id', 'parent_id', 'depth', 'code', 'name', 'description', 'is_active']);
        $children = $nodes->groupBy(fn (CatalogReference $node) => $node->parent_id ?? 'root');
        $build = function (string $key) use (&$build, $children): Collection {
            return $children->get($key, collect())->map(fn (CatalogReference $node) => [
                ...$node->toArray(),
                'level_label' => self::DEPTH_LABELS[$node->depth] ?? '',
                'children' => $build($node->id)->values()->all(),
            ])->values();
        };

        return $build('root');
    }

    private function ancestorIds(CatalogReference $node): Collection
    {
        $ids = collect();
        $current = $node;
        while ($current->parent_id && ! $ids->contains($current->parent_id) && $ids->count() < self::MAX_DEPTH) {
            $ids->push($current->parent_id);
            $current = CatalogReference::find($current->parent_id);
            if (! $current) {
                break;
            }
        }

        return $ids;
    }

    /**
     * Un nœud qui porte déjà des enfants garde sa place : le déplacer
     * modifierait la profondeur de toute sa descendance.
     */
    private function guardMoveWithChildren(?CatalogReference $reference, ?string $newParentId): void
    {
        if ($reference && $reference->parent_id !== $newParentId
            && CatalogReference::where('parent_id', $reference->id)->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'Cet élément possède des sous-éléments : il ne peut pas être déplacé.']);
        }
    }
}
