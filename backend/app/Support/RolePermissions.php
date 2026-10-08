<?php

namespace App\Support;

/**
 * Définition UNIQUE des permissions et des rôles système (seeder et tests).
 *
 * Remplace l'ancienne double définition (roleDefinitions puis strictMatrix) :
 * les listes ci-dessous sont les permissions effectivement appliquées jusqu'ici
 * (celles de strictMatrix, appliquées en dernier). Toute évolution passe par la
 * matrice validée (docs/ameliorations/11-matrice-roles-permissions.md).
 */
final class RolePermissions
{
    public const PERMISSIONS = [
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
        'activity_logs.view' => 'Consulter le journal d’activité',
        'audit.view' => 'Consulter le journal d’audit',
        'project.view' => 'Consulter le projet courant',
        'health_facilities.view' => 'Consulter les formations sanitaires',
        'health_facilities.manage' => 'Gérer les formations sanitaires',
        'dispensing_sites.view' => 'Consulter les sites de dispensation',
        'dispensing_sites.manage' => 'Gérer les sites de dispensation',
        'users.create_site_admin' => 'Créer un Admin Site',
        'users.update_site_admin' => 'Modifier un Admin Site',
        'users.suspend_site_admin' => 'Suspendre ou réactiver un Admin Site',
        'receipts.view' => 'Consulter les réceptions',
        'orders.prepare' => 'Préparer les commandes',
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
        'project_settings.view' => 'Consulter les paramètres du projet',
        'site_settings.view' => 'Consulter les paramètres du site',
        'activity_logs.view_local' => 'Consulter le journal local',
    ];

    private const SAGO_ADMIN = [
        'dashboard.view', 'configuration.view', 'configuration.platform.manage',
        'organizations.view', 'organizations.manage', 'platform_standards.view',
        'platform_standards.manage', 'standards.assign', 'audit.view', 'activity_logs.view',
    ];

    /** code => [libellé, type de périmètre, permissions]. */
    public const ROLES = [
        'sago_admin' => ['Admin Sago', 'platform', self::SAGO_ADMIN],
        // Alias historique : mêmes droits que sago_admin (plus de users.manage ni roles.manage).
        'owner' => ['Admin Sago (alias historique)', 'platform', self::SAGO_ADMIN],
        'coordination_admin' => ['Admin Coordination', 'organization', [
            'dashboard.view', 'missions.view', 'missions.manage', 'standard_lists.view', 'catalog.view',
            'products.view', 'stocks.view', 'receipts.view', 'receipts.manage', 'dispensing.view',
            'dispensing.manage', 'patients.view', 'patients.manage', 'prescriptions.view',
            'prescriptions.manage', 'prescriptions.validate', 'dispensations.view', 'dispensations.manage',
            'inventories.view', 'inventories.manage', 'inventories.validate', 'orders.view', 'orders.manage',
            'orders.approve', 'reports.view', 'reports.export', 'synchronization.view', 'users.view',
            'users.manage', 'activity_logs.view', 'projects.view', 'projects.manage', 'funding.view',
            'funding.manage', 'standard_lists.manage', 'health_facilities.view', 'health_facilities.manage',
            'dispensing_sites.view', 'dispensing_sites.manage',
        ]],
        'project_admin' => ['Admin Projet', 'project', [
            'dashboard.view', 'standard_lists.view', 'catalog.view', 'products.view', 'products.manage',
            'stocks.view', 'receipts.view', 'receipts.manage', 'dispensing.view', 'dispensing.manage',
            'patients.view', 'patients.manage', 'prescriptions.view', 'prescriptions.manage',
            'prescriptions.validate', 'dispensations.view', 'dispensations.manage', 'inventories.view',
            'inventories.manage', 'orders.view', 'orders.manage', 'reports.view', 'reports.export',
            'synchronization.view', 'health_facilities.view', 'health_facilities.manage',
            'dispensing_sites.view', 'dispensing_sites.manage', 'users.view', 'users.create_site_admin',
            'users.update_site_admin', 'users.suspend_site_admin', 'project_settings.view',
            'activity_logs.view', 'projects.view',
        ]],
        'site_admin' => ['Admin Site de Dispensation', 'site', [
            'dashboard.view', 'standard_lists.view', 'catalog.view', 'products.view', 'stocks.view',
            'stocks.manage', 'receipts.view', 'receipts.manage', 'dispensing.view', 'dispensing.manage',
            'patients.view', 'patients.manage', 'prescriptions.view', 'prescriptions.manage',
            'dispensations.view', 'dispensations.manage', 'inventories.view', 'inventories.manage',
            'orders.view', 'orders.manage', 'orders.prepare', 'reports.view', 'reports.export_local',
            'synchronization.view', 'synchronization.manage', 'site_settings.view', 'activity_logs.view_local',
        ]],
        // Niveau 7 (cahier §3) : l'Utilisateur Site saisit les entrées en stock et les inventaires.
        'site_user' => ['Utilisateur du Site', 'site', [
            'dashboard.view', 'standard_lists.view', 'catalog.view', 'products.view', 'stocks.view',
            'receipts.view', 'receipts.manage', 'dispensing.view', 'patients.view', 'prescriptions.view', 'prescriptions.manage',
            'dispensations.view', 'dispensations.manage', 'inventories.view', 'inventories.manage', 'orders.view', 'reports.view',
            'synchronization.view', 'activity_logs.view_local',
        ]],
    ];

    /**
     * Profils sans rôle propre : un compte du rôle de base portant l'indicateur
     * `read_only` ne garde que les permissions de consultation (User::isReadPermission).
     */
    public const PROFILES = [
        'coordination_read_only' => ['Coordination (lecture seule)', 'coordination_admin'],
    ];

    /** @return list<string> */
    public static function permissionsFor(string $role): array
    {
        return self::ROLES[$role][2];
    }

    /** @return list<string> Permissions effectives d'un profil (ex. Coordination en lecture seule). */
    public static function profilePermissions(string $profile): array
    {
        return array_values(array_filter(
            self::permissionsFor(self::PROFILES[$profile][1]),
            fn (string $permission) => \App\Models\User::isReadPermission($permission),
        ));
    }
}
