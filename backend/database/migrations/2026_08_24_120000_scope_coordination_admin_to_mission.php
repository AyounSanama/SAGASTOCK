<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'coordination_admin')->value('id');
        if (! $roleId) {
            return;
        }

        DB::table('roles')->where('id', $roleId)->update([
            'scope_type' => 'mission',
            'scope_id' => null,
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('code', 'missions.manage')->value('id');
        if ($permissionId) {
            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }

        // Les affectations historiques restent volontairement inchangées : leur
        // conversion automatique serait ambiguë pour les organisations multipays.
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'coordination_admin')->value('id');
        if (! $roleId) {
            return;
        }

        DB::table('roles')->where('id', $roleId)->update([
            'scope_type' => 'organization',
            'scope_id' => null,
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('code', 'missions.manage')->value('id');
        if ($permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
};
