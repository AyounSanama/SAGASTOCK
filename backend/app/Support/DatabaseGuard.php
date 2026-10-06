<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Garde-fous sur la base utilisée (config/pharmacare_database.php).
 *
 * Les données réelles et leur copie de travail ne sont jamais réinitialisées
 * ni re-seedées par accident ; les tests ne tournent que sur SQLite en mémoire.
 */
final class DatabaseGuard
{
    public const PROTECTED_KINDS = ['reelle', 'travail'];

    public static function kind(): string
    {
        return (string) config('pharmacare_database.kind', 'reelle');
    }

    public static function isProtected(): bool
    {
        return in_array(self::kind(), self::PROTECTED_KINDS, true);
    }

    /** Vrai seulement pour la base des tests : SQLite en mémoire. */
    public static function isIsolatedTestDatabase(): bool
    {
        $connection = config('database.default');

        return config("database.connections.$connection.driver") === 'sqlite'
            && config("database.connections.$connection.database") === ':memory:';
    }

    /** @throws RuntimeException sur la base réelle ou la copie de travail sans dérogation. */
    public static function assertSeedingAllowed(): void
    {
        if (self::isIsolatedTestDatabase()) {
            return;
        }
        if (self::isProtected() && ! config('pharmacare_database.allow_seed')) {
            throw new RuntimeException(sprintf(
                'db:seed refusé : la base configurée est « %s » (PHARMACARE_DATABASE). '
                .'Le seeder ne s’exécute que sur la base de démonstration ou de test. '
                .'Dérogation : PHARMACARE_ALLOW_SEED=true, après validation et sauvegarde vérifiée.',
                self::kind(),
            ));
        }
    }

    /** migrate:fresh, migrate:refresh, migrate:reset et db:wipe interdits sur les bases protégées. */
    public static function prohibitDestructiveCommands(): void
    {
        DB::prohibitDestructiveCommands(self::isProtected() && ! self::isIsolatedTestDatabase());
    }
}
