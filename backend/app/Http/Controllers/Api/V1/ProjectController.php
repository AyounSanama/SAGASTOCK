<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\GovernanceService;
use App\Services\ProjectProvisioningService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Support\PasswordPolicy;

class ProjectController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserScopeService $scopes,
        private ProjectProvisioningService $provisioning,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $projects = $this->scopes->projects($request->user())->where('organization_id', $organization->id)
            ->with(['organization:id,code,name', 'mission.country:id,iso2,name', 'donors:id,code,name', 'programs:id,code,name', 'administrators:id,name,email,phone'])
            ->when($request->filled('mission_id'), fn ($query) => $query->where('mission_id', $request->string('mission_id')))
            ->when($request->get('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->get('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20);

        return response()->json($projects);
    }

    public function setup(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $missionIds = $this->scopes->coordinationMissionIds($request->user());

        return response()->json([
            'organization' => $organization->only(['id', 'code', 'name']),
            'coordinations' => $organization->missions()->whereIn('id', $missionIds)
                ->with('country:id,iso2,name')->orderBy('name')->get(),
            'donors' => $organization->donors()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'programs' => $organization->programs()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'donor_id']),
            'archived_donors' => $request->user()->hasPermission('funding.manage')
                ? $organization->donors()->onlyTrashed()->latest('deleted_at')->get(['id', 'code', 'name', 'deleted_at'])
                : [],
            'archived_programs' => $request->user()->hasPermission('funding.manage')
                ? $organization->programs()->onlyTrashed()->latest('deleted_at')->get(['id', 'code', 'name', 'donor_id', 'deleted_at'])
                : [],
        ]);
    }

    public function archived(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $projects = $organization->projects()->onlyTrashed()
            ->when(
                app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN,
                fn ($query) => $query->whereIn('mission_id', $this->scopes->coordinationMissionIds($request->user())),
            )
            ->with('mission.country:id,iso2,name')
            ->when($request->filled('mission_id'), fn ($query) => $query->where('mission_id', $request->string('mission_id')))
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->latest('deleted_at')->paginate(20);

        return response()->json($projects);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $result = $this->provisioning->create($organization, $this->createData($request, $organization));
        $project = $result['project'];
        $this->audit->record($request, 'project.created', $project, [], $project->only(['organization_id', 'mission_id', 'code', 'name', 'is_active']));

        return response()->json(['project' => $project, 'admin_project' => $result['admin']], 201);
    }

    public function update(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($this->scopes->projects($request->user())->whereKey($project->id)->exists(), 404);
        $old = $project->only(['mission_id', 'code', 'name', 'description', 'starts_on', 'ends_on', 'is_active']);
        $project = $this->provisioning->update($project, $this->updateData($request, $organization, $project));
        $this->audit->record($request, 'project.updated', $project, $old, $project->only(array_keys($old)));

        return response()->json(['project' => $project]);
    }

    public function destroy(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($this->scopes->projects($request->user())->whereKey($project->id)->exists(), 404);
        $project->delete();
        $this->audit->record($request, 'project.archived', $project);

        return response()->json(status: 204);
    }

    public function restore(Request $request, Organization $organization, string $project): JsonResponse
    {
        $this->accessible($request, $organization);
        $model = $organization->projects()->onlyTrashed()->findOrFail($project);
        if (app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN) {
            abort_unless($this->scopes->coordinationMissionIds($request->user())->contains($model->mission_id), 404);
        }
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, 'project.restored', $model);

        return response()->json(['project' => $model->load('mission.country')]);
    }

    private function validated(Request $request, Organization $organization, ?Project $project = null): array
    {
        $data = $request->validate([
            'mission_id' => ['required', 'uuid', 'exists:missions,id'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('projects')->where('organization_id', $organization->id)->ignore($project?->id)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'order_period_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'delivery_lead_time_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'safety_stock_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $mission = Mission::findOrFail($data['mission_id']);
        abort_unless($mission->organization_id === $organization->id, 422, 'La mission ne dépend pas de cette organisation.');
        if (app(GovernanceService::class)->roleCode(request()->user()) === GovernanceService::COORDINATION_ADMIN) {
            abort_unless($this->scopes->coordinationMissionIds(request()->user())->contains($mission->id), 403);
        }

        return $data;
    }

    private function createData(Request $request, Organization $organization): array
    {
        $data = $this->validated($request, $organization);
        $extra = $request->validate([
            'donor_ids' => ['nullable', 'array'],
            'donor_ids.*' => ['uuid', Rule::exists('donors', 'id')->where('organization_id', $organization->id)],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['uuid', Rule::exists('programs', 'id')->where('organization_id', $organization->id)],
            'admin' => ['nullable', 'array'],
            'admin.first_name' => ['required_with:admin', 'string', 'max:80'],
            'admin.last_name' => ['required_with:admin', 'string', 'max:80'],
            'admin.email' => ['required_with:admin', 'email', 'max:190', 'unique:users,email'],
            'admin.phone' => ['nullable', 'string', 'max:40'],
            'admin.username' => ['nullable', 'alpha_dash', 'max:80', 'unique:users,username'],
            'admin.password' => ['required_with:admin', 'confirmed', PasswordPolicy::rule()],
        ]);

        return [...$data, ...$extra];
    }

    private function updateData(Request $request, Organization $organization, Project $project): array
    {
        return [
            ...$this->validated($request, $organization, $project),
            ...$request->validate([
                'donor_ids' => ['nullable', 'array'],
                'donor_ids.*' => ['uuid', Rule::exists('donors', 'id')->where('organization_id', $organization->id)],
                'program_ids' => ['nullable', 'array'],
                'program_ids.*' => ['uuid', Rule::exists('programs', 'id')->where('organization_id', $organization->id)],
            ]),
        ];
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }
}
