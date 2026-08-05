<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $newPermissions = [
            'project.view' => 'Consulter le projet courant',
            'health_facilities.view' => 'Consulter les formations sanitaires',
            'health_facilities.manage' => 'Gérer les formations sanitaires',
            'dispensing_sites.view' => 'Consulter les sites de dispensation',
            'dispensing_sites.manage' => 'Gérer les sites de dispensation',
            'users.create_site_admin' => 'Créer un Admin Site',
            'users.update_site_admin' => 'Modifier un Admin Site',
            'users.suspend_site_admin' => 'Suspendre ou réactiver un Admin Site',
            'receipts.view' => 'Consulter les réceptions',
            'orders.prepare' => 'Préparer les commandes',
            'reports.export_local' => 'Exporter les rapports locaux',
            'project_settings.view' => 'Consulter les paramètres du projet',
            'site_settings.view' => 'Consulter les paramètres du site',
            'activity_logs.view_local' => 'Consulter le journal local',
        ];
        foreach ($newPermissions as $code => $name) {
            DB::table('permissions')->updateOrInsert(['code' => $code], [
                'name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $matrix = self::matrix();
        foreach ($matrix as $roleCode => $codes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if (! $roleId) continue;
            $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleId, 'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void {}

    /** @return array<string, list<string>> */
    private static function matrix(): array
    {
        return [
            'coordination_admin' => [
                'dashboard.view', 'configuration.view', 'configuration.organization.manage',
                'organizations.view', 'missions.view', 'missions.manage', 'projects.view',
                'projects.manage', 'funding.view', 'funding.manage', 'structures.view',
                'structures.manage', 'health_facilities.view', 'health_facilities.manage',
                'sites.view', 'sites.manage', 'dispensing_sites.view', 'dispensing_sites.manage',
                'modules.manage', 'standard_lists.view', 'standard_lists.manage', 'catalog.view',
                'catalog.manage', 'catalog.publish', 'products.view', 'products.manage',
                'stocks.view', 'inventories.view', 'orders.view', 'orders.manage',
                'reports.view', 'reports.export', 'synchronization.view', 'settings.view',
                'settings.manage', 'users.view', 'users.manage', 'audit.view', 'activity_logs.view',
            ],
            'project_admin' => [
                'dashboard.view', 'organizations.view', 'missions.view', 'project.view',
                'projects.view', 'health_facilities.view', 'health_facilities.manage',
                'structures.view', 'structures.manage', 'dispensing_sites.view',
                'dispensing_sites.manage', 'sites.view', 'sites.manage', 'users.view',
                'users.create_site_admin', 'users.update_site_admin', 'users.suspend_site_admin',
                'standard_lists.view', 'catalog.view', 'products.view', 'products.manage',
                'stocks.view', 'inventories.view', 'orders.view', 'orders.manage',
                'reports.view', 'reports.export', 'synchronization.view',
                'project_settings.view', 'activity_logs.view',
            ],
            'site_admin' => [
                'dashboard.view', 'catalog.view', 'products.view', 'stocks.view', 'stocks.manage',
                'receipts.view', 'receipts.manage', 'dispensing.view', 'dispensing.manage',
                'inventories.view', 'inventories.manage', 'orders.view', 'orders.prepare',
                'reports.view', 'reports.export_local', 'synchronization.view',
                'synchronization.manage', 'site_settings.view', 'activity_logs.view_local',
            ],
        ];
    }
};
