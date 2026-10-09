<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Niveau 7 (décision du 09/10, matrice des rôles E1) : la Coordination et
 * l'Admin Projet ne saisissent ni patient, ni ordonnance, ni dispensation, et
 * ne voient plus la fiche patient. Ils gardent la consultation des
 * ordonnances et des dispensations (sans identité du patient).
 */
return new class extends Migration
{
    private const ROLES = ['coordination_admin', 'project_admin'];

    private const PERMISSIONS = [
        'patients.view', 'patients.manage', 'prescriptions.manage', 'prescriptions.validate',
        'dispensations.manage', 'dispensing.manage',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')->whereIn('code', self::ROLES)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('code', self::PERMISSIONS)->pluck('id');
        if ($roleIds->isEmpty() || $permissionIds->isEmpty()) {
            return;
        }

        DB::table('permission_role')->whereIn('role_id', $roleIds)->whereIn('permission_id', $permissionIds)->delete();
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')->whereIn('code', self::ROLES)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('code', self::PERMISSIONS)->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }
};
