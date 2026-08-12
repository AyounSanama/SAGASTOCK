<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class ApplicationNavigationService
{
    /** Manifeste unique : chaque entrée dépend exclusivement d'une permission. */
    private const ITEMS = [
        ['key'=>'dashboard','label'=>'Tableau de bord','route'=>'dashboard','path'=>'/dashboard','icon'=>'dashboard','permission'=>null],
        ['key'=>'configuration','label'=>'Configuration','route'=>'configuration.index','path'=>'/configuration','icon'=>'configuration','permission'=>'configuration.view'],
        ['key'=>'organizations','label'=>'Organisations','route'=>'organizations.index','path'=>'/organizations','icon'=>'organizations','permission'=>'organizations.view'],
        ['key'=>'funding','label'=>'Bailleurs et programmes','route'=>'modules.funding','path'=>'/funding','icon'=>'funding','permission'=>'funding.view'],
        ['key'=>'facilities','label'=>'Formations sanitaires','route'=>'modules.health-facilities','path'=>'/health-facilities','icon'=>'facilities','permission'=>'health_facilities.view'],
        ['key'=>'sites','label'=>'Sites de dispensation','route'=>'modules.dispensing-sites','path'=>'/dispensing-sites','icon'=>'sites','permission'=>'dispensing_sites.view'],
        ['key'=>'users','label'=>'Utilisateurs','route'=>'users.index','path'=>'/users','icon'=>'users','permission'=>'users.view'],
        ['key'=>'standard-lists','label'=>'Listes standards','route'=>'modules.standard-lists','path'=>'/standard-lists','icon'=>'standard_lists','permission'=>'standard_lists.view'],
        ['key'=>'products','label'=>'Produits','route'=>'modules.products','path'=>'/products','icon'=>'products','permission'=>'products.view'],
        ['key'=>'stocks','label'=>'Stocks','route'=>'modules.stocks','path'=>'/stocks','icon'=>'stocks','permission'=>'stocks.view'],
        ['key'=>'receipts','label'=>'Réceptions','route'=>'modules.receipts','path'=>'/receipts','icon'=>'receipts','permission'=>'receipts.view'],
        ['key'=>'dispensing','label'=>'Dispensation','route'=>'modules.dispensing','path'=>'/dispensations','icon'=>'dispensing','permission'=>'dispensing.view'],
        ['key'=>'inventories','label'=>'Inventaires','route'=>'modules.inventories','path'=>'/inventories','icon'=>'inventories','permission'=>'inventories.view'],
        ['key'=>'orders','label'=>'Commandes','route'=>'modules.orders','path'=>'/orders','icon'=>'orders','permission'=>'orders.view'],
        ['key'=>'reports','label'=>'Rapports','route'=>'modules.reports','path'=>'/reports','icon'=>'reports','permission'=>'reports.view'],
        ['key'=>'synchronization','label'=>'Synchronisation','route'=>'modules.synchronization','path'=>'/synchronization','icon'=>'synchronization','permission'=>'synchronization.view'],
        ['key'=>'settings','label'=>'Paramètres organisation','route'=>'modules.settings','path'=>'/settings','icon'=>'settings','permission'=>'settings.view'],
        ['key'=>'project_settings','label'=>'Paramètres du projet','route'=>'modules.project-settings','path'=>'/project-settings','icon'=>'settings','permission'=>'project_settings.view'],
        ['key'=>'site_settings','label'=>'Paramètres du site','route'=>'modules.site-settings','path'=>'/site-settings','icon'=>'settings','permission'=>'site_settings.view'],
        ['key'=>'activity_logs','label'=>'Journal des activités','route'=>'modules.activity-log','path'=>'/activity-log','icon'=>'activity_logs','permission'=>'activity_logs.view'],
        ['key'=>'local_activity_logs','label'=>'Journal local','route'=>'modules.activity-log-local','path'=>'/activity-log-local','icon'=>'activity_logs','permission'=>'activity_logs.view_local'],
        ['key'=>'profile','label'=>'Mon profil','route'=>'profile.show','path'=>'/profile','icon'=>'profile','permission'=>null],
    ];

    public function permissions(User $user): Collection
    {
        return $user->roles()->with('permissions:id,code')->get()
            ->pluck('permissions')->flatten()->pluck('code')
            ->merge($user->directPermissions()->pluck('code'))
            ->unique()->values();
    }

    public function items(User $user): array
    {
        $permissions = $this->permissions($user);
        if (app(GovernanceService::class)->roleCode($user) === GovernanceService::SAGO_ADMIN) {
            return collect(self::ITEMS)
                ->whereIn('key', ['dashboard', 'configuration', 'profile'])
                ->map(function (array $item): array {
                    $item['route'] = $item['key'] === 'dashboard' ? 'sago.dashboard' : $item['route'];
                    $item['url'] = $this->buildUrl($item['route']);
                    return $item;
                })->values()->all();
        }
        $items = collect(self::ITEMS)
            ->filter(fn(array $item) => $this->isVisible($item, $permissions));

        return $items
            ->map(function (array $item): array {
                $item['url'] = $this->buildUrl($item['route']);
                return $item;
            })->values()->all();
    }

    private function buildUrl(?string $route): ?string
    {
        if ($route === null || ! Route::has($route)) {
            return null;
        }

        try {
            return route($route);
        } catch (\Illuminate\Routing\Exceptions\UrlGenerationException $exception) {
            return null;
        }
    }

    private function isVisible(array $item, Collection $permissions): bool
    {
        if ($item['permission'] === null) {
            return true;
        }

        $permission = $item['permission'];
        return $permissions->contains($permission) || $permissions->contains($this->compatibilityPermission($permission));
    }

    private function compatibilityPermission(string $permission): string
    {
        return match ($permission) {
            'products.view' => 'catalog.view',
            'sites.view' => 'structures.view',
            'sites.manage' => 'structures.manage',
            default => $permission,
        };
    }

    public function mobileItems(User $user): array
    {
        return collect($this->items($user))->whereNotNull('path')
            ->map(fn(array $item) => [
                'key' => $item['key'],
                'label' => $item['label'],
                'path' => $item['key'] === 'dashboard' ? '/home' : $item['path'],
                'icon' => $item['icon'],
                'permission' => $item['permission'],
            ])->values()->all();
    }

    public function scope(User $user): array
    {
        $scopes = app(UserScopeService::class);
        return [
            'platform' => $scopes->isPlatform($user),
            'organization_ids' => $scopes->organizationIds($user)->values()->all(),
            'project_ids' => $scopes->projectIds($user)->values()->all(),
            'facility_ids' => $scopes->facilityIds($user)->values()->all(),
            'site_ids' => $scopes->siteIds($user)->values()->all(),
        ];
    }
}
