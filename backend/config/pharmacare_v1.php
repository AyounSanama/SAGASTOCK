<?php

return [
    // Fonctions masquées en V1 (le code est conservé).
    'features' => [
        // Niveau 4 : mode clair / sombre validé le 06/10.
        'dark_mode' => true,
        // Rôle « Formation sanitaire » (Admin Site) masqué pour la Coordination.
        'coordination_creates_site_admin' => false,
        // Validation clinique des ordonnances : prévue en V4 (C-07).
        'clinical_validation' => false,
        // Destination de sortie « Communauté » : masquée en V1 (C-08).
        'community_dispensation' => false,
        // Niveau 2 : ancien formulaire « Créer un projet » remplacé par l'assistant en 4 étapes.
        'legacy_project_form' => false,
    ],
    'navigation' => [
        'coordination_admin' => ['dashboard', 'missions', 'projects', 'referentials', 'standard-lists', 'synchronization', 'profile'],
        'project_admin' => ['dashboard', 'projects', 'facilities', 'users', 'standard-lists', 'synchronization', 'profile'],
    ],
    // Entrées affichées dans le menu latéral (les autres restent accessibles
    // par onglets ; le manifeste mobile les conserve pour la navigation).
    'menu' => [
        // Niveau 2 : « Configuration des projets » quitte le menu (création et
        // ouverture depuis « Ma Coordination »).
        'coordination_admin' => ['dashboard', 'missions', 'referentials', 'standard-lists', 'synchronization', 'profile'],
        'project_admin' => ['dashboard', 'projects', 'standard-lists', 'synchronization', 'profile'],
    ],
    'web_paths' => [
        'coordination_admin' => ['dashboard', 'missions', 'coordination', 'projects', 'funding', 'standard-lists', 'synchronization', 'profile'],
        'project_admin' => ['dashboard', 'projects', 'health-facilities', 'users', 'standard-lists', 'synchronization', 'profile'],
    ],
    'api_patterns' => [
        'coordination_admin' => [
            '#^api/v1/(?:auth|me|profile|navigation|dashboard)(?:/|$)#',
            // Supervision de la synchronisation et état déclaré par le téléphone.
            '#^api/v1/sync(?:/|$)#',
            '#^api/v1/organizations/?$#',
            '#^api/v1/organizations/[^/]+/?$#',
            '#^api/v1/organizations/[^/]+/missions(?:/|$)#',
            '#^api/v1/missions(?:/|$)#',
            '#^api/v1/coordination(?:/|$)#',
            '#^api/v1/projects(?:/|$)#',
            '#^api/v1/organizations/[^/]+/projects(?:/|$)#',
            '#^api/v1/organizations/[^/]+/projects-setup(?:/|$)#',
            '#^api/v1/organizations/[^/]+/(?:donors|programs)(?:/|$)#',
            '#^api/v1/organizations/[^/]+/projects/[^/]+/(?:funding|donors|programs)(?:/|$)#',
            '#^api/v1/organizations/[^/]+/catalog/(?:lists|references|products)(?:/|$)#',
        ],
        'project_admin' => [
            '#^api/v1/(?:auth|me|profile|navigation|dashboard)(?:/|$)#',
            // Niveau 5 : espace Admin Projet (tableau de bord, FOSA, Liste Standard).
            '#^api/v1/project-admin(?:/|$)#',
            // Supervision de la synchronisation et état déclaré par le téléphone.
            '#^api/v1/sync(?:/|$)#',
            '#^api/v1/organizations/?$#',
            '#^api/v1/organizations/[^/]+/?$#',
            '#^api/v1/projects(?:/|$)#',
            '#^api/v1/organizations/[^/]+/projects(?:/|$)#',
            '#^api/v1/organizations/[^/]+/catalog/(?:lists|references|products)(?:/|$)#',
            '#^api/v1/organizations/[^/]+/structures(?:/|$)#',
            '#^api/v1/organizations/[^/]+/facilities(?:/|$)#',
            '#^api/v1/(?:users|users-archived|assignable-roles|roles)(?:/|$)#',
        ],
    ],
];
