<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'project_admin')->value('id');
        if (! $roleId) return;

        $permissionIds = DB::table('permissions')->whereIn('code', [
            'projects.manage', 'funding.manage', 'standard_lists.manage', 'project_settings.manage',
        ])->pluck('id');

        DB::table('permission_role')->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)->delete();
    }

    public function down(): void
    {
        // Ces droits d’écriture violent le contrat V1 et ne sont pas restaurés automatiquement.
    }
};
