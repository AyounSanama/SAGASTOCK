<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Site;
use App\Models\StockBalance;
use App\Models\StockHold;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Services\AuditService;
use App\Services\ModuleActivationService;
use App\Services\StockLedgerService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function __construct(
        private StockLedgerService $ledger,
        private UserScopeService $scopes,
        private ModuleActivationService $modules,
        private AuditService $audit,
    ) {}

    public function balances(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'stocks.view');
        $query = StockBalance::with(['site.healthFacility', 'product', 'batch'])
            ->where('organization_id', $organization->id)->whereIn('site_id', $this->siteIds($request, $organization))
            ->when($request->input('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->input('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->boolean('available_only'), fn ($q) => $q->whereRaw('(theoretical_quantity - reserved_quantity) > 0'))
            ->orderByDesc('last_movement_at');

        return response()->json($query->paginate(50));
    }

    public function movements(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'stocks.view');

        return response()->json(StockMovement::with(['site', 'product', 'batch'])
            ->where('organization_id', $organization->id)->whereIn('site_id', $this->siteIds($request, $organization))
            ->when($request->input('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->input('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->input('movement_type'), fn ($q, $type) => $q->where('movement_type', $type))
            ->latest('validated_at')->paginate(50));
    }

    public function options(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'stocks.manage');

        return response()->json([
            'sites' => Site::with('healthFacility:id,name')->whereIn('id', $this->siteIds($request, $organization))->orderBy('name')->get(),
            'batches' => $organization->batches()->with('product:id,code,name')
                ->whereIn('status', ['available', 'quarantine'])->orderBy('expires_on')->get(),
        ]);
    }

    public function storeMovement(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'stocks.manage');
        $data = $request->validate([
            'site_id' => ['required', 'uuid'], 'batch_id' => ['required', 'uuid'],
            'movement_type' => ['required', Rule::in(['opening', 'receipt', 'entry', 'issue', 'return_in', 'return_out', 'adjustment_in', 'adjustment_out', 'loss', 'damage', 'expiry', 'quarantine', 'quarantine_release', 'destruction'])],
            'quantity' => ['required', 'numeric', 'gt:0'], 'reason' => ['nullable', 'string', 'max:2000'],
            'client_reference' => ['nullable', 'uuid'],
        ]);
        if (! empty($data['client_reference'])) {
            $existing = StockMovement::where('organization_id', $organization->id)
                ->where('client_reference', $data['client_reference'])
                ->first();
            if ($existing) {
                return response()->json(['movement' => $existing->load(['site', 'product', 'batch']), 'duplicate' => true]);
            }
        }
        $site = $this->site($request, $organization, $data['site_id']);
        $batch = $organization->batches()->findOrFail($data['batch_id']);
        if (in_array($data['movement_type'], ['adjustment_in', 'adjustment_out', 'loss', 'damage', 'expiry', 'quarantine', 'destruction'], true)) {
            abort_unless(filled($data['reason'] ?? null), 422, 'Une justification est obligatoire.');
        }
        $movement = $this->ledger->record($organization, $site, $batch, $data['movement_type'], $data['quantity'], $request->user()->id, [
            'reason' => $data['reason'] ?? null,
            'client_reference' => $data['client_reference'] ?? null,
        ]);
        $this->audit->record($request, 'stock.movement.validated', $movement, [], $movement->toArray());

        return response()->json(['movement' => $movement], 201);
    }

    public function compensate(Request $request, Organization $organization, StockMovement $movement): JsonResponse
    {
        $this->access($request, $organization, 'stocks.adjust');
        abort_unless($movement->organization_id === $organization->id && $this->siteIds($request, $organization)->contains($movement->site_id), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $compensation = $this->ledger->compensate($movement, $data['reason'], $request->user()->id);
        $this->audit->record($request, 'stock.movement.compensated', $compensation, [], ['original_id' => $movement->id, 'reason' => $data['reason']]);

        return response()->json(['movement' => $compensation], 201);
    }

    public function fefo(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'stocks.view');
        $data = $request->validate(['site_id' => ['required', 'uuid'], 'product_id' => ['required', 'uuid'], 'quantity' => ['required', 'numeric', 'gt:0']]);
        $site = $this->site($request, $organization, $data['site_id']);
        abort_unless($organization->products()->whereKey($data['product_id'])->exists(), 422);
        $suggestions = $this->ledger->fefo($organization, $site, $data['product_id'], (float) $data['quantity']);

        return response()->json(['requested_quantity' => (float) $data['quantity'], 'suggested_quantity' => $suggestions->sum('suggested_quantity'), 'allocations' => $suggestions]);
    }

    public function transfers(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'stocks.view');

        return response()->json(Transfer::with(['sourceSite', 'destinationSite', 'items.product', 'items.batch'])
            ->where('organization_id', $organization->id)
            ->where(fn ($q) => $q->whereIn('source_site_id', $this->siteIds($request, $organization))->orWhereIn('destination_site_id', $this->siteIds($request, $organization)))
            ->latest()->paginate(30));
    }

    public function storeTransfer(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'transfers.manage');
        $data = $request->validate([
            'reference' => ['required', 'alpha_dash', 'max:60', Rule::unique('transfers')->where('organization_id', $organization->id)],
            'source_site_id' => ['required', 'uuid', 'different:destination_site_id'], 'destination_site_id' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1'],
            'items.*.batch_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $source = $this->site($request, $organization, $data['source_site_id']);
        $destination = $this->organizationSite($organization, $data['destination_site_id']);
        $transfer = DB::transaction(function () use ($data, $organization, $source, $destination, $request) {
            $transfer = Transfer::create(['organization_id' => $organization->id, 'reference' => $data['reference'], 'source_site_id' => $source->id, 'destination_site_id' => $destination->id, 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id]);
            foreach ($data['items'] as $row) {
                $batch = $organization->batches()->findOrFail($row['batch_id']);
                $transfer->items()->create(['product_id' => $batch->product_id, 'batch_id' => $batch->id, 'quantity_sent' => $row['quantity']]);
            }

            return $transfer;
        });
        $this->audit->record($request, 'transfer.created', $transfer);

        return response()->json(['transfer' => $transfer->load('items')], 201);
    }

    public function dispatch(Request $request, Organization $organization, Transfer $transfer): JsonResponse
    {
        $this->transferAccess($request, $organization, $transfer);
        $transfer = $this->ledger->dispatchTransfer($transfer, $request->user()->id);
        $this->audit->record($request, 'transfer.dispatched', $transfer);

        return response()->json(['transfer' => $transfer]);
    }

    public function receive(Request $request, Organization $organization, Transfer $transfer): JsonResponse
    {
        $this->transferAccess($request, $organization, $transfer);
        $data = $request->validate(['items' => ['nullable', 'array'], 'items.*.id' => ['required', 'uuid'], 'items.*.quantity_received' => ['required', 'numeric', 'gte:0'], 'items.*.discrepancy_reason' => ['nullable', 'string', 'max:1000']]);
        $received = collect($data['items'] ?? [])->keyBy('id')->all();
        $transfer = $this->ledger->receiveTransfer($transfer, $received, $request->user()->id);
        $this->audit->record($request, 'transfer.received', $transfer);

        return response()->json(['transfer' => $transfer]);
    }

    public function holds(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'stocks.view');

        return response()->json(StockHold::with(['site', 'batch.product'])->where('organization_id', $organization->id)->whereIn('site_id', $this->siteIds($request, $organization))->latest()->paginate(30));
    }

    public function storeHold(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'stocks.adjust');
        $data = $request->validate(['site_id' => ['required', 'uuid'], 'batch_id' => ['required', 'uuid'], 'hold_type' => ['required', Rule::in(['quarantine', 'destruction'])], 'quantity' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:2000']]);
        $site = $this->site($request, $organization, $data['site_id']);
        $batch = $organization->batches()->findOrFail($data['batch_id']);
        $movementType = $data['hold_type'] === 'quarantine' ? 'quarantine' : 'destruction';
        $movement = $this->ledger->record($organization, $site, $batch, $movementType, $data['quantity'], $request->user()->id, ['reason' => $data['reason']]);
        $hold = StockHold::create([...$data, 'organization_id' => $organization->id, 'created_by' => $request->user()->id, 'status' => $data['hold_type'] === 'destruction' ? 'completed' : 'active']);
        $this->audit->record($request, 'stock.hold.created', $hold, [], ['movement_id' => $movement->id]);

        return response()->json(['hold' => $hold, 'movement' => $movement], 201);
    }

    public function releaseHold(Request $request, Organization $organization, StockHold $hold): JsonResponse
    {
        $this->access($request, $organization, 'stocks.adjust');
        abort_unless($hold->organization_id === $organization->id && $this->siteIds($request, $organization)->contains($hold->site_id), 404);
        abort_unless($hold->hold_type === 'quarantine' && $hold->status === 'active', 422, 'Seule une quarantaine active peut être libérée.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $movement = $this->ledger->record($organization, $hold->site, $hold->batch, 'quarantine_release', $hold->quantity, $request->user()->id, ['reason' => $data['reason']]);
        $hold->update(['status' => 'released', 'released_by' => $request->user()->id, 'released_at' => now()]);
        $this->audit->record($request, 'stock.hold.released', $hold, [], ['movement_id' => $movement->id, 'reason' => $data['reason']]);

        return response()->json(['hold' => $hold->fresh(), 'movement' => $movement]);
    }

    private function access(Request $request, Organization $organization, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403);
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
        abort_unless($this->modules->isEnabled('stocks', $organization->id), 403, 'Le module Stocks est désactivé.');
    }

    private function siteIds(Request $request, Organization $organization)
    {
        return $this->scopes->sites($request->user())->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->pluck('id');
    }

    private function site(Request $request, Organization $organization, string $id): Site
    {
        return $this->scopes->sites($request->user())->whereKey($id)->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->firstOrFail();
    }

    private function organizationSite(Organization $organization, string $id): Site
    {
        return Site::whereKey($id)->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->firstOrFail();
    }

    private function transferAccess(Request $request, Organization $organization, Transfer $transfer): void
    {
        $this->access($request, $organization, 'transfers.manage');
        abort_unless($transfer->organization_id === $organization->id, 404);
        abort_unless($this->siteIds($request, $organization)->contains($transfer->source_site_id) || $this->siteIds($request, $organization)->contains($transfer->destination_site_id), 404);
    }
}
