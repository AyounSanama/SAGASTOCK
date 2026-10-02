<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * S-05 — Anonymise les entrées EXISTANTES du journal qui concernent des données
 * de santé : seules les listes de champs modifiés sont conservées, jamais les
 * valeurs. L'événement, l'auteur, la date et la cible restent (traçabilité).
 * À lancer sur la base réelle uniquement après confirmation de la sauvegarde.
 */
class AnonymizeHealthAuditLogs extends Command
{
    protected $signature = 'pharmacare:audit:anonymize-health {--dry-run : Compter sans rien modifier}';

    protected $description = 'Retire les valeurs de santé des entrées existantes du journal d’audit (S-05).';

    public function handle(): int
    {
        $query = AuditLog::query()
            ->where(function ($q): void {
                $q->whereIn('auditable_type', AuditLog::HEALTH_SUBJECTS);
                foreach (AuditLog::HEALTH_EVENT_PREFIXES as $prefix) {
                    $q->orWhere('event', 'like', $prefix.'%');
                }
            })
            ->where(fn ($q) => $q->whereNotNull('old_values')->orWhereNotNull('new_values'));

        $count = (clone $query)->count();
        $this->info("Entrées contenant des valeurs de santé : {$count}");
        if ($this->option('dry-run') || $count === 0) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($query): void {
            $query->chunkById(200, function ($logs): void {
                foreach ($logs as $log) {
                    $fields = array_values(array_unique([
                        ...array_keys($log->old_values ?? []),
                        ...array_keys($log->new_values ?? []),
                    ]));
                    // Une entrée déjà anonymisée ne garde que la liste des champs.
                    if (array_keys($log->new_values ?? []) === ['champs_modifies'] && $log->old_values === null) {
                        continue;
                    }
                    $log->forceFill([
                        'old_values' => null,
                        'new_values' => $fields === [] ? null : ['champs_modifies' => $fields],
                    ])->saveQuietly();
                }
            });
        });
        $this->info('Anonymisation terminée.');

        return self::SUCCESS;
    }
}
