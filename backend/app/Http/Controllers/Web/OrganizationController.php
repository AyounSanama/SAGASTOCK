<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CoordinationProvisioningService;
use App\Services\UserScopeService;
use App\Services\GovernanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserScopeService $scopes,
        private CoordinationProvisioningService $coordinations,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->allow('organizations.view');
        if (app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::SAGO_ADMIN) {
            return redirect()->route('configuration.organization', $request->boolean('create') ? ['create' => 1] : []);
        }
        $organizations = $this->scopes->organizations($request->user())->with('countries:id,iso2,name')
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20);
        $archivedOrganizations = $this->scopes->archivedOrganizations($request->user())->latest('deleted_at')->get();
        $countries = Country::where('is_active', true)->orderBy('name')->get(['id', 'iso2', 'name']);
        return view('organizations.index', compact('organizations', 'archivedOrganizations', 'countries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->platformAdmin($request);
        [$organizationData, $countryIds, $adminData] = $this->creationData($request);
        $temporaryPassword = DB::transaction(function () use ($request, $organizationData, $countryIds, $adminData): string {
            $organization = Organization::create($organizationData);
            $organization->countries()->sync($countryIds);
            $missions = $this->coordinations->provision($organization, $countryIds);
            $primaryCountryId = $organization->geographic_access_type === 'single_country'
                ? $countryIds[0]
                : $adminData['admin_country_id'];
            $temporaryPassword = Str::password(16, symbols: true);
            $user = User::create([
                'organization_id' => $organization->id,
                'name' => trim($adminData['admin_first_name'].' '.$adminData['admin_last_name']),
                'first_name' => $adminData['admin_first_name'], 'last_name' => $adminData['admin_last_name'],
                'email' => $adminData['admin_email'], 'phone' => $adminData['admin_phone'] ?? null,
                'username' => strtolower($adminData['admin_username']), 'password' => $temporaryPassword,
                'is_active' => true, 'must_change_password' => true,
            ]);
            $role = Role::where('code', GovernanceService::COORDINATION_ADMIN)->where('is_active', true)->firstOrFail();
            $user->roles()->attach($role, [
                'scope_type' => 'mission',
                'scope_id' => $missions->get($primaryCountryId)->id,
            ]);
            $this->audit->record($request, 'organization.created', $organization, [], $organization->only(['code', 'name', 'is_active']));
            $this->audit->record($request, 'user.created', $user, [], ['role' => GovernanceService::COORDINATION_ADMIN, 'organization_id' => $organization->id]);
            return $temporaryPassword;
        });
        return back()->with('status', 'Organisation et compte Admin Coordination créés avec succès. L’utilisateur devra modifier son mot de passe lors de sa première connexion.');
        /*
        return back()->with('status', 'Organisation créée.');
        */
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->platformAdmin($request);
        $this->accessible($request, $organization);
        $old = $organization->only(['code', 'name', 'legal_name', 'email', 'phone', 'country_code', 'address', 'is_active']);
        $organization->update($this->validated($request, $organization));
        $this->audit->record($request, 'organization.updated', $organization, $old, $organization->only(array_keys($old)));
        return back()->with('status', 'Organisation mise à jour.');
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $this->platformAdmin($request);
        $this->accessible($request, $organization);
        $organization->update(['is_active' => false]);
        $organization->delete();
        $this->audit->record($request, 'organization.archived', $organization);
        return back()->with('status', 'Organisation archivée.');
    }

    public function restore(Request $request, string $organization): RedirectResponse
    {
        $this->platformAdmin($request);
        $model = $this->scopes->archivedOrganizations($request->user())->findOrFail($organization);
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, 'organization.restored', $model);
        return back()->with('status', 'Organisation restaurée.');
    }

    private function creationData(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', 'unique:organizations,code'],
            'name' => ['required', 'string', 'max:160'],
            'organization_type' => ['required', Rule::in(['ngo','ministry','national_program','united_nations','international_agency','other'])],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'], 'is_active' => ['nullable', 'boolean'],
            'geographic_access_type' => ['required', Rule::in(['single_country','multi_country'])],
            'country_ids' => ['required', 'array', 'min:1'], 'country_ids.*' => ['uuid', 'distinct', 'exists:countries,id'],
            'admin_first_name' => ['required', 'string', 'max:80'], 'admin_last_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'admin_phone' => ['nullable', 'string', 'max:40'],
            'admin_username' => ['required', 'alpha_dash', 'max:80', 'unique:users,username'],
            'admin_country_id' => [
                Rule::requiredIf($request->input('geographic_access_type') === 'multi_country'),
                'nullable', 'uuid', 'exists:countries,id',
            ],
        ]);
        if ($data['geographic_access_type'] === 'single_country' && count($data['country_ids']) !== 1) {
            abort(422, 'Une organisation unipays doit avoir exactement un pays.');
        }
        if ($data['geographic_access_type'] === 'multi_country'
            && ! in_array($data['admin_country_id'] ?? null, $data['country_ids'], true)) {
            abort(422, 'La coordination principale doit correspondre à un pays autorisé.');
        }
        $firstCountry = Country::findOrFail($data['country_ids'][0]);
        $organizationData = collect($data)->only(['code','name','organization_type','email','phone','address','geographic_access_type'])->all();
        $organizationData['country_code'] = $data['geographic_access_type'] === 'single_country' ? $firstCountry->iso2 : null;
        $organizationData['is_active'] = $request->boolean('is_active', true);
        return [$organizationData, $data['country_ids'], $data];
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
        if (array_key_exists('country_code', $data)) {
            $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : null;
        }
        $data['is_active'] = $request->boolean('is_active', true);
        return $data;
    }

    private function allow(string $permission): void
    {
        abort_unless(Auth::user()?->hasPermission($permission), 403);
    }

    private function platformAdmin(Request $request): void
    {
        abort_unless(
            app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::SAGO_ADMIN
                && $this->scopes->isPlatform($request->user()),
            403,
        );
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }
}
