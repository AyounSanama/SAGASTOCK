<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Organization;
use App\Models\Receipt;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\ModuleActivationService;
use App\Services\StockLedgerService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __construct(
        private StockLedgerService $ledger,
        private UserScopeService $scopes,
        private ModuleActivationService $modules,
        private AuditService $audit,
    ) {}

    public function index(Request $request, Organization $organization): View
    {
        $this->access($request, $organization, 'stocks.view');
        $siteIds = $this->siteIds($request, $organization);
        $receipts = Receipt::with(['site.healthFacility', 'supplier', 'items.product', 'items.batch'])
            ->where('organization_id', $organization->id)
            ->whereIn('site_id', $siteIds)
            ->when($request->input('site_id'), fn ($query, $id) => $query->where('site_id', $id))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(fn ($nested) => $nested
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('order_reference', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%")));
            })
            ->latest('received_on')
            ->paginate(20)
            ->withQueryString();

        $allVisible = Receipt::where('organization_id', $organization->id)->whereIn('site_id', $siteIds);
        $stats = [
            'total' => (clone $allVisible)->count(),
            'draft' => (clone $allVisible)->where('status', 'draft')->count(),
            'validated' => (clone $allVisible)->where('status', 'validated')->count(),
            'received' => (float) \App\Models\ReceiptItem::whereHas('receipt', fn ($query) => $query
                ->where('organization_id', $organization->id)->whereIn('site_id', $siteIds))
                ->sum('quantity_received'),
        ];

        return view('receipts.index', [
            'organization' => $organization,
            'receipts' => $receipts,
            'stats' => $stats,
            'sites' => Site::with('healthFacility:id,name')->whereIn('id', $siteIds)->orderBy('name')->get(),
            'suppliers' => $organization->suppliers()->where('is_active', true)->orderBy('name')->get(),
            'products' => $organization->products()->where('is_active', true)->orderBy('name')->get(),
            'canManage' => $request->user()->hasPermission('receipts.manage'),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->access($request, $organization, 'receipts.manage');
        $data = $request->validate([
            'site_id' => ['required', 'uuid'],
            'supplier_id' => ['nullable', 'uuid'],
            'reference' => ['required', 'alpha_dash', 'max:60', Rule::unique('receipts')->where('organization_id', $organization->id)],
            'order_reference' => ['nullable', 'string', 'max:100'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.batch_number' => ['required', 'string', 'max:100'],
            'items.*.expires_on' => ['required', 'date', 'after:today'],
            'items.*.quantity_ordered' => ['nullable', 'numeric', 'gte:0'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0'],
            'items.*.quantity_accepted' => ['required', 'numeric', 'gte:0'],
            'items.*.quantity_rejected' => ['nullable', 'numeric', 'gte:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'items.*.discrepancy_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $site = $this->site($request, $organization, $data['site_id']);
        if (filled($data['supplier_id'] ?? null)) {
            abort_unless($organization->suppliers()->whereKey($data['supplier_id'])->exists(), 422);
        }

        $receipt = DB::transaction(function () use ($organization, $site, $data, $request) {
            $receipt = Receipt::create([
                ...collect($data)->except('items')->all(),
                'organization_id' => $organization->id,
                'site_id' => $site->id,
                'created_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $index => $row) {
                $product = $organization->products()->where('is_active', true)->findOrFail($row['product_id']);
                $received = (float) $row['quantity_received'];
                $accepted = (float) $row['quantity_accepted'];
                $rejected = (float) ($row['quantity_rejected'] ?? 0);
                $ordered = (float) ($row['quantity_ordered'] ?? 0);
                if (abs(($accepted + $rejected) - $received) > 0.0001) {
                    throw ValidationException::withMessages(["items.{$index}.quantity_accepted" => 'La quantité acceptée et la quantité rejetée doivent correspondre à la quantité reçue.']);
                }
                if (($rejected > 0 || abs($ordered - $received) > 0.0001) && blank($row['discrepancy_reason'] ?? null)) {
                    throw ValidationException::withMessages(["items.{$index}.discrepancy_reason" => 'Une justification est obligatoire en cas d’écart ou de rejet.']);
                }
                $batch = Batch::withTrashed()->firstOrNew([
                    'organization_id' => $organization->id,
                    'product_id' => $product->id,
                    'batch_number' => trim($row['batch_number']),
                ]);
                if ($batch->exists && $batch->trashed()) {
                    $batch->restore();
                }
                if ($batch->exists && $batch->expires_on && $batch->expires_on->toDateString() !== $row['expires_on']) {
                    throw ValidationException::withMessages(["items.{$index}.expires_on" => 'Ce numéro de lot existe déjà avec une autre date de péremption.']);
                }
                $batch->fill([
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'expires_on' => $row['expires_on'],
                    'unit_cost' => $row['unit_cost'] ?? null,
                    'status' => 'available',
                ])->save();
                $receipt->items()->create([
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'quantity_ordered' => $ordered,
                    'quantity_received' => $received,
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => $rejected,
                    'unit_cost' => $row['unit_cost'] ?? null,
                    'discrepancy_reason' => $row['discrepancy_reason'] ?? null,
                ]);
            }

            return $receipt;
        });
        $this->audit->record($request, 'receipt.created', $receipt);

        return redirect()->route('organizations.receipts.index', $organization)
            ->with('status', 'Réception enregistrée comme brouillon. Vérifiez le rapport avant validation.');
    }

    public function validateReceipt(Request $request, Organization $organization, Receipt $receipt): RedirectResponse
    {
        $this->access($request, $organization, 'receipts.manage');
        $this->receiptAccess($request, $organization, $receipt);
        abort_unless($receipt->status === 'draft', 422, 'Cette réception a déjà été validée.');
        DB::transaction(function () use ($receipt, $request) {
            foreach ($receipt->items as $item) {
                if ((float) $item->quantity_accepted > 0) {
                    $this->ledger->record(
                        $receipt->organization,
                        $receipt->site,
                        $item->batch,
                        'receipt',
                        $item->quantity_accepted,
                        $request->user()->id,
                        [
                            'reference_type' => 'receipt',
                            'reference_id' => $receipt->id,
                            'unit_cost' => $item->unit_cost,
                            'reason' => 'Réception '.$receipt->reference,
                        ],
                    );
                }
            }
            $receipt->update([
                'status' => 'validated',
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
            ]);
        });
        $this->audit->record($request, 'receipt.validated', $receipt);

        return back()->with('status', 'Réception validée : les quantités acceptées ont été ajoutées au stock.');
    }

    public function show(Request $request, Organization $organization, Receipt $receipt): View
    {
        $this->access($request, $organization, 'stocks.view');
        $this->receiptAccess($request, $organization, $receipt);
        $receipt->load(['site.healthFacility', 'supplier', 'items.product', 'items.batch']);
        $ordered = (float) $receipt->items->sum('quantity_ordered');
        $received = (float) $receipt->items->sum('quantity_received');
        $accepted = (float) $receipt->items->sum('quantity_accepted');

        return view('receipts.show', [
            'organization' => $organization,
            'receipt' => $receipt,
            'totals' => [
                'ordered' => $ordered,
                'received' => $received,
                'accepted' => $accepted,
                'rejected' => (float) $receipt->items->sum('quantity_rejected'),
                'rate' => $ordered > 0 ? round(($received / $ordered) * 100, 2) : null,
            ],
        ]);
    }

    private function access(Request $request, Organization $organization, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403);
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
        abort_unless($this->modules->isEnabled('receipts', $organization->id) && $this->modules->isEnabled('stocks', $organization->id), 403, 'Le module Réceptions est désactivé.');
    }

    private function siteIds(Request $request, Organization $organization)
    {
        return $this->scopes->sites($request->user())
            ->whereHas('healthFacility', fn ($query) => $query->where('organization_id', $organization->id))
            ->pluck('id');
    }

    private function site(Request $request, Organization $organization, string $id): Site
    {
        return $this->scopes->sites($request->user())->whereKey($id)
            ->whereHas('healthFacility', fn ($query) => $query->where('organization_id', $organization->id))
            ->firstOrFail();
    }

    private function receiptAccess(Request $request, Organization $organization, Receipt $receipt): void
    {
        abort_unless($receipt->organization_id === $organization->id && $this->siteIds($request, $organization)->contains($receipt->site_id), 404);
    }
}
