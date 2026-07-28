<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function index(Request $request): View
    {
        $this->allow('organizations.view');
        $organizations = $this->scopes->organizations($request->user())
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20);
        $archivedOrganizations = $this->scopes->archivedOrganizations($request->user())->latest('deleted_at')->get();
        return view('organizations.index', compact('organizations', 'archivedOrganizations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('organizations.manage');
        $organization = Organization::create($this->validated($request));
        $this->audit->record($request, 'organization.created', $organization, [], $organization->only(['code', 'name', 'is_active']));
        return back()->with('status', 'Organisation créée.');
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->allow('organizations.manage');
        $this->accessible($request, $organization);
        $old = $organization->only(['code', 'name', 'legal_name', 'email', 'phone', 'country_code', 'address', 'is_active']);
        $organization->update($this->validated($request, $organization));
        $this->audit->record($request, 'organization.updated', $organization, $old, $organization->only(array_keys($old)));
        return back()->with('status', 'Organisation mise à jour.');
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $this->allow('organizations.manage');
        $this->accessible($request, $organization);
        $organization->update(['is_active' => false]);
        $organization->delete();
        $this->audit->record($request, 'organization.archived', $organization);
        return back()->with('status', 'Organisation archivée.');
    }

    public function restore(Request $request, string $organization): RedirectResponse
    {
        $this->allow('organizations.manage');
        $model = $this->scopes->archivedOrganizations($request->user())->findOrFail($organization);
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, 'organization.restored', $model);
        return back()->with('status', 'Organisation restaurée.');
    }

    private function validated(Request $request, ?Organization $organization = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('organizations')->ignore($organization?->id)],
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : null;
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
