<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Http\Requests\SaveProjectRequest;
use App\Services\AuditService;
use App\Services\GovernanceService;
use App\Services\ProjectProvisioningService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    private const AUDITED = ['mission_id', 'code', 'name', 'implementing_partner', 'donor_reference_code', 'moh_program_code', 'responsible_name', 'responsible_contact', 'description', 'starts_on', 'ends_on', 'order_period_months', 'delivery_lead_time_months', 'safety_stock_months', 'status', 'is_active'];

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

    public function show(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json([
            'project' => $project->load([
                'organization:id,code,name',
                'mission.country:id,iso2,name',
                'donors:id,code,name',
                'programs:id,code,name',
            ]),
        ]);
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

    public function store(SaveProjectRequest $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $result = $this->provisioning->create($organization, $request->projectData(), $request->user()->id);
        $project = $result['project'];
        $this->audit->record($request, 'project.created', $project, [], $project->only(['organization_id', 'mission_id', 'code', 'name', 'status', 'is_active']));

        return response()->json(['project' => $project, 'admin_project' => $result['admin']], 201);
    }

    public function update(SaveProjectRequest $request, Organization $organization, Project $project): JsonResponse
    {
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        Gate::authorize('update', $project);
        $old = $project->only(self::AUDITED);
        $project = $this->provisioning->update($project, $request->projectData(), $request->user()->id);
        $this->audit->record($request, 'project.updated', $project, $old, $project->only(array_keys($old)));

        return response()->json(['project' => $project]);
    }

    public function destroy(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        Gate::authorize('delete', $project);
        $project->delete();
        $this->audit->record($request, 'project.archived', $project);

        return response()->json(status: 204);
    }

    public function restore(Request $request, Organization $organization, string $project): JsonResponse
    {
        $this->accessible($request, $organization);
        $model = $organization->projects()->onlyTrashed()->findOrFail($project);
        Gate::authorize('restore', $model);
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, 'project.restored', $model);

        return response()->json(['project' => $model->load('mission.country')]);
    }


    /**
     * DEC-08 — « Appliquer à toutes les FOSA » : recopie la périodicité, le délai
     * et le stock de sécurité du projet dans ses FOSA, avec confirmation
     * explicite. Jamais déclenché automatiquement par une modification du projet.
     */
    public function applySupplyToFacilities(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);
        $request->validate(['confirm' => ['required', 'accepted']], ['confirm.accepted' => 'Confirmez l’application des paramètres du projet à toutes ses FOSA.']);
        $updated = app(\App\Services\HealthFacilityConfigurationService::class)->applyProjectSupplyToFacilities($project, $request->user());
        $this->audit->record($request, 'project.supply_applied_to_facilities', $project, [], ['facilities_updated' => $updated]);

        return response()->json(['facilities_updated' => $updated]);
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }
}
