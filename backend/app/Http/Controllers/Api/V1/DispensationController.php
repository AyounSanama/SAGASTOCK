<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dispensation;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\StockLedgerService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DispensationController extends Controller
{
    public function __construct(private UserScopeService $scopes, private StockLedgerService $ledger, private AuditService $audit) {}

    public function patients(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'patients.view');
        return response()->json(Patient::where('organization_id', $organization->id)
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($n) => $n->where('code', 'like', "%$s%")->orWhere('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
            ->orderBy('last_name')->orderBy('first_name')->paginate(30));
    }

    public function storePatient(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'patients.manage');
        $data = $this->patientData($request, $organization);
        $patient = Patient::create([...$data, 'organization_id' => $organization->id]);
        $this->audit->record($request, 'patient.created', $patient);
        return response()->json(['patient' => $patient], 201);
    }

    public function updatePatient(Request $request, Organization $organization, Patient $patient): JsonResponse
    {
        $this->access($request, $organization, 'patients.manage');
        abort_unless($patient->organization_id === $organization->id, 404);
        $old = $patient->toArray();
        $patient->update($this->patientData($request, $organization, $patient));
        $this->audit->record($request, 'patient.updated', $patient, $old, $patient->fresh()->toArray());
        return response()->json(['patient' => $patient]);
    }

    public function archivePatient(Request $request, Organization $organization, Patient $patient): JsonResponse
    {
        $this->access($request, $organization, 'patients.manage');
        abort_unless($patient->organization_id === $organization->id, 404);
        $patient->update(['is_active' => false]); $patient->delete();
        $this->audit->record($request, 'patient.archived', $patient);
        return response()->json(status: 204);
    }

    public function restorePatient(Request $request, Organization $organization, string $patient): JsonResponse
    {
        $this->access($request, $organization, 'patients.manage');
        $model = Patient::onlyTrashed()->where('organization_id', $organization->id)->findOrFail($patient);
        $model->restore(); $model->update(['is_active' => true]);
        $this->audit->record($request, 'patient.restored', $model);
        return response()->json(['patient' => $model]);
    }

    public function prescriptions(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'prescriptions.view');
        return response()->json(Prescription::with(['patient', 'site', 'items.product'])->where('organization_id', $organization->id)
            ->whereIn('site_id', $this->siteIds($request, $organization))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))->latest('prescribed_on')->paginate(30));
    }

    public function storePrescription(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'prescriptions.manage');
        $data = $request->validate([
            'patient_id' => ['required', 'uuid'], 'site_id' => ['required', 'uuid'],
            'reference' => ['required', 'max:80', Rule::unique('prescriptions')->where('organization_id', $organization->id)],
            'prescribed_on' => ['required', 'date', 'before_or_equal:today'], 'prescriber_name' => ['required', 'string', 'max:190'],
            'diagnosis' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity_prescribed' => ['required', 'numeric', 'gt:0'], 'items.*.dosage' => ['nullable', 'string', 'max:190'],
            'items.*.frequency' => ['nullable', 'string', 'max:120'], 'items.*.duration' => ['nullable', 'string', 'max:120'], 'items.*.instructions' => ['nullable', 'string', 'max:1000'],
        ]);
        $patient = Patient::where('organization_id', $organization->id)->where('is_active', true)->findOrFail($data['patient_id']);
        $site = $this->site($request, $organization, $data['site_id']);
        $productIds = collect($data['items'])->pluck('product_id')->unique();
        abort_unless(Product::where('organization_id', $organization->id)->whereIn('id', $productIds)->count() === $productIds->count(), 422, 'Un produit ne fait pas partie de cette organisation.');
        $prescription = DB::transaction(function () use ($data, $organization, $patient, $site, $request) {
            $model = Prescription::create([...collect($data)->except('items')->all(), 'organization_id' => $organization->id, 'patient_id' => $patient->id, 'site_id' => $site->id, 'created_by' => $request->user()->id]);
            foreach ($data['items'] as $row) $model->items()->create($row);
            return $model;
        });
        $this->audit->record($request, 'prescription.created', $prescription);
        return response()->json(['prescription' => $prescription->fresh(['patient', 'site', 'items.product'])], 201);
    }

    public function validatePrescription(Request $request, Organization $organization, Prescription $prescription): JsonResponse
    {
        $this->access($request, $organization, 'prescriptions.validate');
        abort_unless($prescription->organization_id === $organization->id && $this->siteIds($request, $organization)->contains($prescription->site_id), 404);
        abort_unless($prescription->status === 'draft', 422, 'Cette ordonnance ne peut plus être validée.');
        $prescription->update(['status' => 'validated', 'validated_by' => $request->user()->id, 'validated_at' => now()]);
        $this->audit->record($request, 'prescription.validated', $prescription);
        return response()->json(['prescription' => $prescription->fresh(['patient', 'site', 'items.product'])]);
    }

    public function dispensations(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'dispensations.view');
        return response()->json(Dispensation::with(['patient', 'prescription', 'site', 'items.product', 'items.batch'])
            ->where('organization_id', $organization->id)->whereIn('site_id', $this->siteIds($request, $organization))->latest('dispensed_at')->paginate(30));
    }

    public function options(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'dispensations.manage');
        $sites = Site::with('healthFacility:id,name')->whereIn('id', $this->siteIds($request, $organization))->orderBy('name')->get();
        return response()->json([
            'sites' => $sites,
            'patients' => Patient::where('organization_id', $organization->id)->where('is_active', true)->orderBy('last_name')->get(),
            'prescriptions' => Prescription::with(['patient', 'items.product'])->where('organization_id', $organization->id)->whereIn('site_id', $sites->pluck('id'))->whereIn('status', ['validated', 'partially_dispensed'])->latest('prescribed_on')->get(),
            'products' => Product::where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function storeDispensation(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'dispensations.manage');
        $data = $request->validate([
            'offline_uuid' => ['nullable', 'uuid'], 'reference' => ['required', 'max:80', Rule::unique('dispensations')->where('organization_id', $organization->id)],
            'patient_id' => ['required', 'uuid'], 'prescription_id' => ['nullable', 'uuid'], 'site_id' => ['required', 'uuid'],
            'dispensed_at' => ['required', 'date', 'before_or_equal:now'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.prescription_item_id' => ['nullable', 'uuid'],
            'items.*.product_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        if (! empty($data['offline_uuid']) && ($existing = Dispensation::where('offline_uuid', $data['offline_uuid'])->first())) return response()->json(['dispensation' => $existing->load(['items.product', 'items.batch'])]);
        $patient = Patient::where('organization_id', $organization->id)->where('is_active', true)->findOrFail($data['patient_id']);
        $site = $this->site($request, $organization, $data['site_id']);
        $prescription = empty($data['prescription_id']) ? null : Prescription::where('organization_id', $organization->id)->where('patient_id', $patient->id)->whereIn('status', ['validated', 'partially_dispensed'])->findOrFail($data['prescription_id']);
        $dispensation = DB::transaction(function () use ($data, $organization, $patient, $site, $prescription, $request) {
            $model = Dispensation::create([...collect($data)->except('items')->all(), 'organization_id' => $organization->id, 'patient_id' => $patient->id, 'site_id' => $site->id, 'prescription_id' => $prescription?->id, 'status' => 'validated', 'dispensed_by' => $request->user()->id]);
            foreach ($data['items'] as $row) {
                $product = Product::where('organization_id', $organization->id)->findOrFail($row['product_id']);
                $quantity = (float) $row['quantity'];
                $prescriptionItem = empty($row['prescription_item_id']) ? null : PrescriptionItem::where('prescription_id', $prescription?->id)->where('product_id', $product->id)->findOrFail($row['prescription_item_id']);
                if ($prescriptionItem && (float) $prescriptionItem->quantity_dispensed + $quantity > (float) $prescriptionItem->quantity_prescribed) throw ValidationException::withMessages(['items' => 'La quantité dépasse le reliquat prescrit.']);
                $allocations = $this->ledger->fefo($organization, $site, $product->id, $quantity);
                if ($allocations->sum('suggested_quantity') + .0001 < $quantity) throw ValidationException::withMessages(['items' => "Stock FEFO insuffisant pour {$product->name}."]);
                foreach ($allocations as $allocation) {
                    $movement = $this->ledger->record($organization, $site, $allocation['batch'], 'issue', $allocation['suggested_quantity'], $request->user()->id, ['reference_type' => 'dispensation', 'reference_id' => $model->id, 'reason' => 'Dispensation '.$model->reference]);
                    $model->items()->create(['prescription_item_id' => $prescriptionItem?->id, 'product_id' => $product->id, 'batch_id' => $movement->batch_id, 'quantity' => $allocation['suggested_quantity']]);
                }
                if ($prescriptionItem) $prescriptionItem->increment('quantity_dispensed', $quantity);
            }
            if ($prescription) {
                $complete = $prescription->items()->get()->every(fn ($item) => (float) $item->quantity_dispensed >= (float) $item->quantity_prescribed);
                $prescription->update(['status' => $complete ? 'dispensed' : 'partially_dispensed']);
            }
            return $model;
        });
        $this->audit->record($request, 'dispensation.validated', $dispensation);
        return response()->json(['dispensation' => $dispensation->fresh(['patient', 'prescription', 'site', 'items.product', 'items.batch'])], 201);
    }

    private function patientData(Request $request, Organization $organization, ?Patient $patient = null): array
    {
        return $request->validate(['code' => ['required', 'alpha_dash', 'max:60', Rule::unique('patients')->where('organization_id', $organization->id)->ignore($patient?->id)], 'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'], 'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'], 'sex' => ['nullable', Rule::in(['female', 'male', 'other', 'unknown'])], 'phone' => ['nullable', 'string', 'max:40'], 'external_identifier' => ['nullable', 'string', 'max:120'], 'address' => ['nullable', 'string', 'max:1000'], 'is_active' => ['sometimes', 'boolean']]);
    }
    private function access(Request $request, Organization $organization, string $permission): void { abort_unless($request->user()->hasPermission($permission), 403); abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404); }
    private function siteIds(Request $request, Organization $organization) { return $this->scopes->sites($request->user())->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->pluck('id'); }
    private function site(Request $request, Organization $organization, string $id): Site { return $this->scopes->sites($request->user())->whereKey($id)->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->firstOrFail(); }
}
