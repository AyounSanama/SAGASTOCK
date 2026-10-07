<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dispensation;
use App\Models\DispensationItem;
use App\Models\HealthFacility;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\Site;
use App\Models\StandardList;
use App\Models\StandardListVersion;
use App\Services\AuditService;
use App\Services\HealthFacilityManagementService;
use App\Services\StockLedgerService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DispensationController extends Controller
{
    public function __construct(private UserScopeService $scopes, private StockLedgerService $ledger, private AuditService $audit) {}

    public function patients(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'patients.view');
        return response()->json($this->patientQuery($request, $organization)
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($n) => $n->where('code', 'like', "%$s%")->orWhere('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
            ->orderBy('last_name')->orderBy('first_name')->paginate(30));
    }

    public function patientHistory(Request $request, Organization $organization, Patient $patient): JsonResponse
    {
        $this->access($request, $organization, 'patients.view');
        abort_unless($this->patientQuery($request, $organization)->whereKey($patient->id)->exists(), 404);
        return response()->json(['patient' => $patient, 'prescriptions' => $patient->prescriptions()
            ->with(['site', 'items.product', 'dispensations.items.batch'])->latest('prescribed_on')->get(),
            'dispensations' => $patient->dispensations()->with(['site', 'prescription', 'items.product', 'items.batch'])->latest('dispensed_at')->get()]);
    }

    public function storePatient(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'patients.manage');
        if ($request->filled('client_reference') && ($existing = Patient::where('client_reference', $request->input('client_reference'))->first())) {
            abort_unless($this->patientQuery($request, $organization)->whereKey($existing->id)->exists(), 404);
            return response()->json(['patient' => $existing]);
        }
        $data = $this->patientData($request, $organization);
        $site = $this->patientSite($request, $organization, $data['site_id'] ?? null);
        $patient = Patient::create([
            ...$data,
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'health_facility_id' => $site->health_facility_id,
        ]);
        $this->audit->record($request, 'patient.created', $patient);
        return response()->json(['patient' => $patient], 201);
    }

    public function updatePatient(Request $request, Organization $organization, Patient $patient): JsonResponse
    {
        $this->access($request, $organization, 'patients.manage');
        abort_unless($this->patientQuery($request, $organization)->whereKey($patient->id)->exists(), 404);
        $old = $patient->toArray();
        $data = $this->patientData($request, $organization, $patient);
        if (array_key_exists('site_id', $data)) {
            $site = $this->patientSite($request, $organization, $data['site_id']);
            $data['health_facility_id'] = $site->health_facility_id;
        }
        $patient->update($data);
        $this->audit->record($request, 'patient.updated', $patient, $old, $patient->fresh()->toArray());
        return response()->json(['patient' => $patient]);
    }

    public function archivePatient(Request $request, Organization $organization, Patient $patient): JsonResponse
    {
        $this->access($request, $organization, 'patients.manage');
        abort_unless($this->patientQuery($request, $organization)->whereKey($patient->id)->exists(), 404);
        $patient->update(['is_active' => false]); $patient->delete();
        $this->audit->record($request, 'patient.archived', $patient);
        return response()->json(status: 204);
    }

    public function restorePatient(Request $request, Organization $organization, string $patient): JsonResponse
    {
        $this->access($request, $organization, 'patients.manage');
        $model = Patient::onlyTrashed()->where('organization_id', $organization->id)
            ->whereIn('site_id', $this->siteIds($request, $organization))->findOrFail($patient);
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
        if ($request->filled('client_reference') && ($existing = Prescription::where('client_reference', $request->input('client_reference'))->first())) {
            abort_unless(
                $existing->organization_id === $organization->id
                    && $this->siteIds($request, $organization)->contains($existing->site_id),
                404,
            );
            return response()->json(['prescription' => $existing->load(['patient', 'site', 'items.product'])]);
        }
        $data = $request->validate([
            'client_reference' => ['nullable', 'uuid', 'unique:prescriptions,client_reference'],
            'patient_id' => ['required', 'uuid'], 'site_id' => ['required', 'uuid'],
            'reference' => ['required', 'max:80', Rule::unique('prescriptions')->where('organization_id', $organization->id)],
            'prescribed_on' => ['required', 'date', 'before_or_equal:today'], 'prescriber_name' => ['required', 'string', 'max:190'],
            'service_origin' => ['nullable', 'string', 'max:190'], 'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'diagnosis' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity_prescribed' => ['required', 'numeric', 'gt:0'], 'items.*.dosage' => ['required', 'string', 'max:190'],
            'items.*.frequency' => ['required', 'string', 'max:120'], 'items.*.duration' => ['required', 'string', 'max:120'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'], 'items.*.substitution_authorized' => ['nullable', 'boolean'],
        ]);
        $site = $this->site($request, $organization, $data['site_id']);
        $patient = $this->patientQuery($request, $organization)->where('is_active', true)->findOrFail($data['patient_id']);
        abort_unless($patient->site_id === null || $patient->site_id === $site->id, 422, 'Le patient appartient à une autre formation sanitaire.');
        $productIds = collect($data['items'])->pluck('product_id')->unique();
        abort_unless($this->allowedProducts($request, $organization)->whereIn('id', $productIds)->count() === $productIds->count(), 422, 'Un produit ne fait pas partie de la liste standard autorisée pour ce projet.');
        $attachmentFile = $request->file('attachment');
        $attachment = $attachmentFile?->storeAs('private/prescriptions', Str::uuid().'.'.$attachmentFile->extension());
        $prescription = DB::transaction(function () use ($data, $organization, $patient, $site, $request, $attachment, $attachmentFile) {
            if ($patient->site_id === null) {
                $patient->update(['site_id' => $site->id, 'health_facility_id' => $site->health_facility_id]);
            }
            $model = Prescription::create([...collect($data)->except(['items', 'attachment'])->all(), 'attachment_path' => $attachment,
                'attachment_original_name' => null, 'attachment_mime_type' => $attachmentFile?->getMimeType(),
                'attachment_size' => $attachmentFile?->getSize(), 'attachment_captured_at' => $attachmentFile ? now() : null,
                'organization_id' => $organization->id, 'patient_id' => $patient->id, 'site_id' => $site->id, 'created_by' => $request->user()->id,
                // Validation clinique masquée en V1 (C-07) : statut distinct, jamais « validée ».
                'status' => config('pharmacare_v1.features.clinical_validation') ? 'draft' : Prescription::STATUS_VALIDATION_NOT_REQUIRED]);
            foreach ($data['items'] as $row) $model->items()->create([...$row, 'substitution_authorized' => (bool) ($row['substitution_authorized'] ?? false)]);
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
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'clinical_validation_notes' => ['nullable', 'string', 'max:3000'],
            'rejection_reason' => [Rule::requiredIf($request->input('decision') === 'reject'), 'nullable', 'string', 'min:5', 'max:3000'],
            'protocol_confirmed' => [Rule::requiredIf($request->input('decision') === 'approve'), 'boolean'],
            'dosage_confirmed' => [Rule::requiredIf($request->input('decision') === 'approve'), 'boolean'],
            'contraindications_checked' => [Rule::requiredIf($request->input('decision') === 'approve'), 'boolean'],
        ]);
        $approved = $data['decision'] === 'approve';
        if ($approved && (! $data['protocol_confirmed'] || ! $data['dosage_confirmed'] || ! $data['contraindications_checked'])) throw ValidationException::withMessages(['decision' => 'Tous les contrôles cliniques doivent être confirmés.']);
        $prescription->update(['status' => $approved ? 'validated' : 'rejected', 'clinical_validation_notes' => $data['clinical_validation_notes'] ?? null,
            'rejection_reason' => $data['rejection_reason'] ?? null, 'protocol_confirmed' => $approved, 'dosage_confirmed' => $approved,
            'contraindications_checked' => $approved, 'validated_by' => $request->user()->id, 'validated_at' => now()]);
        $this->audit->record($request, $approved ? 'prescription.validated' : 'prescription.rejected', $prescription, [], $data);
        return response()->json(['prescription' => $prescription->fresh(['patient', 'site', 'items.product'])]);
    }

    public function dispensations(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'dispensations.view');
        return response()->json(Dispensation::with(['patient', 'prescription', 'site', 'items.product', 'items.batch'])
            ->where('organization_id', $organization->id)->whereIn('site_id', $this->siteIds($request, $organization))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))->latest('dispensed_at')->paginate(30));
    }

    public function options(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'dispensations.manage');
        $sites = Site::with('healthFacility:id,name')->whereIn('id', $this->siteIds($request, $organization))->orderBy('name')->get();
        return response()->json(['sites' => $sites, 'patients' => $this->patientQuery($request, $organization)->where('is_active', true)->orderBy('last_name')->get(),
            'prescriptions' => Prescription::with(['patient', 'items.product'])->where('organization_id', $organization->id)->whereIn('site_id', $sites->pluck('id'))->whereIn('status', Prescription::DISPENSABLE_STATUSES)->latest('prescribed_on')->get(),
            'products' => $this->allowedProducts($request, $organization)->with('codes:id,product_id,code_type,value,is_primary')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])]);
    }

    public function fefoSuggestion(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'dispensations.manage');
        $data = $request->validate(['site_id' => ['required', 'uuid'], 'product_id' => ['required', 'uuid'], 'quantity' => ['required', 'numeric', 'gt:0']]);
        $site = $this->site($request, $organization, $data['site_id']);
        $product = $this->allowedProducts($request, $organization)->findOrFail($data['product_id']);
        $allocations = $this->ledger->fefo($organization, $site, $product->id, (float) $data['quantity']);
        return response()->json(['requested_quantity' => (float) $data['quantity'], 'available_quantity' => $allocations->sum('suggested_quantity'), 'allocations' => $allocations]);
    }

    public function storeDispensation(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization, 'dispensations.manage');
        if ($request->filled('offline_uuid') && ($existing = Dispensation::where('offline_uuid', $request->input('offline_uuid'))->first())) {
            abort_unless(
                $existing->organization_id === $organization->id
                    && $this->siteIds($request, $organization)->contains($existing->site_id),
                404,
            );
            return response()->json(['dispensation' => $existing->load(['patient', 'prescription', 'site', 'items.product', 'items.batch'])]);
        }
        $data = $request->validate([
            'offline_uuid' => ['nullable', 'uuid'], 'reference' => ['required', 'max:80', Rule::unique('dispensations')->where('organization_id', $organization->id)],
            'patient_id' => ['required', 'uuid'], 'prescription_id' => ['nullable', 'uuid'], 'site_id' => ['required', 'uuid'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'destination_type' => ['nullable', Rule::in(array_values(array_filter(['patient', 'hospital_service', config('pharmacare_v1.features.community_dispensation') ? 'community' : null, 'other'])))], 'destination_name' => ['nullable', 'string', 'max:190'],
            'allow_partial' => ['nullable', 'boolean'], 'dispensed_at' => ['required', 'date', 'before_or_equal:now'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.prescription_item_id' => ['nullable', 'uuid'],
            'items.*.product_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $patient = $this->patientQuery($request, $organization)->where('is_active', true)->findOrFail($data['patient_id']);
        $site = $this->site($request, $organization, $data['site_id']);
        abort_unless($patient->site_id === null || $patient->site_id === $site->id, 422, 'Le patient appartient à une autre formation sanitaire.');
        $prescription = empty($data['prescription_id']) ? null : Prescription::where('organization_id', $organization->id)->where('patient_id', $patient->id)->whereIn('status', Prescription::DISPENSABLE_STATUSES)->findOrFail($data['prescription_id']);
        $allowPartial = (bool) ($data['allow_partial'] ?? false);
        $attachmentFile = $request->file('attachment');
        $attachmentPath = $attachmentFile?->storeAs('private/dispensations', Str::uuid().'.'.$attachmentFile->extension());
        $dispensation = DB::transaction(function () use ($data, $organization, $patient, $site, $prescription, $request, $allowPartial, $attachmentFile, $attachmentPath) {
            $model = Dispensation::create([...collect($data)->except(['items', 'allow_partial', 'attachment'])->all(), 'destination_type' => $data['destination_type'] ?? 'patient',
                'prescription_attachment_path' => $attachmentPath, 'prescription_attachment_original_name' => null,
                'prescription_attachment_mime_type' => $attachmentFile?->getMimeType(), 'prescription_attachment_size' => $attachmentFile?->getSize(),
                'prescription_attachment_captured_at' => $attachmentFile ? now() : null,
                'organization_id' => $organization->id, 'patient_id' => $patient->id, 'site_id' => $site->id, 'prescription_id' => $prescription?->id, 'status' => 'validated', 'dispensed_by' => $request->user()->id]);
            $totalRequested = 0.0; $totalDispensed = 0.0;
            foreach ($data['items'] as $row) {
                $product = $this->allowedProducts($request, $organization)->findOrFail($row['product_id']);
                $requested = (float) $row['quantity']; $totalRequested += $requested;
                $prescriptionItem = empty($row['prescription_item_id']) ? null : PrescriptionItem::where('prescription_id', $prescription?->id)->where('product_id', $product->id)->findOrFail($row['prescription_item_id']);
                if ($prescriptionItem && (float) $prescriptionItem->quantity_dispensed + $requested > (float) $prescriptionItem->quantity_prescribed) throw ValidationException::withMessages(['items' => 'La quantité dépasse le reliquat prescrit.']);
                $allocations = $this->ledger->fefo($organization, $site, $product->id, $requested);
                $available = (float) $allocations->sum('suggested_quantity');
                if (! $allowPartial && $available + .0001 < $requested) throw ValidationException::withMessages(['items' => "Stock FEFO insuffisant pour {$product->name}."]);
                $first = true;
                foreach ($allocations as $allocation) {
                    $movement = $this->ledger->record($organization, $site, $allocation['batch'], 'issue', $allocation['suggested_quantity'], $request->user()->id, ['reference_type' => 'dispensation', 'reference_id' => $model->id, 'reason' => 'Dispensation '.$model->reference]);
                    $model->items()->create(['prescription_item_id' => $prescriptionItem?->id, 'product_id' => $product->id, 'batch_id' => $movement->batch_id,
                        'quantity_requested' => $first ? $requested : 0, 'quantity' => $allocation['suggested_quantity'], 'quantity_shortage' => $first ? max(0, $requested - $available) : 0]);
                    $first = false;
                }
                $totalDispensed += $available;
                if ($prescriptionItem && $available > 0) $prescriptionItem->increment('quantity_dispensed', $available);
            }
            $status = $totalDispensed <= 0 ? 'stockout' : ($totalDispensed + .0001 < $totalRequested ? 'partial' : 'validated');
            $model->update(['status' => $status]);
            if ($prescription) {
                $complete = $prescription->items()->get()->every(fn ($item) => (float) $item->quantity_dispensed >= (float) $item->quantity_prescribed);
                $prescription->update(['status' => $complete ? 'dispensed' : ($status === 'stockout' ? 'waiting_stock' : 'partially_dispensed')]);
            }
            return $model;
        });
        $this->audit->record($request, 'dispensation.recorded', $dispensation, [], ['status' => $dispensation->status]);
        return response()->json(['dispensation' => $dispensation->fresh(['patient', 'prescription', 'site', 'items.product', 'items.batch'])], 201);
    }

    public function returnDispensation(Request $request, Organization $organization, Dispensation $dispensation): JsonResponse
    {
        $this->access($request, $organization, 'dispensations.manage');
        abort_unless($dispensation->organization_id === $organization->id && $this->siteIds($request, $organization)->contains($dispensation->site_id), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000'], 'items' => ['required', 'array', 'min:1'],
            'items.*.dispensation_item_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'numeric', 'gt:0']]);
        DB::transaction(function () use ($data, $dispensation, $organization, $request) {
            foreach ($data['items'] as $row) {
                $item = DispensationItem::where('dispensation_id', $dispensation->id)->findOrFail($row['dispensation_item_id']);
                $quantity = (float) $row['quantity'];
                if ((float) $item->quantity_returned + $quantity > (float) $item->quantity) throw ValidationException::withMessages(['items' => 'La quantité retournée dépasse la quantité dispensée.']);
                $this->ledger->record($organization, $dispensation->site, $item->batch, 'return_in', $quantity, $request->user()->id, ['reference_type' => 'dispensation_return', 'reference_id' => $dispensation->id, 'reason' => $data['reason']]);
                $item->increment('quantity_returned', $quantity);
            }
            $dispensation->refresh()->load('items');
            $allReturned = $dispensation->items->every(fn ($item) => (float) $item->quantity_returned >= (float) $item->quantity);
            $dispensation->update(['status' => $allReturned ? 'returned' : 'partially_returned']);
        });
        $this->audit->record($request, 'dispensation.returned', $dispensation, [], ['reason' => $data['reason'], 'items' => $data['items']]);
        return response()->json(['dispensation' => $dispensation->fresh(['items.product', 'items.batch'])]);
    }

    private function patientData(Request $request, Organization $organization, ?Patient $patient = null): array
    {
        return $request->validate(['id' => ['nullable', 'uuid', Rule::unique('patients')->ignore($patient?->id)], 'client_reference' => ['nullable', 'uuid', Rule::unique('patients')->ignore($patient?->id)], 'site_id' => ['nullable', 'uuid'], 'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('patients')->where('organization_id', $organization->id)->ignore($patient?->id)],
            'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'], 'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', Rule::in(['female', 'male', 'other', 'unknown'])], 'phone' => ['nullable', 'string', 'max:40'], 'external_identifier' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'], 'allergies' => ['nullable', 'string', 'max:2000'], 'clinical_notes' => ['nullable', 'string', 'max:3000'], 'is_active' => ['sometimes', 'boolean']]);
    }
    private function access(Request $request, Organization $organization, string $permission): void { abort_unless($request->user()->hasPermission($permission), 403); abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404); }
    private function patientQuery(Request $request, Organization $organization): Builder
    {
        $siteIds = $this->siteIds($request, $organization);
        return Patient::where('organization_id', $organization->id)->where(function (Builder $query) use ($siteIds): void {
            $query->whereIn('site_id', $siteIds)
                ->orWhereHas('prescriptions', fn (Builder $q) => $q->whereIn('site_id', $siteIds))
                ->orWhereHas('dispensations', fn (Builder $q) => $q->whereIn('site_id', $siteIds));
        });
    }
    private function patientSite(Request $request, Organization $organization, ?string $siteId): Site
    {
        if ($siteId) return $this->site($request, $organization, $siteId);
        $ids = $this->siteIds($request, $organization);
        if ($ids->count() !== 1) {
            throw ValidationException::withMessages(['site_id' => 'Veuillez sélectionner la formation sanitaire du patient.']);
        }
        return Site::findOrFail($ids->first());
    }
    private function allowedProducts(Request $request, Organization $organization): Builder
    {
        $siteIds = $this->siteIds($request, $organization);
        $projectIds = DB::table('health_facility_project')
            ->join('sites', 'sites.health_facility_id', '=', 'health_facility_project.health_facility_id')
            ->whereIn('sites.id', $siteIds)
            ->pluck('health_facility_project.project_id')->unique();
        $lists = StandardList::where('organization_id', $organization->id)
            ->where('scope_type', 'project')->whereIn('scope_id', $projectIds)
            ->where('is_active', true)->get(['id', 'allow_outside_list']);
        $query = Product::where('organization_id', $organization->id);
        if ($lists->isEmpty() || $lists->contains('allow_outside_list', true)) return $query;

        $versionIds = StandardListVersion::whereIn('standard_list_id', $lists->pluck('id'))
            ->where('status', 'published')->pluck('id');
        $productIds = DB::table('standard_list_items')
            ->whereIn('standard_list_version_id', $versionIds)->pluck('product_id');
        // Niveau 6 : une FOSA configurée suit sa propre Liste Standard (critères
        // de la FOSA, articles décochés par la Coordination retirés).
        $facilities = HealthFacility::whereIn('id', Site::whereIn('id', $siteIds)->pluck('health_facility_id'))->get();
        if ($facilities->isNotEmpty() && $facilities->every(fn ($facility) => $facility->care_level_id)) {
            $management = app(HealthFacilityManagementService::class);
            $productIds = $facilities->flatMap(fn ($facility) => $management->standardList($facility)->pluck('id'))->unique()->values();
        }
        return $query->whereIn('id', $productIds);
    }
    private function siteIds(Request $request, Organization $organization) { return $this->scopes->sites($request->user())->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->pluck('id'); }
    private function site(Request $request, Organization $organization, string $id): Site { return $this->scopes->sites($request->user())->whereKey($id)->whereHas('healthFacility', fn ($q) => $q->where('organization_id', $organization->id))->firstOrFail(); }
}
