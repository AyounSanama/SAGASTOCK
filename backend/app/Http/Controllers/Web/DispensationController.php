<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\V1\DispensationController as ClinicalApiController;
use App\Http\Controllers\Controller;
use App\Models\Dispensation;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Site;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispensationController extends Controller
{
    public function __construct(private UserScopeService $scopes, private ClinicalApiController $clinical) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('dispensations.view') || $request->user()->hasPermission('dispensing.view'), 403);
        $organizations = $this->scopes->organizations($request->user())->orderBy('name')->get();
        $organization = $request->filled('organization_id')
            ? $organizations->firstWhere('id', $request->string('organization_id')->toString())
            : ($organizations->firstWhere('id', $request->user()->organization_id) ?? $organizations->first());
        abort_if(! $organization, $request->filled('organization_id') ? 404 : 403);
        $siteIds = $this->scopes->sites($request->user())->where('organization_id', $organization->id)->pluck('id');
        $patients = Patient::where('organization_id', $organization->id)->orderBy('last_name')->orderBy('first_name')->get();
        $prescriptions = Prescription::with(['patient', 'site', 'items.product'])->where('organization_id', $organization->id)->whereIn('site_id', $siteIds)->latest('prescribed_on')->get();
        $dispensations = Dispensation::with(['patient', 'prescription', 'site', 'items.product', 'items.batch'])->where('organization_id', $organization->id)->whereIn('site_id', $siteIds)->latest('dispensed_at')->get();
        $sites = Site::with('healthFacility:id,name')->whereIn('id', $siteIds)->orderBy('name')->get();
        $products = Product::where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get();
        // Niveau 7 : couples ONG/Bailleur par site et lots en stock (sortie de lots périmés ou détériorés).
        $origins = app(\App\Services\StockOriginService::class);
        $originsBySite = $sites->mapWithKeys(fn (Site $site) => [$site->id => $origins->options($site)]);
        $stockBatches = \App\Models\StockBalance::with('batch:id,batch_number,expires_on,origin_type,origin_project_id')->whereIn('site_id', $sites->pluck('id'))
            ->whereRaw('(theoretical_quantity - reserved_quantity) > 0')->get()
            ->map(fn ($balance) => ['site_id' => $balance->site_id, 'product_id' => $balance->product_id, 'batch_id' => $balance->batch_id,
                'batch_number' => $balance->batch?->batch_number, 'expires_on' => $balance->batch?->expires_on?->format('d/m/Y'),
                'expired' => (bool) $balance->batch?->expires_on?->lt(today()), 'origin_type' => $balance->batch?->origin_type, 'origin_project_id' => $balance->batch?->origin_project_id,
                'available' => (float) $balance->available_quantity]);
        return view('dispensations.index', compact('organizations', 'organization', 'patients', 'prescriptions', 'dispensations', 'sites', 'products', 'originsBySite', 'stockBatches'));
    }

    public function storePatient(Request $request, Organization $organization): RedirectResponse
    {
        $this->clinical->storePatient($request, $organization);
        return back()->with('status', 'Patient enregistré avec succès.');
    }
    public function storePrescription(Request $request, Organization $organization): RedirectResponse
    {
        $this->clinical->storePrescription($request, $organization);
        return back()->with('status', 'Ordonnance enregistrée et transmise pour validation clinique.');
    }
    public function validatePrescription(Request $request, Organization $organization, Prescription $prescription): RedirectResponse
    {
        $response = $this->clinical->validatePrescription($request, $organization, $prescription);
        $status = data_get($response->getData(true), 'prescription.status') === 'rejected' ? 'Ordonnance rejetée avec justification.' : 'Ordonnance validée cliniquement.';
        return back()->with('status', $status);
    }
    public function storeDispensation(Request $request, Organization $organization): RedirectResponse
    {
        $response = $this->clinical->storeDispensation($request, $organization);
        $status = data_get($response->getData(true), 'dispensation.status');
        return back()->with('status', match ($status) { 'partial' => 'Dispensation partielle enregistrée ; reliquat en attente.', 'stockout' => 'Rupture enregistrée ; ordonnance mise en attente.', default => 'Dispensation FEFO validée et stock mis à jour.' });
    }
    public function returnDispensation(Request $request, Organization $organization, Dispensation $dispensation): RedirectResponse
    {
        $this->clinical->returnDispensation($request, $organization, $dispensation);
        return back()->with('status', 'Retour enregistré et stock réintégré avec traçabilité.');
    }
}
