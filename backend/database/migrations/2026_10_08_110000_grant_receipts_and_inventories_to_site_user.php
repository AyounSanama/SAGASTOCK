<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Niveau 7 (cahier §3) : l'Utilisateur Site saisit les entrées en stock et les
 * inventaires. La validation de l'inventaire reste hors de son rôle.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'site_user')->value('id');
        if (! $roleId) {
            return;
        }

        $permissions = [
            'receipts.manage' => 'Enregistrer et valider les réceptions',
            'inventories.manage' => 'Gérer les inventaires',
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
