<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\V1\InventoryController as InventoryApiController;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Organization;
use App\Models\Site;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(
        private UserScopeService $scopes,
        private InventoryApiController $api,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventories.view'), 403);
        $organizations = $this->scopes->organizations($request->user())->orderBy('name')->get();
        $organization = $request->filled('organization_id')
            ? $organizations->firstWhere('id', $request->string('organization_id')->toString())
            : ($organizations->firstWhere('id', $request->user()->organization_id) ?? $organizations->first());
        abort_if(! $organization, $request->filled('organization_id') ? 404 : 403);

        $siteIds = $this->scopes->sites($request->user())
            ->where('organization_id', $organization->id)->pluck('id');
        $sites = Site::with('healthFacility:id,name')->whereIn('id', $siteIds)->orderBy('name')->get();
        $inventories = Inventory::with(['site.healthFacility', 'lines.product', 'lines.batch'])
            ->where('organization_id', $organization->id)->whereIn('site_id', $siteIds)
            ->latest('period_date')->get();

        return view('inventories.index', compact('organizations', 'organization', 'sites', 'inventories'));
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->api->store($request, $organization);
        return back()->with('status', 'Inventaire créé. Démarrez-le pour figer le stock.');
    }

    public function start(Request $request, Organization $organization, Inventory $inventory): RedirectResponse
    {
        $this->api->start($request, $organization, $inventory);
        return back()->with('status', 'Stock gelé et feuille de comptage générée.');
    }

    public function count(Request $request, Organization $organization, Inventory $inventory): RedirectResponse
    {
        $this->api->count($request, $organization, $inventory);
        return back()->with('status', 'Comptage enregistré.');
    }

    public function submit(Request $request, Organization $organization, Inventory $inventory): RedirectResponse
    {
        $this->api->submit($request, $organization, $inventory);
        return back()->with('status', 'Inventaire transmis pour validation.');
    }

    public function validateInventory(Request $request, Organization $organization, Inventory $inventory): RedirectResponse
    {
        $response = $this->api->validateInventory($request, $organization, $inventory);
        $message = data_get($response->getData(true), 'inventory.status') === 'rejected'
            ? 'Inventaire rejeté.' : 'Inventaire validé et stock rapproché.';
        return back()->with('status', $message);
    }

    public function export(Request $request, Organization $organization, Inventory $inventory): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('inventories.view'), 403);
        abort_unless(
            $inventory->organization_id === $organization->id
            && $this->scopes->sites($request->user())->whereKey($inventory->site_id)->exists(),
            404,
        );
        $inventory->load(['site', 'lines.product', 'lines.batch']);

        return response()->streamDownload(function () use ($inventory): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Produit', 'Lot', 'Théorique', 'Physique', 'Écart', 'Coût unitaire', 'Valeur écart', 'Justification'], ';');
            foreach ($inventory->lines as $line) {
                fputcsv($output, [
                    $line->product?->name, $line->batch?->batch_number,
                    $line->theoretical_quantity, $line->physical_quantity,
                    $line->variance_quantity, $line->unit_cost,
                    $line->variance_value, $line->justification,
                ], ';');
            }
            fclose($output);
        }, $inventory->reference.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
