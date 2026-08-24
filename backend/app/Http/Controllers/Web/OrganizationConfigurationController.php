<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Role;
use App\Models\SetupProgress;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ConfigurationWorkflowService;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationConfigurationController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly UserScopeService $scopes,
        private readonly ConfigurationWorkflowService $workflows,
    ) {}

    public function show(Request $request): View
    {
        $this->authorizeViewing($request);
        $organizations = $this->scopes->organizations($request->user())
            ->with(['countries', 'users' => fn ($query) => $query->whereHas(
                'roles', fn ($roles) => $roles->where('code', GovernanceService::COORDINATION_ADMIN),
            )->oldest()])
            ->withCount(['missions', 'projects' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get();
        $editing = $request->filled('edit')
            ? $organizations->firstWhere('id', $request->input('edit')) ?? abort(404)
            : null;

        return view('configuration.organizations', [
            'activeModule' => 'configuration',
            'organizations' => $organizations,
            'editingOrganization' => $editing,
            'canManageOrganizations' => app(GovernanceService::class)->roleCode($request->user())
                === GovernanceService::SAGO_ADMIN,
            'countries' => Country::query()->where('is_active', true)->orderBy('name')->get(),
            'languages' => config('pharmacare_languages.catalog', []),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeOwner($request);
        $progress = $this->workflows->resolve($request);
        $data = $this->validated($request);

        [$organization, $administrator, $temporaryPassword] = DB::transaction(function () use ($request, $data, $progress): array {
            $model = Organization::create($this->payload($request, $data));
            $model->countries()->sync($data['country_ids']);
            $temporaryPassword = $data['activation_mode'] === 'invitation'
                ? Str::password(16, symbols: true)
                : $data['admin_password'];
            $administrator = User::create([
                'organization_id' => $model->id,
                'name' => trim($data['admin_first_name'].' '.$data['admin_last_name']),
                'first_name' => trim($data['admin_first_name']),
                'last_name' => trim($data['admin_last_name']),
                'email' => strtolower($data['admin_email']),
                'phone' => $data['admin_phone'] ?? null,
                'username' => strtolower($data['admin_username']),
                'password' => $temporaryPassword,
                'is_active' => $data['admin_status'] === 'active',
                'must_change_password' => true,
            ]);
            $role = Role::query()->where('code', GovernanceService::COORDINATION_ADMIN)
                ->where('is_active', true)->firstOrFail();
            $administrator->roles()->attach($role, [
                'scope_type' => 'organization',
                'scope_id' => $model->id,
            ]);
            $this->workflows->complete($progress, 1);
            $version = ((int) SetupProgress::query()
                ->where('scope_type', 'organization')
                ->where('scope_id', $model->id)
                ->whereKeyNot($progress->id)
                ->max('version')) + 1;
            $progress->update([
                'scope_type' => 'organization',
                'scope_id' => $model->id,
                'version' => $version,
            ]);

            return [$model, $administrator, $temporaryPassword];
        });

        $this->audit->record(
            $request,
            'configuration.organization.created',
            $organization,
            [],
            $organization->only(['code', 'name', 'organization_type', 'country_code', 'is_active']),
        );
        $this->audit->record($request, 'configuration.organization.coordination_admin_created', $administrator, [], [
            'organization_id' => $organization->id,
            'role' => GovernanceService::COORDINATION_ADMIN,
        ]);

        return redirect()->route(
            'configuration.organization',
            $this->workflows->requestParameters($request, $progress),
        )->with('success', 'Organisation et Admin Coordination créés avec succès.')
            ->with('temporary_password', $data['activation_mode'] === 'invitation' ? $temporaryPassword : null);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->ensureAccessible($request, $organization);
        $data = $this->validated($request, $organization);
        $old = $organization->toArray();

        DB::transaction(function () use ($request, $organization, $data): void {
            $organization->update($this->payload($request, $data, $organization));
            $organization->countries()->sync($data['country_ids']);
        });
        $this->audit->record(
            $request,
            'configuration.organization.updated',
            $organization,
            $old,
            $organization->fresh()->toArray(),
        );

        $progress = $this->workflows->resolve($request);

        return redirect()->route(
            'configuration.organization',
            $this->workflows->requestParameters($request, $progress),
        )->with('success', 'Organisation modifiée avec succès.');
    }

    public function archive(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->ensureAccessible($request, $organization);
        $organization->update(['is_active' => false]);
        $organization->delete();
        $this->audit->record($request, 'configuration.organization.archived', $organization);

        return redirect()->route('configuration.organization')
            ->with('success', 'Organisation archivée. Toutes ses données sont conservées.');
    }

    private function validated(Request $request, ?Organization $organization = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'geographic_access_type' => ['required', Rule::in(['single_country', 'multi_country'])],
            'country_ids' => ['required', 'array', 'min:1'],
            'country_ids.*' => ['required', 'uuid', 'distinct', 'exists:countries,id'],
            'code' => [
                'required', 'alpha_dash', 'max:40',
                Rule::unique('organizations', 'code')->ignore($organization?->id),
            ],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\\s-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'default_language' => ['required', Rule::in(array_keys(config('pharmacare_languages.catalog', [])))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:3000'],
            'admin_first_name' => [Rule::requiredIf($organization === null), 'nullable', 'string', 'max:80'],
            'admin_last_name' => [Rule::requiredIf($organization === null), 'nullable', 'string', 'max:80'],
            'admin_email' => [Rule::requiredIf($organization === null), 'nullable', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'admin_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\\s-]+$/'],
            'admin_username' => [Rule::requiredIf($organization === null), 'nullable', 'alpha_dash', 'max:80', Rule::unique('users', 'username')],
            'activation_mode' => [Rule::requiredIf($organization === null), 'nullable', Rule::in(['temporary_password', 'invitation'])],
            'admin_password' => [Rule::requiredIf($organization === null && $request->input('activation_mode') === 'temporary_password'), 'nullable', 'string', 'min:10', 'confirmed'],
            'admin_status' => [Rule::requiredIf($organization === null), 'nullable', Rule::in(['active', 'inactive'])],
        ]);

        if (($data['geographic_access_type'] ?? null) === 'single_country'
            && count($data['country_ids'] ?? []) !== 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'country_ids' => 'Une organisation Unipays doit avoir exactement un pays principal.',
            ]);
        }

        return $data;
    }

    private function payload(
        Request $request,
        array $data,
        ?Organization $organization = null,
    ): array {
        $logoPath = $organization?->logo_path;
        if ($request->hasFile('logo')) {
            $newPath = $request->file('logo')->store('organizations', 'public');
            if ($logoPath) Storage::disk('public')->delete($logoPath);
            $logoPath = $newPath;
        }

        return [
            'name' => trim($data['name']),
            'legal_name' => trim($data['name']),
            'organization_type' => $organization?->organization_type ?? 'other',
            'country_code' => $data['geographic_access_type'] === 'single_country'
                ? Country::query()->whereKey($data['country_ids'][0])->value('iso2')
                : null,
            'geographic_access_type' => $data['geographic_access_type'],
            'code' => strtoupper($data['code']),
            'logo_path' => $logoPath,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'default_language' => $data['default_language'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['status'] === 'active',
        ];
    }

    private function ensureAccessible(Request $request, Organization $organization): void
    {
        abort_unless(
            $this->scopes->organizations($request->user())->whereKey($organization->id)->exists(),
            404,
        );
    }

    private function authorizeViewing(Request $request): void
    {
        abort_unless(
            $request->user()?->hasPermission('organizations.manage')
                || $request->user()?->hasPermission('organizations.view'),
            403,
        );
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless(
            ($request->user()?->hasPermission('organizations.manage') ?? false)
                || app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::SAGO_ADMIN,
            403,
        );
    }
}
