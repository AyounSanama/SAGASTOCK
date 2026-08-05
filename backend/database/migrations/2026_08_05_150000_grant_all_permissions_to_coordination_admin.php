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

        foreach (DB::table('permissions')->pluck('id') as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Les droits peuvent avoir été attribués explicitement après cette
        // migration. Aucun retrait automatique potentiellement destructeur.
    }
};
