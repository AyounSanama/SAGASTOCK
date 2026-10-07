<?php

namespace App\Services;

use App\Models\HealthFacility;
use App\Models\ProductCode;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Niveau 6 — Lecture de la « Liste Standard » de la Coordination (Web et API) :
 * projets de la coordination, FOSA du projet, liste du projet ou d'une FOSA.
 */
class CoordinationStandardListService
{
    public function __construct(
        private UserScopeService $scopes,
        private ProjectAdminWorkspaceService $workspace,
        private StandardListCatalogService $catalog,
    ) {}

    /** Projets de la Coordination (les projets archivés sont exclus par la suppression douce). */
    public function projects(User $actor): Collection
    {
        return $this->scopes->projects($actor)
            ->with('donors:id,name')->orderBy('code')->get(['id', 'organization_id', 'code', 'name', 'status']);
    }

    /** FOSA du projet, hors FOSA refusées. */
    public function facilities(Project $project): Collection
    {
        return $project->healthFacilities()->where('validation_status', '!=', HealthFacility::STATUS_REFUSED)
            ->orderBy('name')->get(['health_facilities.id', 'health_facilities.name', 'health_facilities.code', 'health_facilities.care_level_id']);
    }

    /**
     * Liste du projet ; pour une FOSA, seuls les articles qu'elle peut recevoir
     * (selon ses critères), « Retenu » = non décoché par la Coordination.
     */
    public function list(Project $project, ?HealthFacility $facility): array
    {
        $list = $this->workspace->standardList($project);
        if ($facility) {
            $candidates = $this->catalog->facilityCandidateIds($facility);
            $excluded = $this->catalog->excludedIds($facility);
            $list['rows'] = $list['rows']->filter(fn (array $row) => $row['retained'] && $candidates->contains($row['product']->id))
                ->map(fn (array $row) => [...$row, 'retained' => ! $excluded->contains($row['product']->id)])->values();
            $list['pathologies'] = $list['rows']->pluck('pathology')->filter()->unique()->sort()->values();
            $list['facility'] = $facility;
        }

        return $list;
    }

    /** @return Collection<string,string> produit => code-barres */
    public function barcodes(Collection $productIds): Collection
    {
        return ProductCode::whereIn('product_id', $productIds)->where('code_type', 'barcode')->pluck('value', 'product_id');
    }
}
