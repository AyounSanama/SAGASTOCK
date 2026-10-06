<?php

namespace Tests;

use App\Support\DatabaseGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Les tests ne tournent que sur la base isolée (SQLite en mémoire).
     *
     * Vérifié ici, avant RefreshDatabase : même avec une configuration en cache
     * (config:cache) ou une variable d'environnement qui pointerait vers la base
     * réelle ou la copie de travail, aucun test ne démarre et rien n'est migré.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (! DatabaseGuard::isIsolatedTestDatabase()) {
            $connection = $app['config']->get('database.default');
            throw new RuntimeException(sprintf(
                'Tests arrêtés : la base configurée n’est pas la base de test isolée (connexion « %s », base « %s »). '
                .'Les tests ne s’exécutent que sur SQLite en mémoire (phpunit.xml). Lancez « php artisan config:clear ».',
                $connection,
                $app['config']->get("database.connections.$connection.database"),
            ));
        }

        return $app;
    }
}
