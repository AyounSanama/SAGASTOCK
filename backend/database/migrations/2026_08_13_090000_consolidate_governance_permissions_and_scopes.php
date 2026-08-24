<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $definitions = [
            'sago_admin' => ['Admin Sago', 'platform'],
            'coordination_admin' => ['Admin Coordination', 'organization'],
            'project_admin' => ['Admin Projet', 'project'],
            'site_admin' => ['Admin Site de Dispensation', 'site'],
            'site_user' => ['Utilisateur du Site', 'site'],
        ];

        foreach ($definitions as $code => [$name, $scope]) {
            DB::table('roles')->where('code', $code)->update([
                'name' => $name, 'scope_type' => $scope, 'scope_id' => null,
                'is_system' => true, 'is_active' => true, 'updated_at' => $now,
            ]);
        }

        foreach ($this->matrix() as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if (! $roleId) continue;
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            foreach (DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id') as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        $sagoId = DB::table('roles')->where('code', 'sago_admin')->value('id');
        if ($sagoId) {
            DB::table('role_user')->where('role_id', $sagoId)->update([
                'scope_type' => 'platform', 'scope_id' => null, 'updated_at' => $now,
            ]);
        }

        $coordinationId = DB::table('roles')->where('code', 'coordination_admin')->value('id');
        if ($coordinationId) {
            foreach (DB::table('role_user')->where('role_id', $coordinationId)->get() as $assignment) {
                $organizationId = $assignment->scope_type === 'organization' ? $assignment->scope_id
                    : DB::table('users')->where('id', $assignment->user_id)->value('organization_id');
                if ($organizationId) DB::table('role_user')->where('role_id', $coordinationId)
                    ->where('user_id', $assignment->user_id)->update([
                        'scope_type' => 'organization', 'scope_id' => $organizationId, 'updated_at' => $now,
                    ]);
            }
        }
    }

    private function matrix(): array
    {
        return [
            'sago_admin' => [
                'dashboard.view','configuration.view','configuration.platform.manage',
                'organizations.view','organizations.manage','missions.view','projects.view',
                'standard_lists.view','standard_lists.manage','standards.assign','catalog.publish',
                'users.view','users.manage','roles.manage','audit.view','activity_logs.view','settings.view',
            ],
            'coordination_admin' => [
                'dashboard.view','standard_lists.view','catalog.view','products.view',
                'stocks.view','receipts.view','receipts.manage','dispensing.view','dispensing.manage',
                'patients.view','patients.manage','prescriptions.view','prescriptions.manage','prescriptions.validate',
                'dispensations.view','dispensations.manage','inventories.view','inventories.manage','inventories.validate',
                'orders.view','orders.manage','orders.approve','reports.view','reports.export',
                'synchronization.view','users.view','users.manage','activity_logs.view',
            ],
            'project_admin' => [
                'dashboard.view','project.view','standard_lists.view','catalog.view','products.view','products.manage',
                'stocks.view','receipts.view','receipts.manage','dispensing.view','dispensing.manage',
                'patients.view','patients.manage','prescriptions.view','prescriptions.manage','prescriptions.validate',
                'dispensations.view','dispensations.manage','inventories.view','inventories.manage',
                'orders.view','orders.manage','reports.view','reports.export','synchronization.view',
                'health_facilities.view','health_facilities.manage','dispensing_sites.view','dispensing_sites.manage',
                'users.view','users.create_site_admin','users.update_site_admin','users.suspend_site_admin',
                'project_settings.view','activity_logs.view',
            ],
            'site_admin' => [
                'dashboard.view','standard_lists.view','catalog.view','products.view','stocks.view','stocks.manage',
                'receipts.view','receipts.manage','dispensing.view','dispensing.manage','patients.view','patients.manage',
                'prescriptions.view','prescriptions.manage','dispensations.view','dispensations.manage',
                'inventories.view','inventories.manage','orders.view','orders.manage','orders.prepare',
                'reports.view','reports.export_local','synchronization.view','synchronization.manage',
                'site_settings.view','activity_logs.view_local',
            ],
            'site_user' => [
                'dashboard.view','standard_lists.view','catalog.view','products.view','stocks.view',
                'receipts.view','dispensing.view','patients.view','prescriptions.view','prescriptions.manage',
                'dispensations.view','dispensations.manage','inventories.view','orders.view',
                'reports.view','synchronization.view','activity_logs.view_local',
            ],
        ];
    }

    public function down(): void {}
};
