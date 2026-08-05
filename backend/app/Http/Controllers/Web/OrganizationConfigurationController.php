<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Organization;
use App\Models\SetupProgress;
use App\Services\AuditService;
use App\Services\ConfigurationWorkflowService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationConfigurationController extends Controller
{
    private const TYPES = [
        'ngo' => 'ONG',
        'ministry' => 'Ministère de la Santé',
        'national_program' => 'Programme national',
        'united_nations' => 'Organisation des Nations unies',
        'international_agency' => 'Agence internationale',
        'other' => 'Autre',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly UserScopeService $scopes,
        private readonly ConfigurationWorkflowService $workflows,
    ) {}

    public function show(Request $request): View
    {
        $this->authorizeViewing($request);
        $progress = $this->workflows->resolve($request);
        $this->workflows->restoreDraft($request, $progress, 1);
        $completed = collect($progress->completed_steps ?? [])->map(fn ($value) => (int) $value);
        $organizations = $this->scopes->organizations($request->user())
            ->withCount('missions')
            ->orderBy('name')
            ->get();
        $editing = $request->filled('edit')
            ? $organizations->firstWhere('id', $request->input('edit')) ?? abort(404)
            : null;

        return view('configuration.index', [
            'activeModule' => 'configuration',
            'activeStep' => 1,
            'completedSteps' => $completed,
            'stepStates' => $this->workflows->states($progress),
            'workflow' => $progress,
            'organization' => $progress->scope_id
                ? $organizations->firstWhere('id', $progress->scope_id)
                : null,
            'organizations' => $organizations,
            'editingOrganization' => $editing,
            'organizationTypes' => self::TYPES,
            'canManageOrganizations' => $request->user()?->hasPermission('organizations.manage') ?? false,
            'countries' => Country::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeOwner($request);
        $progress = $this->workflows->resolve($request);
        $data = $this->validated($request);

        $organization = DB::transaction(function () use ($request, $data, $progress): Organization {
            $model = Organization::create($this->payload($request, $data));
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

            return $model;
        });

        $this->audit->record(
            $request,
            'configuration.organization.created',
            $organization,
            [],
            $organization->only(['code', 'name', 'organization_type', 'country_code', 'is_active']),
        );

        return redirect()->route(
            'configuration.organization',
            $this->workflows->requestParameters($request, $progress),
        )->with('success', 'Organisation ajoutée avec succès.');
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->ensureAccessible($request, $organization);
        $data = $this->validated($request, $organization);
        $old = $organization->toArray();

        DB::transaction(function () use ($request, $organization, $data): void {
            $organization->update($this->payload($request, $data, $organization));
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
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'organization_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'country_code' => ['required', 'string', 'size:2', 'exists:countries,iso2'],
            'code' => [
                'required', 'alpha_dash', 'max:40',
                Rule::unique('organizations', 'code')->ignore($organization?->id),
            ],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\\s-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'default_language' => ['required', Rule::in(['fr', 'en'])],
            'manager_name' => ['required', 'string', 'max:160'],
            'manager_title' => ['required', 'string', 'max:160'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);
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
            'organization_type' => $data['organization_type'],
            'country_code' => strtoupper($data['country_code']),
            'code' => strtoupper($data['code']),
            'logo_path' => $logoPath,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'default_language' => $data['default_language'],
            'manager_name' => trim($data['manager_name']),
            'manager_title' => trim($data['manager_title']),
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
        abort_unless($request->user()?->hasPermission('organizations.manage'), 403);
    }
}
