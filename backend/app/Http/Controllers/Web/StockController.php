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
    private const MOVEMENT_LABELS = [
        'opening' => 'Stock initial',
        'receipt' => 'Réception pharmaceutique',
        'entry' => 'Autre entrée',
        'issue' => 'Sortie de stock',
        'return_in' => 'Retour entrant',
        'return_out' => 'Retour sortant',
        'adjustment_in' => 'Ajustement positif',
        'adjustment_out' => 'Ajustement négatif',
        'loss' => 'Perte',
        'damage' => 'Détérioration',
        'expiry' => 'Péremption',
        'quarantine' => 'Mise en quarantaine',
        'quarantine_release' => 'Sortie de quarantaine',
        'destruction' => 'Destruction',
    ];

    public function home(Request $request): RedirectResponse
    {
        $organization = $this->scopes->organizations($request->user())
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
        abort_unless($organization, 404, 'Aucune organisation accessible pour ce compte.');

        return redirect()->route('organizations.stocks.index', $organization);
    }

    public function __construct(private StockLedgerService $ledger, private UserScopeService $scopes, private ModuleActivationService $modules, private AuditService $audit) {}

    public function index(Request $request, Organization $organization): View
    {
        $this->access($request, $organization, 'stocks.view');
        $siteIds = $this->siteIds($request, $organization);
        $sites = Site::with('healthFacility')->whereIn('id', $siteIds)->orderBy('name')->get();
        $balanceQuery = StockBalance::where('organization_id', $organization->id)->whereIn('site_id', $siteIds)
            ->when($request->input('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($request->boolean('available_only'), fn ($q) => $q->whereRaw('(theoretical_quantity - reserved_quantity) > 0'))
            ->when($request->input('expiry'), function ($q, $expiry) {
                $q->whereHas('batch', fn ($batch) => match ($expiry) {
                    'expired' => $batch->whereDate('expires_on', '<', today()),
                    'soon' => $batch->whereBetween('expires_on', [today(), today()->addMonths(3)]),
                    default => $batch,
                });
            });
        $balances = (clone $balanceQuery)->with(['site', 'product', 'batch'])->orderByDesc('last_movement_at')->paginate(25)->withQueryString();
        $movements = StockMovement::with(['site', 'product', 'batch'])->where('organization_id', $organization->id)->whereIn('site_id', $siteIds)
            ->when($request->input('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->input('movement_type'), fn ($q, $type) => $q->where('movement_type', $type))
            ->latest('validated_at')->limit(50)->get();
        $stats = [
            'lines' => (clone $balanceQuery)->count(),
            'quantity' => (float) (clone $balanceQuery)->sum('theoretical_quantity'),
            'expiring' => (clone $balanceQuery)->whereHas('batch', fn ($q) => $q->whereBetween('expires_on', [today(), today()->addMonths(3)]))->count(),
            'expired' => (clone $balanceQuery)->whereHas('batch', fn ($q) => $q->whereDate('expires_on', '<', today()))->count(),
        ];
        $fefoPriorities = StockBalance::with(['site', 'product', 'batch'])->where('stock_balances.organization_id', $organization->id)->whereIn('stock_balances.site_id', $siteIds)
            ->whereRaw('(stock_balances.theoretical_quantity - stock_balances.reserved_quantity) > 0')
            ->whereHas('batch', fn ($q) => $q->where('status', 'available')->whereDate('expires_on', '>=', today()))
            ->join('batches', 'batches.id', '=', 'stock_balances.batch_id')
            ->orderBy('batches.expires_on')->select('stock_balances.*')->limit(8)->get();
        $movementProducts = $organization->products()->with([
            'batches' => fn ($q) => $q->whereIn('status', ['available', 'quarantine'])->orderBy('expires_on'),
        ])->where('is_active', true)->orderBy('name')->get();

        return view('stocks.index', compact('organization', 'sites', 'balances', 'movements', 'stats', 'fefoPriorities', 'movementProducts') + ['movementLabels' => self::MOVEMENT_LABELS]);
    }

    public function createMovement(Request $request, Organization $organization): View
    {
        $this->access($request, $organization, 'stocks.manage');
        $siteIds = $this->siteIds($request, $organization);

        return view('stocks.create-movement', [
            'organization' => $organization,
            'sites' => Site::with('healthFacility')->whereIn('id', $siteIds)->orderBy('name')->get(),
            'products' => $organization->products()->with(['batches' => fn ($q) => $q->whereIn('status', ['available', 'quarantine'])->orderBy('expires_on')])->where('is_active', true)->orderBy('name')->get(),
            'movementLabels' => self::MOVEMENT_LABELS,
        ]);
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

        return redirect()->route('organizations.stocks.index', $organization)
            ->with('status', 'Mouvement validé et solde du stock recalculé avec succès.');
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
