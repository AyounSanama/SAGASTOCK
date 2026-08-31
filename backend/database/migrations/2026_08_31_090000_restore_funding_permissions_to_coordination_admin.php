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

        $permissions = [
            'funding.view' => 'Consulter les bailleurs et programmes',
            'funding.manage' => 'Gérer les bailleurs et programmes',
        ];

        foreach ($permissions as $code => $name) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'updated_at' => now(), 'created_at' => now()],
            );

            $permissionId = DB::table('permissions')->where('code', $code)->value('id');
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Aucun retrait automatique : ces permissions peuvent avoir été
        // explicitement accordées par un administrateur après la migration.
    }
};
