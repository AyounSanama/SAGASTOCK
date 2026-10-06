<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class ApplicationNavigationService
{
    /** Manifeste unique : chaque entrée dépend exclusivement d'une permission. */
    private const ITEMS = [
        ['key' => 'dashboard', 'label' => 'Tableau de bord', 'route' => 'dashboard', 'path' => '/dashboard', 'icon' => 'dashboard', 'permission' => null],
        ['key' => 'patients', 'label' => 'Bénéficiaires', 'route' => 'modules.dispensing', 'path' => '/dispensations#beneficiaries', 'icon' => 'patients', 'permission' => 'patients.view', 'webOnly' => true],
        ['key' => 'missions', 'label' => 'Missions', 'route' => 'modules.missions', 'path' => '/missions', 'icon' => 'missions', 'permission' => 'missions.view'],
        ['key' => 'projects', 'label' => 'Projet actuel', 'route' => 'modules.projects', 'path' => '/projects', 'icon' => 'projects', 'permission' => 'projects.view'],
        ['key' => 'configuration', 'label' => 'Configuration', 'route' => 'configuration.index', 'path' => '/configuration', 'icon' => 'configuration', 'permission' => 'configuration.view'],
        ['key' => 'organizations', 'label' => 'Organisations', 'route' => 'organizations.index', 'path' => '/organizations', 'icon' => 'organizations', 'permission' => 'organizations.view'],
        ['key' => 'funding', 'label' => 'Bailleurs et programmes', 'route' => 'modules.funding', 'path' => '/funding', 'icon' => 'funding', 'permission' => 'funding.view'],
        ['key' => 'facilities', 'label' => 'Formations sanitaires', 'route' => 'modules.health-facilities', 'path' => '/health-facilities', 'icon' => 'facilities', 'permission' => 'health_facilities.view'],
        ['key' => 'sites', 'label' => 'Sites de dispensation', 'route' => 'modules.dispensing-sites', 'path' => '/dispensing-sites', 'icon' => 'sites', 'permission' => 'dispensing_sites.view'],
        // Niveau 2 : « Référentiels » de la Coordination (Bailleurs, Référentiel médical).
        ['key' => 'referentials', 'label' => 'Référentiels', 'route' => 'modules.funding', 'path' => '/funding', 'icon' => 'referentials', 'permission' => 'funding.view', 'webOnly' => true],
        ['key' => 'users', 'label' => 'Utilisateurs', 'route' => 'users.index', 'path' => '/users', 'icon' => 'users', 'permission' => 'users.view'],
        ['key' => 'standard-lists', 'label' => 'Listes standards de médicaments', 'route' => 'modules.standard-lists', 'path' => '/standard-lists', 'icon' => 'standard_lists', 'permission' => 'standard_lists.view'],
        ['key' => 'products', 'label' => 'Produits', 'route' => 'modules.products', 'path' => '/products', 'icon' => 'products', 'permission' => 'products.view'],
        ['key' => 'stocks', 'label' => 'Stocks', 'route' => 'modules.stocks', 'path' => '/stocks', 'icon' => 'stocks', 'permission' => 'stocks.view'],
        ['key' => 'receipts', 'label' => 'Entrées en stock', 'route' => 'modules.receipts', 'path' => '/receipts', 'icon' => 'receipts', 'permission' => 'receipts.view'],
        ['key' => 'dispensing', 'label' => 'Dispensation de médicaments', 'route' => 'modules.dispensing', 'path' => '/dispensations', 'icon' => 'dispensing', 'permission' => 'dispensing.view'],
        ['key' => 'inventory-orders', 'label' => 'Inventaires & Commandes', 'route' => 'modules.inventories', 'path' => '/inventories', 'icon' => 'inventories', 'permission' => 'inventories.view'],
        ['key' => 'inventories', 'label' => 'Inventaires', 'route' => 'modules.inventories', 'path' => '/inventories', 'icon' => 'inventories', 'permission' => 'inventories.view'],
        ['key' => 'orders', 'label' => 'Commandes', 'route' => 'modules.orders', 'path' => '/orders', 'icon' => 'orders', 'permission' => 'orders.view'],
        ['key' => 'reports', 'label' => 'Rapports', 'route' => 'modules.reports', 'path' => '/reports', 'icon' => 'reports', 'permission' => 'reports.view'],
        ['key' => 'synchronization', 'label' => 'Synchronisation', 'route' => 'modules.synchronization', 'path' => '/synchronization', 'icon' => 'synchronization', 'permission' => 'synchronization.view'],
        ['key' => 'settings', 'label' => 'Paramètres organisation', 'route' => 'modules.settings', 'path' => '/settings', 'icon' => 'settings', 'permission' => 'settings.view'],
        ['key' => 'project_settings', 'label' => 'Paramètres du projet', 'route' => 'modules.project-settings', 'path' => '/project-settings', 'icon' => 'settings', 'permission' => 'project_settings.view'],
        ['key' => 'site_settings', 'label' => 'Paramètres du site', 'route' => 'modules.site-settings', 'path' => '/site-settings', 'icon' => 'settings', 'permission' => 'site_settings.view'],
        ['key' => 'activity_logs', 'label' => 'Journal des activités', 'route' => 'modules.activity-log', 'path' => '/activity-log', 'icon' => 'activity_logs', 'permission' => 'activity_logs.view'],
        ['key' => 'local_activity_logs', 'label' => 'Journal local', 'route' => 'modules.activity-log-local', 'path' => '/activity-log-local', 'icon' => 'activity_logs', 'permission' => 'activity_logs.view_local'],
        ['key' => 'profile', 'label' => 'Mon profil', 'route' => 'profile.show', 'path' => '/profile', 'icon' => 'profile', 'permission' => null],
    ];

    public function permissions(User $user): Collection
    {
        return $user->roles()->with('permissions:id,code')->get()
            ->pluck('permissions')->flatten()->pluck('code')
            ->merge($user->directPermissions()->pluck('code'))
            ->when($user->read_only, fn (Collection $codes) => $codes->filter(fn (string $code) => User::isReadPermission($code)))
            ->unique()->values();
    }

    public function items(User $user): array
    {
        $permissions = $this->permissions($user);
        $role = app(GovernanceService::class)->roleCode($user);
        $allowedKeys = $this->allowedKeys($role);
        $items = collect(self::ITEMS)
            ->filter(fn (array $item) => $allowedKeys === null || in_array($item['key'], $allowedKeys, true))
            ->filter(fn (array $item) => $this->isVisible($item, $permissions));

        return $items
            ->map(function (array $item) use ($role): array {
                if ($role === GovernanceService::SAGO_ADMIN && $item['key'] === 'dashboard') {
                    $item['route'] = 'sago.dashboard';
                    $item['path'] = '/sago/dashboard';
                }
                if ($role === GovernanceService::COORDINATION_ADMIN && $item['key'] === 'missions') {
                    $item['label'] = 'Ma Coordination';
                    // Les projets se créent et s'ouvrent depuis « Ma Coordination ».
                    $item['active_routes'] = ['modules.missions', 'coordination.*', 'organizations.missions.*', 'modules.projects', 'projects.wizard.*', 'projects.medical-configuration*'];
                }
                if ($role === GovernanceService::COORDINATION_ADMIN && $item['key'] === 'standard-lists') {
                    $item['label'] = 'Liste Standard';
                }
                if ($item['key'] === 'referentials') {
                    $item['active_routes'] = ['modules.funding', 'projects.medical-references*'];
                }
                if ($role === GovernanceService::COORDINATION_ADMIN && $item['key'] === 'projects') {
                    $item['label'] = 'Configuration des projets';
                }
                if ($role === GovernanceService::PROJECT_ADMIN && $item['key'] === 'projects') {
                    $item['label'] = 'Projet & FOSA';
                    // Onglets FOSA et Comptes utilisateurs de « Projet & FOSA ».
                    $item['active_routes'] = ['modules.projects', 'modules.health-facilities', 'users.*'];
                }
                if ($role === GovernanceService::PROJECT_ADMIN && $item['key'] === 'facilities') {
                    $item['label'] = 'FOSA';
                }
                $menu = config("pharmacare_v1.menu.$role");
                $item['menu'] = $menu === null || in_array($item['key'], $menu, true);
                if ($role === GovernanceService::PROJECT_ADMIN && $item['key'] === 'standard-lists') {
                    $item['label'] = 'Liste standard';
                }
                if ($item['key'] === 'standard-lists') {
                    // /standard-lists redirige vers la Liste Standard du catalogue.
                    $item['active_routes'] = ['modules.standard-lists', 'organizations.catalog.*', 'projects.standard-list.*'];
                }
                if ($role === GovernanceService::PROJECT_ADMIN && $item['key'] === 'users') {
                    $item['label'] = 'Équipe FOSA';
                }
                $item['url'] = $this->buildUrl($item['route']);
                if ($item['key'] === 'patients' && $item['url'] !== null) {
                    $item['url'] .= '#beneficiaries';
                }

                return $item;
            })->values()->all();
    }

    private function allowedKeys(?string $role): ?array
    {
        return match ($role) {
            GovernanceService::SAGO_ADMIN => ['dashboard', 'configuration', 'profile'],
            GovernanceService::COORDINATION_ADMIN => [
                ...config('pharmacare_v1.navigation.coordination_admin'),
            ],
            GovernanceService::PROJECT_ADMIN => [
                ...config('pharmacare_v1.navigation.project_admin'),
            ],
            GovernanceService::SITE_ADMIN,
            GovernanceService::SITE_USER => [
                'dashboard', 'standard-lists', 'stocks', 'receipts', 'dispensing',
                'inventory-orders', 'reports', 'synchronization', 'profile',
            ],
            default => null,
        };
    }

    private function buildUrl(?string $route): ?string
    {
        if ($route === null || ! Route::has($route)) {
            return null;
        }

        try {
            return route($route);
        } catch (UrlGenerationException $exception) {
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

    /**
     * AM-161 — Fil d'Ariane ONG / Coordination / Projet / FOSA du compte.
     *
     * @return array{organization: ?string, coordination: ?string, country: ?string, project: ?string, facility: ?string}
     */
    public function context(User $user): array
    {
        // Menu latéral et barre supérieure le demandent sur la même page.
        static $cache = null;
        $cache ??= new \WeakMap();
        if (isset($cache[$user])) {
            return $cache[$user];
        }
        $scopes = app(UserScopeService::class);
        $role = app(GovernanceService::class)->roleCode($user);
        $project = in_array($role, [GovernanceService::PROJECT_ADMIN], true)
            ? \App\Models\Project::with('mission.country')->whereIn('id', $scopes->projectIds($user))->orderBy('name')->first()
            : null;
        $site = in_array($role, [GovernanceService::SITE_ADMIN, GovernanceService::SITE_USER], true)
            ? \App\Models\Site::with('healthFacility.mission.country', 'healthFacility.projects')->whereIn('id', $scopes->siteIds($user))->first()
            : null;
        $mission = $project?->mission ?? $site?->healthFacility?->mission;
        if (! $mission && $role === GovernanceService::COORDINATION_ADMIN) {
            $mission = \App\Models\Mission::with('country')->whereIn('id', $scopes->coordinationMissionIds($user))->first();
        }

        return $cache[$user] = [
            'organization' => $role === GovernanceService::SAGO_ADMIN ? null : $user->organization?->name,
            'coordination' => $mission?->name,
            'country' => $mission?->country?->name,
            'project' => $project?->name ?? $site?->healthFacility?->projects->first()?->name,
            'facility' => $site?->healthFacility?->name,
        ];
    }

    public function mobileItems(User $user): array
    {
        $sago = app(GovernanceService::class)->roleCode($user) === GovernanceService::SAGO_ADMIN;

        return collect($this->items($user))->whereNotNull('path')->reject(fn (array $item) => $item['webOnly'] ?? false)
            ->map(fn (array $item) => [
                'key' => $item['key'],
                'label' => $item['label'],
                'path' => $item['key'] === 'dashboard' && ! $sago ? '/home' : $item['path'],
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
            'mission_ids' => $scopes->coordinationMissionIds($user)->values()->all(),
            'project_ids' => $scopes->projectIds($user)->values()->all(),
            'facility_ids' => $scopes->facilityIds($user)->values()->all(),
            'site_ids' => $scopes->siteIds($user)->values()->all(),
        ];
    }
}
