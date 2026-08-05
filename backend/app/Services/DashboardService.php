<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Project;
use App\Models\Site;
use App\Models\StockBalance;
use App\Models\User;

class DashboardService
{
    public function __construct(private readonly UserScopeService $scopes) {}

    public function build(User $user): array
    {
        $organizationIds = $this->scopes->organizationIds($user);
        $projectIds = $this->scopes->projectIds($user);
        $facilityIds = $this->scopes->facilityIds($user);
        $siteIds = $this->scopes->siteIds($user);
        $visibleUsers = $user->hasPermission('users.view') ? $this->scopes->users($user) : User::whereKey($user->id);

        $stats = [
            'organizations' => Organization::whereIn('id', $organizationIds)->count(),
            'missions' => Mission::whereIn('organization_id', $organizationIds)
                ->when($projectIds->isNotEmpty() && ! $user->hasPermission('missions.manage'),
                    fn ($query) => $query->whereHas('projects', fn ($projects) => $projects->whereIn('projects.id', $projectIds)))
                ->count(),
            'projects' => Project::whereIn('id', $projectIds)->count(),
            'facilities' => HealthFacility::whereIn('id', $facilityIds)->count(),
            'sites' => Site::whereIn('id', $siteIds)->count(),
            'products' => Product::whereIn('organization_id', $organizationIds)->where('is_active', true)->count(),
            'users_total' => (clone $visibleUsers)->count(),
            'users_active' => (clone $visibleUsers)->where('is_active', true)->count(),
            'users_archived' => $user->hasPermission('users.view') ? $this->scopes->users($user, User::onlyTrashed())->count() : 0,
            'stock_lines' => StockBalance::whereIn('site_id', $siteIds)->count(),
            'stock_quantity' => (float) StockBalance::whereIn('site_id', $siteIds)->sum('theoretical_quantity'),
            'stockouts' => StockBalance::whereIn('site_id', $siteIds)->where('theoretical_quantity', '<=', 0)->count(),
            'expiring_batches' => Batch::whereIn('id', StockBalance::whereIn('site_id', $siteIds)
                ->where('theoretical_quantity', '>', 0)->select('batch_id'))
                ->whereBetween('expires_on', [today(), today()->addDays(90)])->count(),
        ];

        $definitions = [
            ['key'=>'organizations','label'=>'Organisations','caption'=>'Structures accessibles','icon'=>'organizations','route'=>'/organizations','color'=>'orange','permission'=>'organizations.view'],
            ['key'=>'missions','label'=>'Missions','caption'=>'Missions de votre périmètre','icon'=>'missions','route'=>'/missions','color'=>'blue','permission'=>'missions.view'],
            ['key'=>'projects','label'=>'Projets','caption'=>'Projets accessibles','icon'=>'projects','route'=>'/projects','color'=>'cyan','permission'=>'projects.view'],
            ['key'=>'facilities','label'=>'Formations sanitaires','caption'=>'Structures sanitaires','icon'=>'facilities','route'=>'/health-facilities','color'=>'orange','permission'=>'health_facilities.view'],
            ['key'=>'sites','label'=>'Sites','caption'=>'Sites de dispensation','icon'=>'sites','route'=>'/dispensing-sites','color'=>'blue','permission'=>'dispensing_sites.view'],
            ['key'=>'users_active','label'=>'Utilisateurs actifs','caption'=>'Comptes actifs visibles','icon'=>'users','route'=>'/users','color'=>'red','permission'=>'users.view'],
            ['key'=>'products','label'=>'Produits autorisés','caption'=>'Produits actifs','icon'=>'products','route'=>'/products','color'=>'orange','permission'=>'products.view'],
            ['key'=>'stock_quantity','label'=>'Stock disponible','caption'=>'Unités disponibles','icon'=>'stocks','route'=>'/stocks','color'=>'blue','permission'=>'stocks.view'],
            ['key'=>'stockouts','label'=>'Ruptures','caption'=>'Lignes sans stock','icon'=>'reports','route'=>'/stocks','color'=>'red','permission'=>'stocks.view'],
            ['key'=>'expiring_batches','label'=>'Péremptions proches','caption'=>'Dans les 90 prochains jours','icon'=>'activity_logs','route'=>'/stocks','color'=>'orange','permission'=>'stocks.view'],
        ];

        $widgets = collect($definitions)
            ->filter(fn (array $widget) => $user->hasPermission($widget['permission']))
            ->map(fn (array $widget) => [...$widget, 'value' => $stats[$widget['key']] ?? 0])
            ->values()->all();

        return compact('stats', 'widgets', 'organizationIds', 'projectIds', 'facilityIds', 'siteIds');
    }
}
