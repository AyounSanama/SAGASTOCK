<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MissionController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function home(Request $request): View
    {
        $this->allow('missions.view');
        $organizations = $this->scopes->organizations($request->user())->orderBy('name')->get();
        $organization = $request->filled('organization_id')
            ? $organizations->firstWhere('id', $request->string('organization_id')->toString())
            : ($organizations->firstWhere('id', $request->user()->organization_id) ?? $organizations->first());
        abort_if(! $organization, $request->filled('organization_id') ? 404 : 403);

        return $this->index($request, $organization);
    }

    public function index(Request $request, Organization $organization): View
    {
        $this->allow('missions.view');
        $this->accessible($request, $organization);
        $missionBase = $organization->missions();
        return view('missions.index', [
            'organization' => $organization,
            'organizations' => $this->scopes->organizations($request->user())->orderBy('name')->get(),
            'countries' => $organization->countries()->where('countries.is_active', true)->orderBy('countries.name')->get(),
            'missionStats' => [
                'total' => (clone $missionBase)->count(),
                'active' => (clone $missionBase)->where('is_active', true)->count(),
                'countries' => (clone $missionBase)->distinct()->count('country_id'),
                'projects' => $organization->projects()->count(),
            ],
            'missions' => $organization->missions()->with('country')->withCount('projects')
                ->when($request->string('search')->toString(), fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
                ->when($request->filled('country_id'), fn ($query) => $query->where('country_id', $request->string('country_id')->toString()))
                ->when($request->get('status') === 'active', fn ($query) => $query->where('is_active', true))
                ->when($request->get('status') === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('name')->paginate(12)->withQueryString(),
            'archivedMissions' => $organization->missions()->onlyTrashed()->with('country')->latest('deleted_at')->get(),
            'canManage' => $request->user()->hasPermission('missions.manage'),
        ]);
    }

    public function storeCountry(Request $request): RedirectResponse
    {
        $this->allow('missions.manage');
        $data = $request->validate([
            'iso2' => ['required', 'string', 'size:2', 'unique:countries,iso2'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        $country = Country::create([...$data, 'iso2' => strtoupper($data['iso2']), 'is_active' => true]);
        $this->audit->record($request, 'country.created', $country, [], $country->only(['iso2', 'name']));
        return back()->with('status', 'Pays ajouté.');
    }

    public function show(Request $request, Organization $organization, Mission $mission): View
    {
        $this->allow('missions.view');
        $this->accessible($request, $organization);
        abort_unless($mission->organization_id === $organization->id, 404);

        $projectIds = $mission->projects()->pluck('id');
        return view('missions.show', [
            'organization' => $organization,
            'mission' => $mission->load('country'),
            'projects' => $mission->projects()->orderBy('name')->get(),
            'archivedProjects' => $mission->projects()->onlyTrashed()->latest('deleted_at')->get(),
            'canManageProjects' => $request->user()->hasPermission('projects.manage'),
            'canManageProjectAdmins' => $request->user()->hasPermission('users.manage'),
            'projectAdminRole' => Role::where('code', 'project_admin')->where('is_active', true)->first(),
            'projectAdmins' => User::with(['roles' => fn ($query) => $query
                ->where('roles.code', 'project_admin')->wherePivot('scope_type', 'project')->wherePivotIn('scope_id', $projectIds)])
                ->where('organization_id', $organization->id)
                ->whereHas('roles', fn ($query) => $query->where('roles.code', 'project_admin')
                    ->where('role_user.scope_type', 'project')->whereIn('role_user.scope_id', $projectIds))
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->allow('missions.manage');
        $this->accessible($request, $organization);
        $mission = $organization->missions()->create($this->validated($request, $organization));
        $this->audit->record($request, 'mission.created', $mission, [], $mission->only(['organization_id', 'country_id', 'code', 'name', 'is_active']));
        return back()->with('status', 'Mission créée.');
    }

    public function update(Request $request, Organization $organization, Mission $mission): RedirectResponse
    {
        $this->allow('missions.manage');
        $this->accessible($request, $organization);
        abort_unless($mission->organization_id === $organization->id, 404);
        $old = $mission->only(['country_id', 'code', 'name', 'starts_on', 'ends_on', 'is_active']);
        $mission->update($this->validated($request, $organization, $mission));
        $this->audit->record($request, 'mission.updated', $mission, $old, $mission->only(array_keys($old)));
        return back()->with('status', 'Mission mise à jour.');
    }

    public function destroy(Request $request, Organization $organization, Mission $mission): RedirectResponse
    {
        $this->allow('missions.manage');
        $this->accessible($request, $organization);
        abort_unless($mission->organization_id === $organization->id, 404);
        $mission->delete();
        $this->audit->record($request, 'mission.archived', $mission);
        return back()->with('status', 'Mission archivée.');
    }

    public function restore(Request $request, Organization $organization, string $mission): RedirectResponse
    {
        $this->allow('missions.manage'); $this->accessible($request, $organization);
        $model = $organization->missions()->onlyTrashed()->findOrFail($mission);
        $model->restore(); $model->update(['is_active' => true]);
        $this->audit->record($request, 'mission.restored', $model);
        return back()->with('status', 'Mission restaurée.');
    }

    private function validated(Request $request, Organization $organization, ?Mission $mission = null): array
    {
        $data = $request->validate([
            'country_id' => [
                'required', 'uuid',
                Rule::exists('country_organization', 'country_id')->where('organization_id', $organization->id),
            ],
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('missions')->where('organization_id', $organization->id)->ignore($mission?->id)],
            'name' => ['required', 'string', 'max:160'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'address' => ['nullable', 'string', 'max:1000'],
            'manager_name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);
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
