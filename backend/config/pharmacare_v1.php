<?php

return [
    'navigation' => [
        'coordination_admin' => ['dashboard', 'missions', 'projects', 'standard-lists', 'profile'],
        'project_admin' => ['dashboard', 'projects', 'standard-lists', 'profile'],
    ],
    'web_paths' => [
        'coordination_admin' => ['dashboard', 'missions', 'projects', 'funding', 'standard-lists', 'profile'],
        'project_admin' => ['dashboard', 'projects', 'standard-lists', 'profile'],
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
        ],
    ],
];
