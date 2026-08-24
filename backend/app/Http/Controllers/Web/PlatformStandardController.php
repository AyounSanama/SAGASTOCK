<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\PlatformStandard;
use App\Models\PlatformStandardVersion;
use App\Models\PlatformStandardAssignment;
use App\Models\OrganizationEffectiveConfiguration;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\PlatformStandardVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class PlatformStandardController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly PlatformStandardVersionService $versions,
    ) {}

    public function index(Request $request): View
    {
        if ($request->input('tab') === 'history') {
            return $this->history($request);
        }
        if (! $request->filled('tab')) {
            $totalConfigurations = OrganizationEffectiveConfiguration::where('status', 'active')->count();
            $syncedConfigurations = OrganizationEffectiveConfiguration::where('status', 'active')->where('synchronization_status', 'synced')->count();
            return view('configuration.platform-standard-home', [
                'activeModule' => 'configuration',
                'stats' => [
                    'organizations' => Organization::count(),
                    'active' => Organization::where('is_active', true)->count(),
                    'interventions' => OrganizationEffectiveConfiguration::count(),
                    'pending' => OrganizationEffectiveConfiguration::where('synchronization_status', 'pending')->count(),
                    'compliance' => $totalConfigurations ? (int) round(($syncedConfigurations / $totalConfigurations) * 100) : 0,
                ],
                'recent' => OrganizationEffectiveConfiguration::with(['organization:id,name,geographic_access_type','appliedBy:id,name'])->latest('effective_at')->limit(5)->get(),
                'distribution' => OrganizationEffectiveConfiguration::selectRaw('configuration_category, count(*) aggregate')->groupBy('configuration_category')->pluck('aggregate','configuration_category'),
                'categoryLabels' => $this->manualCategoryLabels(),
            ]);
        }

        $organizationQuery = Organization::query()
            ->with([
                'countries:id,name,iso2',
                'users' => fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('code', 'coordination_admin'))
                    ->select('users.id', 'users.organization_id', 'users.name', 'users.email'),
                'effectiveConfigurations' => fn ($query) => $query->where('status', 'active')->with('synchronizations')->latest('effective_at'),
            ]);
        if ($request->filled('organization_q')) {
            $term = trim((string) $request->input('organization_q'));
            $organizationQuery->where(fn ($query) => $query->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%"));
        }
        if ($request->filled('country_id')) {
            $organizationQuery->whereHas('countries', fn ($query) => $query->where('countries.id', $request->input('country_id')));
        }
        if (in_array($request->input('access_type'), ['single_country', 'multi_country'], true)) {
            $organizationQuery->where('geographic_access_type', $request->input('access_type'));
        }
        if (in_array($request->input('organization_status'), ['active', 'inactive'], true)) {
            $organizationQuery->where('is_active', $request->input('organization_status') === 'active');
        }
        if (in_array($request->input('configuration_state'), ['configured', 'not_configured'], true)) {
            $method = $request->input('configuration_state') === 'configured' ? 'whereHas' : 'whereDoesntHave';
            $organizationQuery->{$method}('effectiveConfigurations', fn ($query) => $query->where('status', 'active'));
        }
        $assistanceOrganizations = $organizationQuery->orderBy('name')->get();

        return view('configuration.platform-standard-organizations', [
            'activeModule' => 'configuration',
            'assistanceOrganizations' => $assistanceOrganizations,
            'assistanceCountries' => Country::where('is_active', true)->whereHas('organizations')->orderBy('name')->get(['id', 'name']),
            'assistanceStats' => [
                'active' => Organization::where('is_active', true)->count(),
                'accompanied' => OrganizationEffectiveConfiguration::where('status', 'active')->distinct('organization_id')->count('organization_id'),
                'configurations' => OrganizationEffectiveConfiguration::where('status', 'active')->count(),
            ],
        ]);
    }

    private function history(Request $request): View
    {
        $query = OrganizationEffectiveConfiguration::query()->with([
            'organization:id,name,code,geographic_access_type', 'appliedBy:id,name', 'standard:id,name,code',
        ]);
        if ($request->filled('organization_id')) $query->where('organization_id', $request->input('organization_id'));
        if (in_array($request->input('category'), ['general','access','security','synchronization','platform'], true)) {
            $query->where('configuration_category', $request->input('category'));
        }
        if ($request->input('intervention') === 'creation') $query->whereNull('previous_configuration_version')->where('intervention_action', 'applied');
        if ($request->input('intervention') === 'modification') $query->whereNotNull('previous_configuration_version')->where('intervention_action', 'applied');
        if ($request->input('intervention') === 'restoration') $query->where('intervention_action', 'restored');
        if ($request->filled('date_from')) $query->whereDate('effective_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('effective_at', '<=', $request->input('date_to'));
        if (in_array($request->input('history_status'), ['pending','synced','error'], true)) {
            $query->where('synchronization_status', $request->input('history_status'));
        }

        $filtered = clone $query;
        $summary = [
            'total' => (clone $filtered)->count(),
            'applied' => (clone $filtered)->where('synchronization_status', 'synced')->count(),
            'pending' => (clone $filtered)->where('synchronization_status', 'pending')->count(),
            'errors' => (clone $filtered)->where('synchronization_status', 'error')->count(),
        ];
        $categoryDistribution = (clone $filtered)->selectRaw('configuration_category, count(*) as aggregate')
            ->groupBy('configuration_category')->pluck('aggregate', 'configuration_category');
        $perPage = in_array((int) $request->input('per_page'), [10,25,50], true) ? (int) $request->input('per_page') : 10;

        return view('configuration.platform-standard-history', [
            'activeModule' => 'configuration',
            'interventions' => $query->latest('effective_at')->paginate($perPage)->withQueryString(),
            'organizations' => Organization::orderBy('name')->get(['id','name']),
            'summary' => $summary,
            'categoryDistribution' => $categoryDistribution,
            'categoryLabels' => $this->manualCategoryLabels(),
        ]);
    }

    public function historyDetail(OrganizationEffectiveConfiguration $configuration): View
    {
        $configuration->load(['organization:id,name,code,geographic_access_type', 'appliedBy:id,name', 'standard:id,name,code']);
        return view('configuration.platform-standard-history-detail', [
            'activeModule' => 'configuration', 'configuration' => $configuration,
            'categoryLabels' => $this->manualCategoryLabels(),
        ]);
    }

    private function manualCategoryLabels(): array
    {
        return ['general'=>'Général','access'=>'Accès & rôles','security'=>'Sécurité','synchronization'=>'Synchronisation','platform'=>'Paramètres plateforme'];
    }

    public function assist(Organization $organization): View
    {
        $organization->load([
            'countries:id,name,iso2',
            'users' => fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('code', 'coordination_admin'))
                ->select('users.id', 'users.organization_id', 'users.name', 'users.email'),
            'effectiveConfigurations' => fn ($query) => $query->where('status', 'active')->with(['standard:id,code,name,category', 'synchronizations'])->latest('effective_at'),
        ]);

        return view('configuration.platform-standard-assistance', [
            'activeModule' => 'configuration',
            'organization' => $organization,
            'languages' => config('pharmacare_languages.catalog', []),
            'categories' => $this->assistanceCategories($organization->effectiveConfigurations),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    private function assistanceCategories($effectiveConfigurations): array
    {
        return [
            'general' => ['label' => 'Général', 'icon' => 'language', 'description' => 'Langues, paramètres régionaux, fuseau horaire et préférences générales.', 'field_count' => 6, 'current' => $effectiveConfigurations->firstWhere('configuration_category', 'general')],
            'access' => ['label' => 'Accès & rôles', 'icon' => 'admin_panel_settings', 'description' => 'Profils standards d’accès autorisés pour l’organisation.', 'field_count' => 3, 'current' => $effectiveConfigurations->firstWhere('configuration_category', 'access')],
            'security' => ['label' => 'Sécurité', 'icon' => 'shield', 'description' => 'Session, authentification et politiques de sécurité.', 'field_count' => 5, 'current' => $effectiveConfigurations->firstWhere('configuration_category', 'security')],
            'synchronization' => ['label' => 'Synchronisation', 'icon' => 'sync', 'description' => 'Paramètres Web, mobile, Offline et de synchronisation.', 'field_count' => 5, 'current' => $effectiveConfigurations->firstWhere('configuration_category', 'synchronization')],
            'platform' => ['label' => 'Paramètres plateforme', 'icon' => 'tune', 'description' => 'Options générales PharmaCare explicitement autorisées.', 'field_count' => 4, 'current' => $effectiveConfigurations->firstWhere('configuration_category', 'platform')],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $standard = DB::transaction(function () use ($data, $request): PlatformStandard {
            $standard = PlatformStandard::create($this->payload($data, $request->user()->id, true));
            $this->versions->createInitial($standard, $request->user(), $request->input('change_notes'));
            return $standard;
        });
        $this->audit->record($request, 'platform_standard.created', $standard, [], $standard->toArray());

        return redirect()->route('configuration.platform-standards.index', ['tab' => 'models'])
            ->with('success', 'Modèle créé avec succès. Il reste en brouillon jusqu’à l’étape de publication.');
    }

    public function update(Request $request, PlatformStandard $platformStandard): RedirectResponse
    {
        $data = $this->validated($request, $platformStandard);
        $old = $platformStandard->toArray();
        DB::transaction(function () use ($platformStandard, $data, $request): void {
            $platformStandard->update($this->payload($data, $request->user()->id));
            $this->versions->captureDraft($platformStandard, $request->user(), $request->input('change_notes'));
        });
        $this->audit->record($request, 'platform_standard.updated', $platformStandard, $old, $platformStandard->fresh()->toArray());

        return redirect()->route('configuration.platform-standards.index', ['tab' => 'models'])
            ->with('success', 'Modèle modifié avec succès.');
    }

    public function archive(Request $request, PlatformStandard $platformStandard): RedirectResponse
    {
        $platformStandard->update(['status' => 'archived', 'is_active' => false, 'updated_by' => $request->user()->id]);
        $platformStandard->delete();
        $this->audit->record($request, 'platform_standard.archived', $platformStandard);
        return back()->with('success', 'Standard archivé. Son historique est conservé.');
    }

    public function restore(Request $request, string $platformStandard): RedirectResponse
    {
        $standard = PlatformStandard::onlyTrashed()->findOrFail($platformStandard);
        $standard->restore();
        $standard->update([
            'status' => $standard->versions()->where('status', 'published')->exists() ? 'published' : 'draft',
            'is_active' => true, 'updated_by' => $request->user()->id,
        ]);
        $this->audit->record($request, 'platform_standard.restored', $standard);
        return back()->with('success', 'Standard restauré avec succès.');
    }

    public function publish(Request $request, PlatformStandard $platformStandard, PlatformStandardVersion $version): RedirectResponse
    {
        $this->versions->publish($platformStandard, $version, $request->user());
        $this->audit->record($request, 'platform_standard.version_published', $version, [], ['version' => $version->label]);
        return back()->with('success', "Version {$version->label} publiée avec succès.");
    }

    public function rollback(Request $request, PlatformStandard $platformStandard, PlatformStandardVersion $version): RedirectResponse
    {
        $data = $request->validate(['change_notes' => ['nullable', 'string', 'max:1000']]);
        $restored = $this->versions->rollback($platformStandard, $version, $request->user(), $data['change_notes'] ?? null);
        $this->audit->record($request, 'platform_standard.version_rolled_back', $restored, [], ['source_version' => $version->label, 'new_version' => $restored->label]);
        return back()->with('success', "Version {$version->label} restaurée sous la nouvelle version {$restored->label}.");
    }

    private function validated(Request $request, ?PlatformStandard $standard = null): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_keys(PlatformStandard::CATEGORIES))],
            'code' => ['required', 'alpha_dash', 'max:80', Rule::unique('platform_standards', 'code')->ignore($standard?->id)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'definition_key' => ['nullable', 'string', 'max:100'],
            'definition_value' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'change_notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function payload(array $data, string $actorId, bool $creating = false): array
    {
        return [
            'category' => $data['category'], 'code' => strtoupper($data['code']), 'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'definition' => array_filter(['key' => $data['definition_key'] ?? null, 'value' => $data['definition_value'] ?? null], fn ($value) => $value !== null && $value !== ''),
            'is_active' => (bool) $data['is_active'],
            'updated_by' => $actorId,
        ] + ($creating ? ['created_by' => $actorId, 'status' => 'draft'] : []);
    }
}
