<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'coordination_admin')->value('id');
        if (! $roleId) return;
        foreach (DB::table('permissions')->whereIn('code', ['missions.view', 'missions.manage'])->pluck('id') as $permissionId) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'coordination_admin')->value('id');
        if ($roleId) DB::table('permission_role')->where('role_id', $roleId)->whereIn('permission_id', DB::table('permissions')->whereIn('code', ['missions.view', 'missions.manage'])->pluck('id'))->delete();
    }
};
