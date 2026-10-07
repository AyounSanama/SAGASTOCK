<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\Site;
use App\Services\StockOriginService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Niveau 7 — Origine (couple ONG/Bailleur) du stock existant.
 *
 * Par défaut : liste seulement ce qui serait fait, sans rien modifier.
 * Avec --apply : applique les cas sûrs (la FOSA n'a qu'un seul projet).
 * Les cas ambigus (FOSA avec plusieurs projets, lot présent dans des FOSA de
 * projets différents) sont listés et jamais devinés.
 */
class AssignStockOrigins extends Command
{
    protected $signature = 'pharmacare:stock-origins {--apply : Appliquer les origines sûres (sinon : liste seulement)}';

    protected $description = 'Déduit l’origine (couple ONG/Bailleur) du stock et des entrées existants à partir du projet de la FOSA.';

    public function handle(StockOriginService $origins): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Application des origines sûres.' : 'Simulation : aucune donnée ne sera modifiée (ajoutez --apply pour appliquer).');

        // Projets de chaque site (via sa FOSA).
        $projectsBySite = Site::with('healthFacility.projects:id,code')->get()
            ->mapWithKeys(fn (Site $site) => [$site->id => $site->healthFacility?->projects->pluck('id') ?? collect()]);
        $single = fn (Collection $siteIds) => $siteIds->map(fn ($id) => $projectsBySite[$id] ?? collect())
            ->reduce(fn (?Collection $carry, Collection $ids) => $carry === null ? $ids : $carry->intersect($ids));

        $batchRows = [];
        $ambiguous = [];
        $batches = Batch::where('origin_key', '')->with('product:id,code,name')->get();
        foreach ($batches as $batch) {
            $siteIds = DB::table('stock_balances')->where('batch_id', $batch->id)->pluck('site_id')->unique();
            $siteIds = $siteIds->merge(Receipt::whereHas('items', fn ($q) => $q->where('batch_id', $batch->id))->pluck('site_id'))->unique()->values();
            $candidates = $siteIds->isEmpty() ? collect() : ($single($siteIds) ?? collect())->values();
            $label = ($batch->product?->code ?? '?').' · lot '.$batch->batch_number;
            if ($candidates->count() !== 1) {
                $ambiguous[] = [$label, $siteIds->isEmpty() ? 'Lot sans stock ni entrée' : ($candidates->isEmpty() ? 'Aucun projet commun aux FOSA concernées' : 'Plusieurs projets possibles')];

                continue;
            }
            $projectId = $candidates->first();
            $key = 'project:'.$projectId;
            if (Batch::where('organization_id', $batch->organization_id)->where('product_id', $batch->product_id)
                ->where('batch_number', $batch->batch_number)->where('origin_key', $key)->exists()) {
                $ambiguous[] = [$label, 'Un lot de même numéro existe déjà pour ce couple : fusion à décider'];

                continue;
            }
            $batchRows[] = [$label, $this->projectLabel($origins, $projectId)];
            if ($apply) {
                $batch->update(['origin_type' => StockOriginService::TYPE_PROJECT, 'origin_project_id' => $projectId, 'origin_key' => $key]);
            }
        }

        $receiptRows = [];
        foreach (Receipt::whereNull('origin_type')->get(['id', 'reference', 'site_id']) as $receipt) {
            $projects = $projectsBySite[$receipt->site_id] ?? collect();
            if ($projects->count() !== 1) {
                $ambiguous[] = ['Entrée '.$receipt->reference, $projects->isEmpty() ? 'FOSA sans projet' : 'Plusieurs projets possibles'];

                continue;
            }
            $receiptRows[] = ['Entrée '.$receipt->reference, $this->projectLabel($origins, $projects->first())];
            if ($apply) {
                $receipt->update(['origin_type' => StockOriginService::TYPE_PROJECT, 'origin_project_id' => $projects->first()]);
            }
        }

        $rows = [...$batchRows, ...$receiptRows];
        $this->line(count($rows).' origine(s) '.($apply ? 'appliquée(s)' : 'à appliquer').' :');
        if ($rows !== []) {
            $this->table(['Lot / entrée', 'Couple ONG/Bailleur'], $rows);
        }
        $this->line(count($ambiguous).' cas à décider (non modifiés) :');
        if ($ambiguous !== []) {
            $this->table(['Lot / entrée', 'Raison'], $ambiguous);
        }

        return self::SUCCESS;
    }

    private function projectLabel(StockOriginService $origins, string $projectId): string
    {
        return $origins->label(Project::with(['organization:id,name', 'donors:id,name'])->find($projectId));
    }
}
