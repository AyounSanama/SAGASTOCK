<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Site;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\AuditService;
use App\Services\ModuleActivationService;
use App\Services\StockLedgerService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private StockLedgerService $ledger, private UserScopeService $scopes, private ModuleActivationService $modules, private AuditService $audit) {}

    public function index(Request $request, Organization $organization): View
    {
        $this->access($request, $organization, 'stocks.view');
        $siteIds = $this->siteIds($request, $organization);
        $sites = Site::with('healthFacility')->whereIn('id', $siteIds)->orderBy('name')->get();
        $balances = StockBalance::with(['site', 'product', 'batch'])->where('organization_id', $organization->id)->whereIn('site_id', $siteIds)
            ->when($request->input('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderByDesc('last_movement_at')->paginate(25)->withQueryString();
        $movements = StockMovement::with(['site', 'product', 'batch'])->where('organization_id', $organization->id)->whereIn('site_id', $siteIds)->latest('validated_at')->limit(30)->get();
        $products = $organization->products()->with(['batches' => fn ($q) => $q->where('status', 'available')->orderBy('expires_on')])->where('is_active', true)->orderBy('name')->get();

        return view('stocks.index', compact('organization', 'sites', 'balances', 'movements', 'products'));
    }

    public function storeMovement(Request $request, Organization $organization): RedirectResponse
    {
        $this->access($request, $organization, 'stocks.manage');
        $data = $request->validate(['site_id' => ['required', 'uuid'], 'batch_id' => ['required', 'uuid'], 'movement_type' => ['required', 'in:opening,receipt,entry,issue,return_in,return_out,adjustment_in,adjustment_out,loss,damage,expiry,quarantine,quarantine_release,destruction'], 'quantity' => ['required', 'numeric', 'gt:0'], 'reason' => ['nullable', 'string', 'max:2000']]);
        if (in_array($data['movement_type'], ['adjustment_in', 'adjustment_out', 'loss', 'damage', 'expiry', 'quarantine', 'destruction'], true)) {
            $request->validate(['reason' => ['required', 'string', 'min:5']]);
        }
        $site = $this->site($request, $organization, $data['site_id']);
        $batch = $organization->batches()->findOrFail($data['batch_id']);
        $movement = $this->ledger->record($organization, $site, $batch, $data['movement_type'], $data['quantity'], $request->user()->id, ['reason' => $data['reason'] ?? null]);
        $this->audit->record($request, 'stock.movement.validated', $movement);

        return back()->with('status', 'Mouvement validé et solde recalculé.');
    }

    public function compensate(Request $request, Organization $organization, StockMovement $movement): RedirectResponse
    {
        $this->access($request, $organization, 'stocks.adjust');
        abort_unless($movement->organization_id === $organization->id && $this->siteIds($request, $organization)->contains($movement->site_id), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $correction = $this->ledger->compensate($movement, $data['reason'], $request->user()->id);
        $this->audit->record($request, 'stock.movement.compensated', $correction, [], ['original_id' => $movement->id]);

        return back()->with('status', 'Mouvement compensatoire créé ; le registre original est conservé.');
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
}
