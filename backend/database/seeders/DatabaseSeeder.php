<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissionNames = [
            'organizations.view' => 'Consulter les organisations',
            'organizations.manage' => 'Gérer les organisations',
            'missions.view' => 'Consulter les pays et missions',
            'missions.manage' => 'Gérer les pays et missions',
            'projects.view' => 'Consulter les projets',
            'projects.manage' => 'Gérer les projets',
            'funding.view' => 'Consulter les bailleurs et programmes',
            'funding.manage' => 'Gérer les bailleurs et programmes',
            'structures.view' => 'Consulter les formations sanitaires et sites',
            'structures.manage' => 'Gérer les formations sanitaires et sites',
            'modules.manage' => 'Activer les modules par périmètre',
            'catalog.view' => 'Consulter les référentiels et produits',
            'catalog.manage' => 'Gérer les référentiels et produits',
            'catalog.publish' => 'Publier les listes standards',
            'batches.manage' => 'Gérer les lots produits',
            'stocks.view' => 'Consulter les stocks et mouvements',
            'stocks.manage' => 'Enregistrer les mouvements de stock',
            'stocks.adjust' => 'Valider les ajustements, pertes et quarantaines',
            'transfers.manage' => 'Gérer les transferts de stock',
            'receipts.manage' => 'Enregistrer et valider les réceptions',
            'users.view' => 'Consulter les utilisateurs',
            'users.manage' => 'Gérer les utilisateurs',
            'roles.manage' => 'Gérer les rôles',
            'audit.view' => "Consulter le journal d'audit",
        ];
        $permissions = collect($permissionNames)->map(
            fn ($name, $code) => Permission::updateOrCreate(['code' => $code], ['name' => $name]),
        );

        $viewPermissions = ['organizations.view', 'missions.view', 'projects.view', 'funding.view', 'structures.view', 'catalog.view', 'stocks.view'];
        $roleDefinitions = [
            'platform_owner' => ['Propriétaire de la plateforme', array_keys($permissionNames)],
            'organization_admin' => ['Administrateur ONG', [
                ...$viewPermissions, 'missions.manage', 'projects.manage', 'funding.manage', 'structures.manage', 'modules.manage', 'catalog.manage', 'catalog.publish', 'batches.manage', 'stocks.manage', 'stocks.adjust', 'transfers.manage', 'receipts.manage',
                'users.view', 'users.manage', 'roles.manage', 'audit.view',
            ]],
            'project_coordinator' => ['Coordinateur de projet', [
                ...$viewPermissions, 'projects.manage', 'funding.manage', 'structures.manage', 'catalog.manage', 'catalog.publish', 'batches.manage', 'stocks.manage', 'stocks.adjust', 'transfers.manage', 'receipts.manage',
                'users.view', 'users.manage', 'audit.view',
            ]],
            'facility_manager' => ['Responsable de formation sanitaire', [
                ...$viewPermissions, 'structures.manage', 'batches.manage', 'stocks.manage', 'stocks.adjust', 'transfers.manage', 'receipts.manage', 'users.view', 'users.manage', 'audit.view',
            ]],
            'pharmacist' => ['Pharmacien / Gestionnaire de stock', [...$viewPermissions, 'stocks.manage', 'transfers.manage', 'receipts.manage']],
            'clinician' => ['Clinicien / Prescripteur', ['organizations.view', 'missions.view', 'projects.view']],
            'supervisor' => ['Superviseur / Auditeur', [...$viewPermissions, 'users.view', 'audit.view']],
        ];

        foreach ($roleDefinitions as $code => [$name, $codes]) {
            $role = Role::updateOrCreate(['code' => $code], ['name' => $name, 'is_system' => true]);
            $role->permissions()->sync($permissions->only($codes)->pluck('id'));
        }

        $email = env('SAGASTOCK_ADMIN_EMAIL');
        $password = env('SAGASTOCK_ADMIN_PASSWORD');
        if ($email && $password) {
            $admin = User::firstOrCreate(
                ['email' => $email],
                ['name' => 'Administrateur PharmaCare', 'password' => $password, 'is_active' => true],
            );
            $owner = Role::where('code', 'platform_owner')->firstOrFail();
            $admin->roles()->syncWithoutDetaching([$owner->id => ['scope_type' => 'platform', 'scope_id' => null]]);
        }
    }
}
