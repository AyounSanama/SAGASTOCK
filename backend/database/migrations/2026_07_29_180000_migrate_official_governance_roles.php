<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'platform_owner' => ['owner', 'Propriétaire de la plateforme', 'platform'],
            'organization_admin' => ['coordination_admin', 'Admin Coordination', 'organization'],
            'project_coordinator' => ['project_admin', 'Admin Projet', 'project'],
            'facility_manager' => ['site_admin', 'Admin Site de Dispensation', 'site'],
            'pharmacist' => ['site_user', 'Utilisateur du Site', 'site'],
        ];

        foreach ($renames as $old => [$code, $name, $scope]) {
            DB::table('roles')->where('code', $old)->update([
                'code' => $code,
                'name' => $name,
                'scope_type' => $scope,
                'scope_id' => null,
                'is_system' => true,
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }

        $siteUserId = DB::table('roles')->where('code', 'site_user')->value('id');
        foreach (['clinician', 'supervisor'] as $legacyCode) {
            $legacyId = DB::table('roles')->where('code', $legacyCode)->value('id');
            if (!$legacyId) continue;
            if ($siteUserId) {
                DB::table('role_user')->where('role_id', $legacyId)->get()->each(function ($assignment) use ($siteUserId) {
                    DB::table('role_user')->insertOrIgnore([
                        'user_id' => $assignment->user_id,
                        'role_id' => $siteUserId,
                        'scope_type' => $assignment->scope_type,
                        'scope_id' => $assignment->scope_id,
                        'created_at' => $assignment->created_at,
                        'updated_at' => now(),
                    ]);
                });
            }
            DB::table('role_user')->where('role_id', $legacyId)->delete();
            DB::table('permission_role')->where('role_id', $legacyId)->delete();
            DB::table('roles')->where('id', $legacyId)->delete();
        }
    }

    public function down(): void
    {
        $renames = [
            'owner' => ['platform_owner', 'Propriétaire de la plateforme', 'platform'],
            'coordination_admin' => ['organization_admin', 'Administrateur ONG', 'organization'],
            'project_admin' => ['project_coordinator', 'Coordinateur de projet', 'project'],
            'site_admin' => ['facility_manager', 'Responsable de formation sanitaire', 'facility'],
            'site_user' => ['pharmacist', 'Pharmacien / Gestionnaire de stock', 'site'],
        ];
        foreach ($renames as $old => [$code, $name, $scope]) {
            DB::table('roles')->where('code', $old)->update([
                'code' => $code,
                'name' => $name,
                'scope_type' => $scope,
                'updated_at' => now(),
            ]);
        }
    }
};
