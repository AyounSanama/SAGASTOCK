<?php

/*
 * Base de données utilisée par cette instance.
 *
 * - reelle  : base de production (valeur par défaut : un .env sans cette ligne
 *             est traité comme la base réelle, le cas le plus prudent) ;
 * - travail : copie de travail des données réelles (recette) ;
 * - demo    : base de démonstration ;
 * - test    : base des tests automatiques (SQLite en mémoire, phpunit.xml).
 *
 * Sur « reelle » et « travail » : db:seed refusé et commandes destructrices
 * (migrate:fresh, migrate:refresh, migrate:reset, db:wipe) interdites.
 */
return [
    'kind' => env('PHARMACARE_DATABASE', 'reelle'),

    // Dérogation explicite et temporaire pour lancer le seeder sur « reelle »
    // ou « travail », après validation et sauvegarde vérifiée.
    'allow_seed' => (bool) env('PHARMACARE_ALLOW_SEED', false),

    // Bandeau discret « Base de démonstration / de travail » (versions de test).
    'banner' => [
        'demo' => 'Base de démonstration',
        'travail' => 'Base de travail (copie des données réelles)',
    ],
];
