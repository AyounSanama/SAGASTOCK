<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
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

    public function index(Request $request, Organization $organization): View
    {
        $this->allow('missions.view');
        $this->accessible($request, $organization);
        return view('missions.index', [
            'organization' => $organization,
            'countries' => Country::orderBy('name')->get(),
            'missions' => $organization->missions()->with('country')->orderBy('name')->paginate(20),
            'archivedMissions' => $organization->missions()->onlyTrashed()->with('country')->latest('deleted_at')->get(),
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
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('missions')->where('organization_id', $organization->id)->ignore($mission?->id)],
            'name' => ['required', 'string', 'max:160'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
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
