<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Organization;
use App\Notifications\OperationalNotification;
use App\Models\Receipt;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\ModuleActivationService;
use App\Services\StockLedgerService;
use App\Services\StockOriginService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReceiptController extends Controller
{
    public function __construct(private StockLedgerService $ledger, private UserScopeService $scopes, private ModuleActivationService $modules, private AuditService $audit, private StockOriginService $origins) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'stocks.view');

        return response()->json(Receipt::with(['site', 'supplier', 'items.product', 'items.batch', 'originProject.organization:id,name', 'originProject.donors:id,name'])
            ->where('organization_id', $organization->id)->whereIn('site_id', $this->siteIds($request, $organization))->latest('received_on')->paginate(30)
            // Niveau 7 : libellé de l'origine (couple ONG/Bailleur ou « Autre »).
            ->through(fn (Receipt $receipt) => [...$receipt->toArray(), 'origin_display' => $receipt->origin_type ? $this->origins->label($receipt->originProject, $receipt->origin_label) : null]));
    }

    public function options(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'receipts.manage');

        return response()->json([
            // Niveau 7 : couples ONG/Bailleur proposés pour chaque site (projets de sa FOSA).
            'sites' => Site::with('healthFacility:id,name')->whereIn('id', $this->siteIds($request, $organization))->orderBy('name')->get()
                ->map(fn (Site $site) => [...$site->toArray(), 'origins' => $this->origins->options($site)->values()]),
            'suppliers' => $organization->suppliers()->where('is_active', true)->orderBy('name')->get(),
            'products' => $organization->products()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'receipts.manage');
        $data = $request->validate([
            'site_id' => ['required', 'uuid'], 'supplier_id' => ['nullable', 'uuid'],
            'reference' => ['required', 'alpha_dash', 'max:60', Rule::unique('receipts')->where('organization_id', $organization->id)],
            'order_reference' => ['nullable', 'string', 'max:100'], 'received_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1'],
            'items.*.batch_id' => ['nullable', 'uuid', 'required_without:items.*.product_id'],
            'items.*.product_id' => ['nullable', 'uuid', 'required_without:items.*.batch_id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100', 'required_with:items.*.product_id'],
            'items.*.expires_on' => ['nullable', 'date', 'after:today', 'required_with:items.*.product_id'],
            'items.*.quantity_ordered' => ['nullable', 'numeric', 'gte:0'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0'], 'items.*.quantity_accepted' => ['required', 'numeric', 'gte:0'],
            'items.*.quantity_rejected' => ['nullable', 'numeric', 'gte:0'], 'items.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'items.*.discrepancy_reason' => ['nullable', 'string', 'max:1000'],
            ...StockOriginService::rules(),
        ], StockOriginService::messages(), StockOriginService::attributes());
        $site = $this->site($request, $organization, $data['site_id']);
        $origin = $this->origins->resolve($site, $data);
        if (filled($data['supplier_id'] ?? null)) {
            abort_unless($organization->suppliers()->whereKey($data['supplier_id'])->exists(), 422);
        }
        $receipt = DB::transaction(function () use ($organization, $site, $data, $request, $origin) {
            $receipt = Receipt::create([...collect($data)->except(['items', 'origin_type', 'origin_project_id', 'origin_label'])->all(), ...collect($origin)->except('origin_key')->all(), 'organization_id' => $organization->id, 'site_id' => $site->id, 'created_by' => $request->user()->id]);
            foreach ($data['items'] as $row) {
                if (filled($row['batch_id'] ?? null)) {
                    $batch = $organization->batches()->findOrFail($row['batch_id']);
                    if ($batch->origin_key !== '' && $batch->origin_key !== $origin['origin_key']) {
                        throw ValidationException::withMessages(['items' => 'Ce lot appartient à une autre origine (couple ONG/Bailleur).']);
                    }
                } else {
                    $product = $organization->products()->where('is_active', true)->findOrFail($row['product_id']);
                    // Niveau 7 : lot propre à l'origine de l'entrée.
                    $batch = $this->origins->batch($organization, $product->id, $row['batch_number'], $origin);
                    if ($batch->exists && $batch->expires_on && $batch->expires_on->toDateString() !== $row['expires_on']) {
                        throw ValidationException::withMessages(['items' => 'Ce numéro de lot existe déjà avec une autre date de péremption.']);
                    }
                    $batch->fill([
                        'supplier_id' => $data['supplier_id'] ?? null,
                        'expires_on' => $row['expires_on'],
                        'unit_cost' => $row['unit_cost'] ?? null,
                        'status' => 'available',
                    ])->save();
                }
                $received = (float) $row['quantity_received'];
                $accepted = (float) $row['quantity_accepted'];
                $rejected = (float) ($row['quantity_rejected'] ?? 0);
                if (abs(($accepted + $rejected) - $received) > 0.0001) {
                    throw ValidationException::withMessages(['items' => 'La quantité acceptée plus la quantité rejetée doit égaler la quantité reçue.']);
                }
                if (($rejected > 0 || abs((float) ($row['quantity_ordered'] ?? 0) - $received) > 0.0001) && blank($row['discrepancy_reason'] ?? null)) {
                    throw ValidationException::withMessages(['items' => 'Une justification est requise en cas d’écart ou de rejet.']);
                }
                $receipt->items()->create([
                    ...collect($row)->only(['quantity_ordered', 'quantity_received', 'quantity_accepted', 'unit_cost', 'discrepancy_reason'])->all(),
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'quantity_rejected' => $rejected,
                ]);
            }

            return $receipt;
        });
        $this->audit->record($request, 'receipt.created', $receipt);

        return response()->json(['receipt' => $receipt->fresh('items')], 201);
    }

    public function validateReceipt(Request $request, Organization $organization, Receipt $receipt): JsonResponse
    {
        $this->access($request, $organization, 'receipts.manage');
        abort_unless($receipt->organization_id === $organization->id && $this->siteIds($request, $organization)->contains($receipt->site_id), 404);
        abort_unless($receipt->status === 'draft', 422, 'Réception déjà validée.');
        DB::transaction(function () use ($receipt, $request) {
            foreach ($receipt->items as $item) {
                if ((float) $item->quantity_accepted > 0) {
                    $this->ledger->record($receipt->organization, $receipt->site, $item->batch, 'receipt', $item->quantity_accepted, $request->user()->id, ['reference_type' => 'receipt', 'reference_id' => $receipt->id, 'unit_cost' => $item->unit_cost, 'reason' => 'Réception '.$receipt->reference]);
                }
            }
            $receipt->update(['status' => 'validated', 'validated_by' => $request->user()->id, 'validated_at' => now()]);
        });
        $this->audit->record($request, 'receipt.validated', $receipt);
        $request->user()->notify(new OperationalNotification([
            'title' => 'Réception validée',
            'message' => "La réception {$receipt->reference} a crédité le stock.",
            'category' => 'receipt',
            'action_path' => '/receipts',
        ]));

        return response()->json(['receipt' => $receipt->fresh(['items', 'site', 'supplier'])]);
    }

    private function access(Request $request, Organization $organization, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403);
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
        abort_unless($this->modules->isEnabled('receipts', $organization->id) && $this->modules->isEnabled('stocks', $organization->id), 403);
    }

    private function siteIds(Request $request, Organization $organization)
    {
        return $this->scopes->sites($request->user())->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->pluck('id');
    }

    private function site(Request $request, Organization $organization, string $id): Site
    {
        return $this->scopes->sites($request->user())->whereKey($id)->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->firstOrFail();
    }
}
