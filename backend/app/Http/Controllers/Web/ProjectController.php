<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Http\Requests\SaveProjectRequest;
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
    private const AUDITED = ['mission_id', 'code', 'name', 'implementing_partner', 'donor_reference_code', 'moh_program_code', 'responsible_name', 'responsible_contact', 'description', 'starts_on', 'ends_on', 'order_period_months', 'delivery_lead_time_months', 'safety_stock_months', 'status', 'is_active'];

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

    public function store(SaveProjectRequest $request, Organization $organization): RedirectResponse
    {
        $this->allow('projects.manage');
        $this->accessible($request, $organization);
        $result = $this->provisioning->create($organization, $request->projectData(), $request->user()->id);
        $project = $result['project'];
        $this->audit->record($request, 'project.created', $project, [], $project->only(['organization_id', 'mission_id', 'code', 'name', 'status', 'is_active']));

        // Configuration Mission : la création enchaîne sur la configuration
        // de la Liste Standard (niveaux de soins, populations, pathologies).
        return redirect()->route('projects.medical-configuration', $project)
            ->with('status', $result['admin'] ? 'Projet et premier Admin Projet créés avec succès.' : 'Projet créé avec succès.');
    }

    public function update(SaveProjectRequest $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->allow('projects.manage');
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($this->scopes->projects($request->user())->whereKey($project->id)->exists(), 404);
        $old = $project->only(self::AUDITED);
        $project = $this->provisioning->update($project, $request->projectData(), $request->user()->id);
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


    private function allow(string $permission): void
    {
        abort_unless(Auth::user()?->hasPermission($permission), 403);
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }
}
