<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Organization;
use App\Models\Site;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Transfer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedgerService
{
    private const POSITIVE = ['opening', 'receipt', 'entry', 'return_in', 'transfer_in', 'adjustment_in', 'quarantine_release'];

    private const NEGATIVE = ['issue', 'return_out', 'transfer_out', 'adjustment_out', 'loss', 'damage', 'expiry', 'quarantine', 'destruction'];

    public function record(Organization $organization, Site $site, Batch $batch, string $type, float|string $quantity, ?int $userId, array $context = []): StockMovement
    {
        abort_unless(in_array($type, [...self::POSITIVE, ...self::NEGATIVE], true), 422, 'Type de mouvement invalide.');
        abort_unless($site->healthFacility()->where('organization_id', $organization->id)->exists(), 422, 'Site hors organisation.');
        abort_unless($batch->organization_id === $organization->id, 422, 'Lot hors organisation.');
        $absolute = abs((float) $quantity);
        if ($absolute <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité doit être supérieure à zéro.']);
        }
        $signed = in_array($type, self::NEGATIVE, true) ? -$absolute : $absolute;

        return DB::transaction(function () use ($organization, $site, $batch, $type, $signed, $userId, $context) {
            $balance = StockBalance::where('site_id', $site->id)->where('batch_id', $batch->id)->lockForUpdate()->first();
            $balance ??= new StockBalance([
                'organization_id' => $organization->id,
                'site_id' => $site->id,
                'product_id' => $batch->product_id,
                'batch_id' => $batch->id,
                'theoretical_quantity' => 0,
                'reserved_quantity' => 0,
            ]);
            $newQuantity = (float) $balance->theoretical_quantity + $signed;
            if ($newQuantity < 0) {
                throw ValidationException::withMessages(['quantity' => 'Stock insuffisant sur ce site et ce lot.']);
            }
            $movement = StockMovement::create([
                'organization_id' => $organization->id,
                'site_id' => $site->id,
                'product_id' => $batch->product_id,
                'batch_id' => $batch->id,
                'movement_type' => $type,
                'quantity' => $signed,
                'unit_cost' => $context['unit_cost'] ?? $batch->unit_cost,
                'currency' => strtoupper($context['currency'] ?? $batch->currency ?? ''),
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'client_reference' => $context['client_reference'] ?? null,
                'compensates_movement_id' => $context['compensates_movement_id'] ?? null,
                'reason' => $context['reason'] ?? null,
                'status' => 'validated',
                'created_by' => $userId,
                'validated_by' => $userId,
                'validated_at' => now(),
            ]);
            $balance->theoretical_quantity = $newQuantity;
            $balance->last_movement_at = $movement->validated_at;
            $balance->save();

            return $movement->load(['site', 'product', 'batch']);
        }, 3);
    }

    public function compensate(StockMovement $movement, string $reason, int $userId): StockMovement
    {
        abort_unless($movement->status === 'validated', 422);
        abort_if(StockMovement::where('compensates_movement_id', $movement->id)->exists(), 422, 'Ce mouvement a déjà été compensé.');
        $type = (float) $movement->quantity >= 0 ? 'adjustment_out' : 'adjustment_in';

        return $this->record($movement->organization, $movement->site, $movement->batch, $type, abs((float) $movement->quantity), $userId, [
            'reason' => $reason,
            'compensates_movement_id' => $movement->id,
            'unit_cost' => $movement->unit_cost,
            'currency' => $movement->currency,
        ]);
    }

    public function fefo(Organization $organization, Site $site, string $productId, float $requested): Collection
    {
        $remaining = $requested;

        return StockBalance::query()->with('batch')->where('stock_balances.organization_id', $organization->id)
            ->where('stock_balances.site_id', $site->id)->where('stock_balances.product_id', $productId)
            ->whereRaw('(stock_balances.theoretical_quantity - stock_balances.reserved_quantity) > 0')
            ->whereHas('batch', fn ($q) => $q->where('status', 'available')->whereDate('expires_on', '>=', today()))
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->orderBy('batches.expires_on')->orderBy('batches.batch_number')
            ->select('stock_balances.*')->get()->map(function (StockBalance $balance) use (&$remaining) {
                $available = (float) $balance->available_quantity;
                $suggested = min($available, max(0, $remaining));
                $remaining -= $suggested;

                return ['batch' => $balance->batch, 'available_quantity' => $available, 'suggested_quantity' => $suggested];
            })->filter(fn ($row) => $row['suggested_quantity'] > 0)->values();
    }

    public function dispatchTransfer(Transfer $transfer, int $userId): Transfer
    {
        abort_unless($transfer->status === 'draft' && $transfer->items()->exists(), 422);
        DB::transaction(function () use ($transfer, $userId) {
            foreach ($transfer->items as $item) {
                $this->record($transfer->organization, $transfer->sourceSite, $item->batch, 'transfer_out', $item->quantity_sent, $userId, [
                    'reference_type' => 'transfer', 'reference_id' => $transfer->id, 'reason' => 'Expédition '.$transfer->reference,
                ]);
            }
            $transfer->update(['status' => 'in_transit', 'dispatched_by' => $userId, 'dispatched_at' => now()]);
        });

        return $transfer->fresh('items');
    }

    public function receiveTransfer(Transfer $transfer, array $received, int $userId): Transfer
    {
        abort_unless($transfer->status === 'in_transit', 422);
        DB::transaction(function () use ($transfer, $received, $userId) {
            foreach ($transfer->items as $item) {
                $quantity = (float) ($received[$item->id]['quantity_received'] ?? $item->quantity_sent);
                abort_unless($quantity >= 0 && $quantity <= (float) $item->quantity_sent, 422, 'Quantité reçue invalide.');
                $item->update(['quantity_received' => $quantity, 'discrepancy_reason' => $received[$item->id]['discrepancy_reason'] ?? null]);
                if ($quantity > 0) {
                    $this->record($transfer->organization, $transfer->destinationSite, $item->batch, 'transfer_in', $quantity, $userId, [
                        'reference_type' => 'transfer', 'reference_id' => $transfer->id, 'reason' => 'Réception '.$transfer->reference,
                    ]);
                }
            }
            $transfer->update(['status' => 'received', 'received_by' => $userId, 'received_at' => now()]);
        });

        return $transfer->fresh('items');
    }
}
