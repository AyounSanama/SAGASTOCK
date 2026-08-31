<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CoordinationProvisioningService;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use App\Support\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class OrganizationController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserScopeService $scopes,
        private CoordinationProvisioningService $coordinations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizations = $this->scopes->organizations($request->user())
            ->withCount('missions')
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')->paginate(20);

        return response()->json($organizations);
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);

        return response()->json(['organization' => $organization->load('countries:id,iso2,name')]);
    }

    public function archived(Request $request): JsonResponse
    {
        return response()->json($this->scopes->archivedOrganizations($request->user())->latest('deleted_at')->paginate(20));
    }

    public function restore(Request $request, string $organization): JsonResponse
    {
        $this->owner($request);
        $model = $this->scopes->archivedOrganizations($request->user())->findOrFail($organization);
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, 'organization.restored', $model);

        return response()->json(['organization' => $model]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->owner($request);
        [$organizationData, $countryIds, $adminData] = $this->creationData($request);
        $logoPath = $request->file('logo')?->store('organizations', 'public');
        if ($logoPath) {
            $organizationData['logo_path'] = $logoPath;
        }
        try {
            [$organization, $user, $temporaryPassword] = DB::transaction(function () use ($request, $organizationData, $countryIds, $adminData): array {
                $organization = Organization::create($organizationData);
                $organization->countries()->sync($countryIds);
                $missions = $this->coordinations->provision($organization, $countryIds);
                $primaryCountryId = $organization->geographic_access_type === 'single_country'
                    ? $countryIds[0]
                    : $adminData['admin_country_id'];
                $temporaryPassword = ($adminData['activation_mode'] ?? 'invitation') === 'temporary_password'
                    ? ($adminData['admin_password'] ?? Str::password(16, symbols: true))
                    : Str::password(16, symbols: true);
                $user = User::create([
                    'organization_id' => $organization->id,
                    'name' => trim($adminData['admin_first_name'].' '.$adminData['admin_last_name']),
                    'first_name' => $adminData['admin_first_name'], 'last_name' => $adminData['admin_last_name'],
                    'email' => $adminData['admin_email'], 'phone' => $adminData['admin_phone'] ?? null,
                    'username' => strtolower($adminData['admin_username']), 'password' => $temporaryPassword,
                    'is_active' => ($adminData['admin_status'] ?? 'active') === 'active', 'must_change_password' => true,
                ]);
                $role = Role::where('code', GovernanceService::COORDINATION_ADMIN)->where('is_active', true)->firstOrFail();
                $user->roles()->attach($role, ['scope_type' => 'mission', 'scope_id' => $missions->get($primaryCountryId)->id]);
                $this->audit->record($request, 'organization.created', $organization, [], $organization->only(['code', 'name', 'is_active']));

                return [$organization, $user, $temporaryPassword];
            });
        } catch (Throwable $exception) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            throw $exception;
        }

        return response()->json([
            'organization' => $organization->load('countries'),
            'coordination_admin' => $user,
            'message' => 'Compte créé avec succès. L’utilisateur devra modifier son mot de passe lors de sa première connexion.',
        ], 201);
    }

    private function creationData(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', 'unique:organizations,code'],
            'name' => ['required', 'string', 'max:160'],
            'organization_type' => ['nullable', Rule::in(['ngo', 'ministry', 'national_program', 'united_nations', 'international_agency', 'other'])],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'geographic_access_type' => ['required', Rule::in(['single_country', 'multi_country'])],
            'country_ids' => ['required', 'array', 'min:1'], 'country_ids.*' => ['uuid', 'distinct', 'exists:countries,id'],
            'admin_first_name' => ['required', 'string', 'max:80'], 'admin_last_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'admin_phone' => ['nullable', 'string', 'max:40'],
            'admin_username' => ['required', 'alpha_dash', 'max:80', 'unique:users,username'],
            'activation_mode' => ['nullable', Rule::in(['temporary_password', 'invitation'])],
            'admin_password' => [Rule::requiredIf($request->input('activation_mode') === 'temporary_password'), 'nullable', 'string', PasswordPolicy::rule()],
            'admin_status' => ['nullable', Rule::in(['active', 'inactive'])],
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
        $country = Country::findOrFail($data['country_ids'][0]);
        $organizationData = collect($data)->only(['code', 'name', 'email', 'phone', 'geographic_access_type'])->all();
        $organizationData['organization_type'] = $data['organization_type'] ?? 'other';
        $organizationData['default_language'] = $data['default_language'] ?? 'fr';
        $organizationData['country_code'] = $data['geographic_access_type'] === 'single_country' ? $country->iso2 : null;
        $organizationData['is_active'] = ($data['status'] ?? 'active') === 'active';

        return [$organizationData, $data['country_ids'], $data];
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->owner($request);
        $this->accessible($request, $organization);
        $old = $organization->only(['code', 'name', 'legal_name', 'email', 'phone', 'country_code', 'address', 'is_active']);
        $organization->update($this->validated($request, $organization));
        $this->audit->record($request, 'organization.updated', $organization, $old, $organization->only(array_keys($old)));

        return response()->json(['organization' => $organization]);
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->owner($request);
        $this->accessible($request, $organization);
        $organization->update(['is_active' => false]);
        $organization->delete();
        $this->audit->record($request, 'organization.archived', $organization);

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?Organization $organization = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('organizations')->ignore($organization?->id)],
            'name' => ['required', 'string', 'max:160'],
            'organization_type' => ['nullable', Rule::in([
                'ngo', 'ministry', 'national_program', 'united_nations',
                'international_agency', 'other',
            ])],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:1000'],
            'manager_name' => ['nullable', 'string', 'max:160'],
            'manager_title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['country_code'])) {
            $data['country_code'] = strtoupper($data['country_code']);
        }

        return $data;
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }

    private function owner(Request $request): void
    {
        abort_unless(
            app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::SAGO_ADMIN
                && $this->scopes->isPlatform($request->user()),
            403,
        );
    }
}
