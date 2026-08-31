<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\GovernanceService;
use App\Services\ProjectProvisioningService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Support\PasswordPolicy;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserScopeService $scopes,
        private ProjectProvisioningService $provisioning,
    ) {}

    public function home(Request $request): View
    {
        $this->allow('projects.view');
        $projects = $this->scopes->projects($request->user())
            ->with(['organization:id,name', 'mission.country:id,iso2,name', 'healthFacilities:id,name,code', 'donors:id,code,name', 'programs:id,code,name', 'administrators:id,name,email'])
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($request->get('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->get('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')->get();

        $organization = $this->scopes->organizations($request->user())->orderBy('name')->first();
        $missionIds = $this->scopes->coordinationMissionIds($request->user());
        $coordination = $organization?->missions()->whereIn('id', $missionIds)->with('country')->first();
        $canCreateProject = app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN
            && $request->user()->hasPermission('projects.manage')
            && $organization !== null
            && $coordination !== null;
        $projectMode = app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::PROJECT_ADMIN;

        return view('projects.scope', [
            'projects' => $projects,
            'organization' => $organization,
            'coordination' => $coordination,
            'canCreateProject' => $canCreateProject,
            'projectMode' => $projectMode,
            'donors' => $organization?->donors()->where('is_active', true)->orderBy('name')->get() ?? collect(),
            'programs' => $organization?->programs()->where('is_active', true)->orderBy('name')->get() ?? collect(),
            'archivedProjects' => $organization?->projects()->onlyTrashed()->whereIn('mission_id', $missionIds)->latest('deleted_at')->get() ?? collect(),
        ]);
    }

    public function index(Request $request, Organization $organization): View
    {
        $this->allow('projects.view');
        $this->accessible($request, $organization);

        return view('projects.index', [
            'organization' => $organization,
            'missions' => $organization->missions()
                ->when(
                    app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN,
                    fn ($query) => $query->whereIn('id', $this->scopes->coordinationMissionIds($request->user())),
                )
                ->with('country')->orderBy('name')->get(),
            'projects' => $this->scopes->projects($request->user())->where('organization_id', $organization->id)
                ->with('mission.country')->orderBy('name')->paginate(20),
            'archivedProjects' => $organization->projects()->onlyTrashed()
                ->when(
                    app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN,
                    fn ($query) => $query->whereIn('mission_id', $this->scopes->coordinationMissionIds($request->user())),
                )
                ->with('mission.country')->latest('deleted_at')->get(),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->allow('projects.manage');
        $this->accessible($request, $organization);
        $result = $this->provisioning->create($organization, $this->createData($request, $organization));
        $project = $result['project'];
        $this->audit->record($request, 'project.created', $project, [], $project->only(['organization_id', 'mission_id', 'code', 'name', 'is_active']));

        return back()->with('status', 'Projet et premier Admin Projet créés avec succès.');
    }

    public function update(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->allow('projects.manage');
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($this->scopes->projects($request->user())->whereKey($project->id)->exists(), 404);
        $old = $project->only(['mission_id', 'code', 'name', 'description', 'starts_on', 'ends_on', 'is_active']);
        $project = $this->provisioning->update($project, $this->updateData($request, $organization, $project));
        $this->audit->record($request, 'project.updated', $project, $old, $project->only(array_keys($old)));

        return back()->with('status', 'Projet mis à jour.');
    }

    public function destroy(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->allow('projects.manage');
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($this->scopes->projects($request->user())->whereKey($project->id)->exists(), 404);
        $project->delete();
        $this->audit->record($request, 'project.archived', $project);

        return back()->with('status', 'Projet archivé.');
    }

    public function restore(Request $request, Organization $organization, string $project): RedirectResponse
    {
        $this->allow('projects.manage');
        $this->accessible($request, $organization);
        $model = $organization->projects()->onlyTrashed()->findOrFail($project);
        if (app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN) {
            abort_unless($this->scopes->coordinationMissionIds($request->user())->contains($model->mission_id), 404);
        }
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, 'project.restored', $model);

        return back()->with('status', 'Projet restauré.');
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
        ]);
        $mission = Mission::findOrFail($data['mission_id']);
        abort_unless($mission->organization_id === $organization->id, 422, 'La mission ne dépend pas de cette organisation.');
        if (app(GovernanceService::class)->roleCode(request()->user()) === GovernanceService::COORDINATION_ADMIN) {
            abort_unless($this->scopes->coordinationMissionIds(request()->user())->contains($mission->id), 403);
        }
        $data['is_active'] = $request->boolean('is_active', true);

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
            'admin' => ['required', 'array'],
            'admin.first_name' => ['required', 'string', 'max:80'],
            'admin.last_name' => ['required', 'string', 'max:80'],
            'admin.email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'admin.phone' => ['nullable', 'string', 'max:40'],
            'admin.username' => ['nullable', 'alpha_dash', 'max:80', 'unique:users,username'],
            'admin.password' => ['required', 'confirmed', PasswordPolicy::rule()],
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

    private function allow(string $permission): void
    {
        abort_unless(Auth::user()?->hasPermission($permission), 403);
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }
}
