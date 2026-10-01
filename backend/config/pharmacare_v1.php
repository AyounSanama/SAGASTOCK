<?php

return [
    // Fonctions masquées en V1 (le code est conservé).
    'features' => [
        'dark_mode' => false,
        // Rôle « Formation sanitaire » (Admin Site) masqué pour la Coordination.
        'coordination_creates_site_admin' => false,
        // Validation clinique des ordonnances : prévue en V4 (C-07).
        'clinical_validation' => false,
        // Destination de sortie « Communauté » : masquée en V1 (C-08).
        'community_dispensation' => false,
    ],
    'navigation' => [
        'coordination_admin' => ['dashboard', 'missions', 'projects', 'standard-lists', 'profile'],
        'project_admin' => ['dashboard', 'projects', 'facilities', 'users', 'standard-lists', 'profile'],
    ],
    // Entrées affichées dans le menu latéral (les autres restent accessibles
    // par onglets ; le manifeste mobile les conserve pour la navigation).
    'menu' => [
        'project_admin' => ['dashboard', 'projects', 'standard-lists', 'profile'],
    ],
    'web_paths' => [
        'coordination_admin' => ['dashboard', 'missions', 'projects', 'funding', 'standard-lists', 'profile'],
        'project_admin' => ['dashboard', 'projects', 'health-facilities', 'users', 'standard-lists', 'profile'],
    ],
    'api_patterns' => [
        'coordination_admin' => [
            '#^api/v1/(?:auth|me|profile|navigation|dashboard)(?:/|$)#',
            '#^api/v1/organizations/?$#',
            '#^api/v1/organizations/[^/]+/?$#',
            '#^api/v1/organizations/[^/]+/missions(?:/|$)#',
            '#^api/v1/missions(?:/|$)#',
            '#^api/v1/projects(?:/|$)#',
            '#^api/v1/organizations/[^/]+/projects(?:/|$)#',
            '#^api/v1/organizations/[^/]+/projects-setup(?:/|$)#',
            '#^api/v1/organizations/[^/]+/(?:donors|programs)(?:/|$)#',
            '#^api/v1/organizations/[^/]+/projects/[^/]+/(?:funding|donors|programs)(?:/|$)#',
            '#^api/v1/organizations/[^/]+/catalog/(?:lists|references|products)(?:/|$)#',
        ],
        'project_admin' => [
            '#^api/v1/(?:auth|me|profile|navigation|dashboard)(?:/|$)#',
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
