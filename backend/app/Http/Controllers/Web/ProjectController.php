<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function home(Request $request): View
    {
        $this->allow('projects.view');
        $projects = $this->scopes->projects($request->user())
            ->with(['organization:id,name', 'mission.country:id,iso2,name', 'healthFacilities:id,name,code'])
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->get();

        return view('projects.scope', ['projects' => $projects]);
    }

    public function index(Request $request, Organization $organization): View
    {
        $this->allow('projects.view');
        $this->accessible($request, $organization);
        return view('projects.index', [
            'organization' => $organization,
            'missions' => $organization->missions()->with('country')->orderBy('name')->get(),
            'projects' => $this->scopes->projects($request->user())->where('organization_id', $organization->id)
                ->with('mission.country')->orderBy('name')->paginate(20),
            'archivedProjects' => $organization->projects()->onlyTrashed()->with('mission.country')->latest('deleted_at')->get(),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->allow('projects.manage');
        $this->accessible($request, $organization);
        $project = $organization->projects()->create($this->validated($request, $organization));
        $this->audit->record($request, 'project.created', $project, [], $project->only(['organization_id', 'mission_id', 'code', 'name', 'is_active']));
        return back()->with('status', 'Projet créé.');
    }

    public function update(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->allow('projects.manage');
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($this->scopes->projects($request->user())->whereKey($project->id)->exists(), 404);
        $old = $project->only(['mission_id', 'code', 'name', 'description', 'starts_on', 'ends_on', 'is_active']);
        $project->update($this->validated($request, $organization, $project));
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
        $this->allow('projects.manage'); $this->accessible($request, $organization);
        $model = $organization->projects()->onlyTrashed()->findOrFail($project);
        $model->restore(); $model->update(['is_active' => true]);
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
        ]);
        $mission = Mission::findOrFail($data['mission_id']);
        abort_unless($mission->organization_id === $organization->id, 422, 'La mission ne dépend pas de cette organisation.');
        $data['is_active'] = $request->boolean('is_active', true);
        return $data;
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
