<?php

namespace App\Services;

use App\Models\HealthFacility;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Site;
use App\Models\StandardList;
use App\Models\StandardListVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Produits autorisés pour des sites : Liste Standard des projets de leurs
 * FOSA (niveau 6 : liste propre à la FOSA, articles décochés retirés).
 * Commun à la dispensation et aux commandes.
 */
class AllowedProductsService
{
    /** @param Collection<int, string> $siteIds */
    public function query(Organization $organization, Collection $siteIds): Builder
    {
        $projectIds = DB::table('health_facility_project')
            ->join('sites', 'sites.health_facility_id', '=', 'health_facility_project.health_facility_id')
            ->whereIn('sites.id', $siteIds)
            ->pluck('health_facility_project.project_id')->unique();
        $lists = StandardList::where('organization_id', $organization->id)
            ->where('scope_type', 'project')->whereIn('scope_id', $projectIds)
            ->where('is_active', true)->get(['id', 'allow_outside_list']);
        $query = Product::where('organization_id', $organization->id);
        if ($lists->isEmpty() || $lists->contains('allow_outside_list', true)) {
            return $query;
        }

        $versionIds = StandardListVersion::whereIn('standard_list_id', $lists->pluck('id'))
            ->where('status', 'published')->pluck('id');
        $productIds = DB::table('standard_list_items')
            ->whereIn('standard_list_version_id', $versionIds)->pluck('product_id');
        // Niveau 6 : une FOSA configurée suit sa propre Liste Standard (critères
        // de la FOSA, articles décochés par la Coordination retirés).
        $facilities = HealthFacility::whereIn('id', Site::whereIn('id', $siteIds)->pluck('health_facility_id'))->get();
        if ($facilities->isNotEmpty() && $facilities->every(fn ($facility) => $facility->care_level_id)) {
            $management = app(HealthFacilityManagementService::class);
            $productIds = $facilities->flatMap(fn ($facility) => $management->standardList($facility)->pluck('id'))->unique()->values();
        }

        return $query->whereIn('id', $productIds);
    }
}
