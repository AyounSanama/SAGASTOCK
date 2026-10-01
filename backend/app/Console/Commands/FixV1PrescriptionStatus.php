<?php

namespace App\Console\Commands;

use App\Models\Prescription;
use Illuminate\Console\Command;

/**
 * Corrige les ordonnances créées « validées » pendant le contournement V1
 * (validation clinique masquée) : elles passent au statut distinct
 * « validation non requise (V1) ». Une validation pharmaceutique réelle a
 * toujours un validateur et une date : ces ordonnances-là ne sont pas touchées.
 */
class FixV1PrescriptionStatus extends Command
{
    protected $signature = 'pharmacare:prescriptions:fix-v1-status {--dry-run : Compter sans rien modifier}';

    protected $description = 'Passe au statut « validation non requise (V1) » les ordonnances « validées » sans validateur.';

    public function handle(): int
    {
        $query = Prescription::query()
            ->where('status', 'validated')
            ->whereNull('validated_by')
            ->whereNull('validated_at');
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("$count ordonnance(s) concernée(s) — aucune modification (--dry-run).");

            return self::SUCCESS;
        }

        $updated = $query->update(['status' => Prescription::STATUS_VALIDATION_NOT_REQUIRED]);
        $this->info("$updated ordonnance(s) passée(s) au statut « validation non requise (V1) ».");

        return self::SUCCESS;
    }
}
