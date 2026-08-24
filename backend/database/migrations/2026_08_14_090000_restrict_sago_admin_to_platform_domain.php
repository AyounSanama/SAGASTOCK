<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'sago_admin')->value('id');
        if (! $roleId) return;

        $allowed = [
            'dashboard.view', 'configuration.view', 'configuration.platform.manage',
            'organizations.view', 'organizations.manage', 'standards.assign',
            'platform_standards.view', 'platform_standards.manage',
            'audit.view', 'activity_logs.view',
        ];

        DB::table('permission_role')->where('role_id', $roleId)->delete();
        $rows = DB::table('permissions')->whereIn('code', $allowed)->pluck('id')->map(
            fn ($permissionId) => ['role_id' => $roleId, 'permission_id' => $permissionId],
        )->all();
        if ($rows !== []) DB::table('permission_role')->insert($rows);
    }

    public function down(): void
    {
        // Ne pas restaurer des droits opérationnels devenus interdits.
    }
};
