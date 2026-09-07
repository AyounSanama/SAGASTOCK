<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\ModuleActivation;
use App\Models\Organization;
use App\Models\Pharmacy;
use App\Models\Project;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StructureController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->organization($request, $organization);
        $facilityIds = $this->scopes->facilityIds($request->user());
        return response()->json([
            'facilities' => $organization->healthFacilities()->whereIn('id', $facilityIds)->with([
                'mission.country', 'projects:id,name', 'departments', 'archivedDepartments',
                'pharmacies', 'archivedPharmacies', 'sites.department', 'sites.pharmacy', 'archivedSites',
            ])->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
                ->orderBy('name')->paginate(20),
            'archived_facilities' => $organization->healthFacilities()->onlyTrashed()->whereIn('id', $facilityIds)->latest('deleted_at')->get(),
            'sites' => $organization->sites()->whereIn('health_facility_id', $facilityIds)
                ->where('is_active', true)->with('healthFacility:id,name,code,locality')
                ->orderBy('name')->get(),
            'module_activations' => ModuleActivation::where(function ($query) use ($organization) {
                $query->where(fn ($q) => $q->where('target_type', 'organization')->where('target_id', $organization->id))
                    ->orWhere(fn ($q) => $q->where('target_type', 'project')->whereIn('target_id', $organization->projects()->pluck('id')))
                    ->orWhere(fn ($q) => $q->where('target_type', 'facility')->whereIn('target_id', $organization->healthFacilities()->pluck('id')));
            })->get(),
        ]);
    }

    public function storeFacility(Request $request, Organization $organization): JsonResponse
    {
        $this->organization($request, $organization);
        $data = $this->facilityData($request, $organization);
        $projectIds = $data['project_ids'] ?? [];
        unset($data['project_ids']);
        $facility = DB::transaction(function () use ($organization, $data, $projectIds) {
            $facility = $organization->healthFacilities()->create($data);
            $facility->projects()->sync($projectIds);

            return $facility;
        });
        $this->audit->record($request, 'facility.created', $facility, [], $facility->only(['organization_id', 'code', 'name', 'facility_type']));
        return response()->json(['facility' => $facility->load(['organization:id,code,name', 'mission.country', 'projects:id,organization_id,mission_id,code,name'])], 201);
    }

    public function showFacility(Request $request, Organization $organization, HealthFacility $facility): JsonResponse
    {
        $this->facility($request, $organization, $facility);

        return response()->json(['facility' => $facility->load([
            'organization:id,code,name', 'mission.country', 'projects:id,organization_id,mission_id,code,name',
            'departments', 'pharmacies', 'sites.department', 'sites.pharmacy',
        ])]);
    }

    public function updateFacility(Request $request, Organization $organization, HealthFacility $facility): JsonResponse
    {
        $this->facility($request, $organization, $facility);
        $data = $this->facilityData($request, $organization, $facility);
        $projectIds = $data['project_ids'] ?? [];
        unset($data['project_ids']);
        $old = $facility->toArray();
        $facility->update($data);
        $facility->projects()->sync($projectIds);
        $this->audit->record($request, 'facility.updated', $facility, $old, $facility->fresh()->toArray());
        return response()->json(['facility' => $facility->load(['mission', 'projects'])]);
    }

    public function archiveFacility(Request $request, Organization $organization, HealthFacility $facility): JsonResponse
    {
        $this->facility($request, $organization, $facility);
        $facility->update(['is_active' => false]);
        $facility->delete();
        $this->audit->record($request, 'facility.archived', $facility);
        return response()->json(status: 204);
    }

    public function restoreFacility(Request $request, Organization $organization, string $facility): JsonResponse
    {
        $this->organization($request, $organization);
        $model = $organization->healthFacilities()->onlyTrashed()->findOrFail($facility);
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, 'facility.restored', $model);
        return response()->json(['facility' => $model]);
    }

    public function storeDepartment(Request $request, Organization $organization, HealthFacility $facility): JsonResponse
    {
        $this->facility($request, $organization, $facility);
        $department = $facility->departments()->create($this->departmentData($request, $facility));
        return $this->created($request, 'department.created', $department, 'department');
    }

    public function updateDepartment(Request $request, Organization $organization, HealthFacility $facility, Department $department): JsonResponse
    {
        $this->child($request, $organization, $facility, $department);
        $department->update($this->departmentData($request, $facility, $department));
        $this->audit->record($request, 'department.updated', $department);
        return response()->json(['department' => $department]);
    }

    public function archiveDepartment(Request $request, Organization $organization, HealthFacility $facility, Department $department): JsonResponse
    {
        return $this->archiveChild($request, $organization, $facility, $department, 'department.archived');
    }
    public function restoreDepartment(Request $request, Organization $organization, HealthFacility $facility, string $department): JsonResponse
    { return $this->restoreChild($request, $organization, $facility, Department::onlyTrashed()->findOrFail($department), 'department.restored', 'department'); }

    public function storePharmacy(Request $request, Organization $organization, HealthFacility $facility): JsonResponse
    {
        $this->facility($request, $organization, $facility);
        $pharmacy = $facility->pharmacies()->create($this->pharmacyData($request, $facility));
        return $this->created($request, 'pharmacy.created', $pharmacy, 'pharmacy');
    }

    public function updatePharmacy(Request $request, Organization $organization, HealthFacility $facility, Pharmacy $pharmacy): JsonResponse
    {
        $this->child($request, $organization, $facility, $pharmacy);
        $pharmacy->update($this->pharmacyData($request, $facility, $pharmacy));
        $this->audit->record($request, 'pharmacy.updated', $pharmacy);
        return response()->json(['pharmacy' => $pharmacy]);
    }

    public function archivePharmacy(Request $request, Organization $organization, HealthFacility $facility, Pharmacy $pharmacy): JsonResponse
    {
        return $this->archiveChild($request, $organization, $facility, $pharmacy, 'pharmacy.archived');
    }
    public function restorePharmacy(Request $request, Organization $organization, HealthFacility $facility, string $pharmacy): JsonResponse
    { return $this->restoreChild($request, $organization, $facility, Pharmacy::onlyTrashed()->findOrFail($pharmacy), 'pharmacy.restored', 'pharmacy'); }

    public function storeSite(Request $request, Organization $organization, HealthFacility $facility): JsonResponse
    {
        $this->organization($request, $organization);
        abort_unless(
            $facility->organization_id === $organization->id
                && $this->scopes->facilityIds($request->user())->contains($facility->id),
            403,
            'Cette formation sanitaire ne fait pas partie de votre projet.',
        );
        $site = $facility->sites()->create([
            ...$this->siteData($request, $facility),
            'organization_id' => $organization->id,
        ]);
        return $this->created($request, 'site.created', $site, 'site');
    }

    public function showSite(Request $request, Organization $organization, HealthFacility $facility, Site $site): JsonResponse
    {
        $this->child($request, $organization, $facility, $site);

        return response()->json(['site' => $site->load('healthFacility.projects:id,name')]);
    }

    public function updateSite(Request $request, Organization $organization, HealthFacility $facility, Site $site): JsonResponse
    {
        $this->child($request, $organization, $facility, $site);
        $site->update($this->siteData($request, $facility, $site));
        $this->audit->record($request, 'site.updated', $site);
        return response()->json(['site' => $site]);
    }

    public function archiveSite(Request $request, Organization $organization, HealthFacility $facility, Site $site): JsonResponse
    {
        return $this->archiveChild($request, $organization, $facility, $site, 'site.archived');
    }
    public function restoreSite(Request $request, Organization $organization, HealthFacility $facility, string $site): JsonResponse
    { return $this->restoreChild($request, $organization, $facility, Site::onlyTrashed()->findOrFail($site), 'site.restored', 'site'); }

    public function activation(Request $request, Organization $organization): JsonResponse
    {
        $this->organization($request, $organization);
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['organization', 'project', 'facility'])],
            'target_id' => ['required', 'uuid'],
            'module_code' => ['required', Rule::in(['references', 'stocks', 'receptions', 'dispensing', 'inventories', 'orders', 'alerts', 'reports'])],
            'is_enabled' => ['required', 'boolean'],
        ]);
        $this->validateTarget($organization, $data['target_type'], $data['target_id']);
        $activation = ModuleActivation::updateOrCreate(
            collect($data)->only(['target_type', 'target_id', 'module_code'])->all(),
            ['is_enabled' => $data['is_enabled'], 'updated_by' => $request->user()->id],
        );
        $this->audit->record($request, 'module.activation_updated', $activation, [], $activation->toArray());
        return response()->json(['activation' => $activation]);
    }

    private function organization(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }

    private function facility(Request $request, Organization $organization, HealthFacility $facility): void
    {
        $this->organization($request, $organization);
        abort_unless(
            $facility->organization_id === $organization->id
                && $this->scopes->facilityIds($request->user())->contains($facility->id),
            404,
        );
    }

    private function child(Request $request, Organization $organization, HealthFacility $facility, Model $child): void
    {
        $this->facility($request, $organization, $facility);
        abort_unless($child->health_facility_id === $facility->id, 404);
    }

    private function facilityData(Request $request, Organization $organization, ?HealthFacility $facility = null): array
    {
        $data = $request->validate([
            'mission_id' => ['nullable', 'uuid', 'exists:missions,id'],
            'project_ids' => ['nullable', 'array'], 'project_ids.*' => ['uuid', 'exists:projects,id'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('health_facilities')->where('organization_id', $organization->id)->ignore($facility?->id)],
            'name' => ['required', 'string', 'max:180'],
            'facility_type' => ['required', Rule::in(['hospital', 'health_center', 'clinic', 'warehouse', 'community', 'other'])],
            'care_level' => ['nullable', Rule::in(['primary', 'secondary', 'tertiary', 'national'])], 'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000'],
            'region' => ['nullable', 'string', 'max:120'], 'district' => ['nullable', 'string', 'max:120'],
            'locality' => ['nullable', 'string', 'max:190'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (app(\App\Services\GovernanceService::class)->roleCode($request->user()) === \App\Services\GovernanceService::PROJECT_ADMIN) {
            $projectIds = $this->scopes->directProjectIds($request->user())->unique()->values();
            abort_unless($projectIds->count() === 1, 403, 'Le compte Admin Projet doit être rattaché à un projet unique.');
            $project = Project::whereKey($projectIds->first())
                ->where('organization_id', $organization->id)
                ->firstOrFail();
            $data['project_ids'] = [$project->id];
            $data['mission_id'] = $project->mission_id;
        }
        if (!empty($data['mission_id'])) abort_unless(Mission::whereKey($data['mission_id'])->where('organization_id', $organization->id)->exists(), 422);
        if (!empty($data['project_ids'])) {
            $projectIds = collect($data['project_ids'])->unique();
            abort_unless(
                Project::whereIn('id', $projectIds)->where('organization_id', $organization->id)->count() === $projectIds->count()
                    && $projectIds->every(fn (string $id) => $this->scopes->projectIds($request->user())->contains($id)),
                422,
                'Un projet sélectionné ne fait pas partie de votre périmètre.',
            );
        }
        return $data;
    }

    private function departmentData(Request $request, HealthFacility $facility, ?Department $department = null): array
    {
        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('departments')->where('health_facility_id', $facility->id)->ignore($department?->id)],
            'name' => ['required', 'string', 'max:160'],
            'department_type' => ['required', Rule::in(['clinical', 'pharmacy', 'laboratory', 'logistics', 'administration', 'other'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function pharmacyData(Request $request, HealthFacility $facility, ?Pharmacy $pharmacy = null): array
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('pharmacies')->where('health_facility_id', $facility->id)->ignore($pharmacy?->id)],
            'name' => ['required', 'string', 'max:160'],
            'pharmacy_type' => ['required', Rule::in(['central', 'hospital', 'dispensary', 'community', 'other'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (!empty($data['department_id'])) abort_unless($facility->departments()->whereKey($data['department_id'])->exists(), 422);
        return $data;
    }

    private function siteData(Request $request, HealthFacility $facility, ?Site $site = null): array
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'], 'pharmacy_id' => ['nullable', 'uuid', 'exists:pharmacies,id'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('sites')->where('health_facility_id', $facility->id)->ignore($site?->id)],
            'name' => ['required', 'string', 'max:160'],
            'site_type' => ['required', Rule::in(['stock', 'dispensing', 'stock_and_dispensing', 'quarantine', 'other'])],
            'location' => ['nullable', 'string', 'max:190'], 'is_active' => ['sometimes', 'boolean'],
        ]);
        if (!empty($data['department_id'])) abort_unless($facility->departments()->whereKey($data['department_id'])->exists(), 422);
        if (!empty($data['pharmacy_id'])) abort_unless($facility->pharmacies()->whereKey($data['pharmacy_id'])->exists(), 422);
        return $data;
    }

    private function validateTarget(Organization $organization, string $type, string $id): void
    {
        $valid = match ($type) {
            'organization' => $organization->id === $id,
            'project' => $organization->projects()->whereKey($id)->exists(),
            'facility' => $organization->healthFacilities()->whereKey($id)->exists(),
        };
        abort_unless($valid, 422, 'La cible ne dépend pas de cette organisation.');
    }

    private function created(Request $request, string $event, Model $model, string $key): JsonResponse
    {
        $this->audit->record($request, $event, $model, [], $model->toArray());
        return response()->json([$key => $model], 201);
    }

    private function archiveChild(Request $request, Organization $organization, HealthFacility $facility, Model $model, string $event): JsonResponse
    {
        $this->child($request, $organization, $facility, $model);
        $model->update(['is_active' => false]);
        $model->delete();
        $this->audit->record($request, $event, $model);
        return response()->json(status: 204);
    }

    private function restoreChild(Request $request, Organization $organization, HealthFacility $facility, Model $model, string $event, string $key): JsonResponse
    {
        $this->child($request, $organization, $facility, $model);
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, $event, $model);
        return response()->json([$key => $model]);
    }
}
