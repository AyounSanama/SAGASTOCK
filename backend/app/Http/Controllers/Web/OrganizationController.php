<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): View
    {
        $this->allow('organizations.view');
        $organizations = Organization::query()
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20);
        return view('organizations.index', compact('organizations'));
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
        $old = $organization->only(['code', 'name', 'legal_name', 'email', 'phone', 'country_code', 'address', 'is_active']);
        $organization->update($this->validated($request, $organization));
        $this->audit->record($request, 'organization.updated', $organization, $old, $organization->only(array_keys($old)));
        return back()->with('status', 'Organisation mise à jour.');
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $this->allow('organizations.manage');
        $organization->delete();
        $this->audit->record($request, 'organization.archived', $organization);
        return back()->with('status', 'Organisation archivée.');
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
}
