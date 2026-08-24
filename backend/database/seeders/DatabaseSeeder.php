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
            'dashboard.view' => 'Consulter le tableau de bord',
            'organizations.view' => 'Consulter les organisations',
            'organizations.manage' => 'Gérer les organisations',
            'configuration.view' => 'Accéder à la configuration métier',
            'configuration.platform.manage' => 'Gérer la configuration plateforme',
            'configuration.organization.manage' => 'Gérer la configuration organisation',
            'configuration.project.manage' => 'Gérer la configuration projet',
            'configuration.site.manage' => 'Gérer la configuration site',
            'missions.view' => 'Consulter les pays et missions',
            'missions.manage' => 'Gérer les pays et missions',
            'projects.view' => 'Consulter les projets',
            'projects.manage' => 'Gérer les projets',
            'sites.view' => 'Consulter les sites de dispensation',
            'sites.manage' => 'Gérer les sites de dispensation',
            'standard_lists.view' => 'Consulter les listes standards',
            'standard_lists.manage' => 'Gérer les listes standards',
            'products.view' => 'Consulter les produits',
            'products.manage' => 'Gérer les produits',
            'funding.view' => 'Consulter les bailleurs et programmes',
            'funding.manage' => 'Gérer les bailleurs et programmes',
            'structures.view' => 'Consulter les formations sanitaires et sites',
            'structures.manage' => 'Gérer les formations sanitaires et sites',
            'modules.manage' => 'Activer les modules par périmètre',
            'catalog.view' => 'Consulter les référentiels et produits',
            'catalog.manage' => 'Gérer les référentiels et produits',
            'catalog.publish' => 'Publier les listes standards',
            'standards.assign' => 'Affecter les listes standards',
            'platform_standards.view' => 'Consulter les standards et référentiels plateforme',
            'platform_standards.manage' => 'Gérer les standards et référentiels plateforme',
            'batches.manage' => 'Gérer les lots produits',
            'stocks.view' => 'Consulter les stocks et mouvements',
            'stocks.manage' => 'Enregistrer les mouvements de stock',
            'stocks.adjust' => 'Valider les ajustements, pertes et quarantaines',
            'transfers.manage' => 'Gérer les transferts de stock',
            'receipts.manage' => 'Enregistrer et valider les réceptions',
            'dispensing.view' => 'Consulter les dispensations',
            'dispensing.manage' => 'Enregistrer les dispensations',
            'inventories.view' => 'Consulter les inventaires',
            'inventories.manage' => 'Gérer les inventaires',
            'orders.view' => 'Consulter les commandes',
            'orders.manage' => 'Gérer les commandes',
            'reports.view' => 'Consulter les rapports',
            'reports.export' => 'Exporter les rapports',
            'synchronization.view' => 'Consulter la synchronisation',
            'settings.view' => 'Consulter les paramètres',
            'settings.manage' => 'Gérer les paramètres globaux',
            'project_settings.manage' => 'Gérer les paramètres du projet',
            'synchronization.manage' => 'Gérer les synchronisations',
            'users.view' => 'Consulter les utilisateurs',
            'users.manage' => 'Gérer les utilisateurs',
            'roles.manage' => 'Gérer les rôles',
            'activity_logs.view' => "Consulter le journal d'activité",
            'audit.view' => "Consulter le journal d'audit",
            'project.view' => 'Consulter le projet courant',
            'health_facilities.view' => 'Consulter les formations sanitaires',
            'health_facilities.manage' => 'Gerer les formations sanitaires',
            'dispensing_sites.view' => 'Consulter les sites de dispensation',
            'dispensing_sites.manage' => 'Gerer les sites de dispensation',
            'users.create_site_admin' => 'Creer un Admin Site',
            'users.update_site_admin' => 'Modifier un Admin Site',
            'users.suspend_site_admin' => 'Suspendre ou reactiver un Admin Site',
            'receipts.view' => 'Consulter les receptions',
            'orders.prepare' => 'Preparer les commandes',
            'orders.approve' => 'Approuver les commandes',
            'inventories.validate' => 'Valider les inventaires',
            'patients.view' => 'Consulter les patients',
            'patients.manage' => 'Gérer les patients',
            'prescriptions.view' => 'Consulter les ordonnances',
            'prescriptions.manage' => 'Gérer les ordonnances',
            'prescriptions.validate' => 'Valider les ordonnances',
            'dispensations.view' => 'Consulter les dispensations cliniques',
            'dispensations.manage' => 'Gérer les dispensations cliniques',
            'reports.export_local' => 'Exporter les rapports locaux',
            'project_settings.view' => 'Consulter les parametres du projet',
            'site_settings.view' => 'Consulter les parametres du site',
            'activity_logs.view_local' => 'Consulter le journal local',
        ];
        $permissions = collect($permissionNames)->map(
            fn($name, $code) => Permission::updateOrCreate(['code' => $code], ['name' => $name]),
        );

        $roleDefinitions = [
            'sago_admin' => ['Admin Sago', [
                'dashboard.view', 'configuration.view', 'configuration.platform.manage',
                'organizations.view', 'organizations.manage', 'platform_standards.view',
                'platform_standards.manage', 'standards.assign',
                'audit.view', 'activity_logs.view',
            ], 'platform'],
            'owner' => ['Admin Sago (alias historique)', [
                'dashboard.view', 'configuration.view', 'configuration.platform.manage',
                'organizations.view', 'organizations.manage', 'missions.view', 'missions.manage',
                'projects.view', 'standard_lists.view', 'standard_lists.manage', 'standards.assign',
                'catalog.publish', 'users.view', 'users.manage', 'roles.manage', 'audit.view',
                'activity_logs.view', 'settings.view',
            ], 'platform'],
            'coordination_admin' => ['Admin Coordination', [
                'dashboard.view',
                'configuration.view',
                'configuration.platform.manage',
                'configuration.organization.manage',
                'organizations.view',
                'missions.view',
                'missions.manage',
                'projects.view',
                'projects.manage',
                'funding.view',
                'funding.manage',
                'structures.view',
                'modules.manage',
                'catalog.view',
                'catalog.manage',
                'catalog.publish',
                'reports.view',
                'settings.view',
                'settings.manage',
                'users.view',
                'users.manage',
                'audit.view',
                'activity_logs.view',
            ], 'organization'],
            'project_admin' => ['Admin Projet', [
                'dashboard.view',
                'organizations.view',
                'missions.view',
                'projects.view',
                'projects.manage',
                'structures.view',
                'structures.manage',
                'catalog.view',
                'standard_lists.view',
                'standard_lists.manage',
                'products.view',
                'products.manage',
                'sites.view',
                'sites.manage',
                'orders.view',
                'orders.manage',
                'stocks.view',
                'reports.view',
                'project_settings.manage',
                'users.view',
                'users.manage',
                'audit.view',
                'activity_logs.view',
            ], 'project'],
            'site_admin' => ['Admin Site de Dispensation', [
                'dashboard.view',
                'organizations.view',
                'projects.view',
                'structures.view',
                'sites.view',
                'sites.manage',
                'catalog.view',
                'batches.manage',
                'stocks.view',
                'stocks.manage',
                'stock.adjust',
                'transfers.manage',
                'receipts.manage',
                'dispensing.view',
                'dispensing.manage',
                'inventories.view',
                'inventories.manage',
                'orders.view',
                'orders.manage',
                'reports.view',
                'synchronization.view',
                'synchronization.manage',
                'users.view',
                'users.manage',
                'audit.view',
                'activity_logs.view',
            ], 'site'],
            'site_user' => ['Utilisateur du Site', [
                'dashboard.view',
                'catalog.view',
                'stocks.view',
                'dispensing.view',
                'reports.view',
                'activity_logs.view',
            ], 'site'],
        ];

        foreach ($roleDefinitions as $code => [$name, $codes, $scopeType]) {
            $role = Role::updateOrCreate(['code' => $code], [
                'name' => $name,
                'is_system' => true,
                'is_active' => true,
                'scope_type' => $scopeType,
                'scope_id' => null,
            ]);
            $role->permissions()->sync($permissions->only($codes)->pluck('id'));
        }

        $strictMatrix = [
            'sago_admin' => ['dashboard.view','configuration.view','configuration.platform.manage','organizations.view','organizations.manage','platform_standards.view','platform_standards.manage','standards.assign','audit.view','activity_logs.view'],
            'coordination_admin' => ['dashboard.view','missions.view','missions.manage','standard_lists.view','catalog.view','products.view','stocks.view','receipts.view','receipts.manage','dispensing.view','dispensing.manage','patients.view','patients.manage','prescriptions.view','prescriptions.manage','prescriptions.validate','dispensations.view','dispensations.manage','inventories.view','inventories.manage','inventories.validate','orders.view','orders.manage','orders.approve','reports.view','reports.export','synchronization.view','users.view','users.manage','activity_logs.view'],
            'project_admin' => ['dashboard.view','project.view','standard_lists.view','catalog.view','products.view','products.manage','stocks.view','receipts.view','receipts.manage','dispensing.view','dispensing.manage','patients.view','patients.manage','prescriptions.view','prescriptions.manage','prescriptions.validate','dispensations.view','dispensations.manage','inventories.view','inventories.manage','orders.view','orders.manage','reports.view','reports.export','synchronization.view','health_facilities.view','health_facilities.manage','dispensing_sites.view','dispensing_sites.manage','users.view','users.create_site_admin','users.update_site_admin','users.suspend_site_admin','project_settings.view','activity_logs.view'],
            'site_admin' => ['dashboard.view','standard_lists.view','catalog.view','products.view','stocks.view','stocks.manage','receipts.view','receipts.manage','dispensing.view','dispensing.manage','patients.view','patients.manage','prescriptions.view','prescriptions.manage','dispensations.view','dispensations.manage','inventories.view','inventories.manage','orders.view','orders.manage','orders.prepare','reports.view','reports.export_local','synchronization.view','synchronization.manage','site_settings.view','activity_logs.view_local'],
            'site_user' => ['dashboard.view','standard_lists.view','catalog.view','products.view','stocks.view','receipts.view','dispensing.view','patients.view','prescriptions.view','prescriptions.manage','dispensations.view','dispensations.manage','inventories.view','orders.view','reports.view','synchronization.view','activity_logs.view_local'],
        ];
        $strictMatrix['coordination_admin'] = array_values(array_unique([
            ...$strictMatrix['coordination_admin'],
            'projects.view',
            'projects.manage',
            'health_facilities.view',
            'health_facilities.manage',
            'dispensing_sites.view',
            'dispensing_sites.manage',
        ]));
        $strictMatrix['project_admin'] = array_values(array_unique([
            ...array_diff($strictMatrix['project_admin'], ['project.view']),
            'projects.view',
        ]));
        foreach ($strictMatrix as $roleCode => $codes) {
            Role::where('code', $roleCode)->first()?->permissions()
                ->sync(
                    $permissions->only($codes)->pluck('id'),
                );
        }

        Role::where('is_system', true)
            ->whereNotIn('code', array_keys($roleDefinitions))
            ->get()
            ->each(function (Role $role): void {
                $role->users()->detach();
                $role->permissions()->detach();
                $role->delete();
            });

        $email = env('SAGASTOCK_ADMIN_EMAIL');
        $password = env('SAGASTOCK_ADMIN_PASSWORD');
        if ($email && $password) {
            $admin = User::firstOrCreate(
                ['email' => $email],
                ['name' => 'Administrateur PharmaCare', 'password' => $password, 'is_active' => true],
            );
            $owner = Role::where('code', 'sago_admin')->firstOrFail();
            $admin->roles()->syncWithoutDetaching([
                $owner->id => ['scope_type' => 'platform', 'scope_id' => null],
            ]);
        }
    }
}
