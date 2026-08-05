<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ModulePlaceholderController extends Controller
{
    private const MODULES = [
        'dashboard' => ['Tableau de bord', 'dashboard', null],
        'projects' => ['Projets', 'briefcase', 'projects.view'],
        'missions' => ['Missions', 'briefcase', 'missions.view'],
        'funding' => ['Bailleurs et programmes', 'briefcase', 'funding.view'],
        'health-facilities' => ['Formations sanitaires', 'location', 'health_facilities.view'],
        'dispensing-sites' => ['Sites de dispensation', 'location', 'dispensing_sites.view'],
        'users' => ['Utilisateurs', 'users', 'users.view'],
        'standard-lists' => ['Listes standards', 'list', 'catalog.view'],
        'products' => ['Produits', 'products', 'catalog.view'],
        'stocks' => ['Stocks', 'stocks', 'stocks.view'],
        'receipts' => ['Réceptions', 'stocks', 'receipts.view'],
        'dispensing' => ['Dispensation', 'products', 'dispensing.view'],
        'inventories' => ['Inventaires', 'stocks', 'inventories.view'],
        'orders' => ['Commandes', 'list', 'orders.view'],
        'reports' => ['Rapports', 'reports', 'reports.view'],
        'synchronization' => ['Synchronisation', 'sync', 'synchronization.view'],
        'settings' => ['Paramètres organisation', 'settings', 'settings.view'],
        'project-settings' => ['Paramètres du projet', 'settings', 'project_settings.view'],
        'site-settings' => ['Paramètres du site', 'settings', 'site_settings.view'],
        'activity-log' => ['Journal des activités', 'activity', 'activity_logs.view'],
        'activity-log-local' => ['Journal local', 'activity', 'activity_logs.view_local'],
    ];

    public function show(string $module): View
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $permission = self::MODULES[$module][2];
        abort_unless($permission === null || request()->user()->hasPermission($permission), 403);

        return view('modules.placeholder', [
            'moduleKey' => $module,
            'moduleName' => self::MODULES[$module][0],
            'moduleIcon' => self::MODULES[$module][1],
        ]);
    }
}
