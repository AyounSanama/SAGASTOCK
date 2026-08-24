<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationEffectiveConfiguration;
use App\Services\AuditService;
use App\Services\PlatformStandardAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformConfigurationController extends Controller
{
    private const CATEGORIES = [
        'general' => 'Général',
        'access' => 'Accès & rôles',
        'security' => 'Sécurité',
        'synchronization' => 'Synchronisation',
        'platform' => 'Paramètres plateforme',
    ];

    public function __construct(
        private readonly PlatformStandardAssignmentService $assignments,
        private readonly AuditService $audit,
    ) {}

    public function home(): JsonResponse
    {
        $total = OrganizationEffectiveConfiguration::where('status', 'active')->count();
        $synced = OrganizationEffectiveConfiguration::where('status', 'active')->where('synchronization_status', 'synced')->count();

        return response()->json([
            'stats' => [
                'organizations' => Organization::count(),
                'active' => Organization::where('is_active', true)->count(),
                'interventions' => OrganizationEffectiveConfiguration::count(),
                'pending' => OrganizationEffectiveConfiguration::where('synchronization_status', 'pending')->count(),
                'compliance' => $total ? (int) round(($synced / $total) * 100) : 0,
            ],
            'distribution' => OrganizationEffectiveConfiguration::selectRaw('configuration_category, count(*) aggregate')->groupBy('configuration_category')->pluck('aggregate', 'configuration_category'),
            'recent' => $this->historyQuery()->limit(5)->get()->map(fn ($item) => $this->historyResource($item)),
        ]);
    }

    public function organizations(Request $request): JsonResponse
    {
        $query = Organization::with([
            'countries:id,name,iso2',
            'users' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('code', 'coordination_admin'))->select('users.id', 'users.organization_id', 'users.name'),
            'effectiveConfigurations' => fn ($q) => $q->where('status', 'active')->latest('configuration_version'),
        ]);
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
        }
        if (in_array($request->input('access_type'), ['single_country', 'multi_country'], true)) {
            $query->where('geographic_access_type', $request->input('access_type'));
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        return response()->json(['data' => $query->orderBy('name')->paginate(20)->through(fn ($org) => [
            'id' => $org->id,
            'name' => $org->name,
            'code' => $org->code,
            'access_type' => $org->geographic_access_type,
            'countries' => $org->countries->map->only(['id', 'name', 'iso2'])->values(),
            'admin_coordination' => $org->users->first()?->name,
            'is_active' => $org->is_active,
            'configuration_version' => optional($org->effectiveConfigurations->sortByDesc('configuration_version')->first())->configuration_version,
        ])]);
    }

    public function organization(Organization $organization): JsonResponse
    {
        $organization->load([
            'countries:id,name,iso2',
            'users' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('code', 'coordination_admin'))->select('users.id', 'users.organization_id', 'users.name'),
            'effectiveConfigurations' => fn ($q) => $q->where('status', 'active')->latest('configuration_version'),
        ]);

        return response()->json([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'code' => $organization->code,
                'access_type' => $organization->geographic_access_type,
                'countries' => $organization->countries->map->only(['id', 'name', 'iso2'])->values(),
                'admin_coordination' => $organization->users->first()?->name,
                'is_active' => $organization->is_active,
            ],
            'categories' => collect(self::CATEGORIES)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'configuration' => $organization->effectiveConfigurations->firstWhere('configuration_category', $key),
            ])->values(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $query = $this->historyQuery();
        if ($request->filled('organization_id')) $query->where('organization_id', $request->input('organization_id'));
        if (array_key_exists((string) $request->input('category'), self::CATEGORIES)) $query->where('configuration_category', $request->input('category'));
        if (in_array($request->input('status'), ['pending', 'synced', 'error'], true)) $query->where('synchronization_status', $request->input('status'));

        return response()->json(['data' => $query->paginate(20)->through(fn ($item) => $this->historyResource($item))]);
    }

    public function preview(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate(['category' => ['required', Rule::in(PlatformStandardAssignmentService::MANUAL_CATEGORIES)]]);
        abort_unless($organization->is_active, 404);
        $settings = $this->validatedSettings($request, $data['category']);

        return response()->json(['preview' => $this->assignments->previewManual($organization, $data['category'], $settings)]);
    }

    public function apply(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate(['category' => ['required', Rule::in(PlatformStandardAssignmentService::MANUAL_CATEGORIES)]]);
        abort_unless($organization->is_active, 404);
        $settings = $this->validatedSettings($request, $data['category']);
        $preview = $this->assignments->previewManual($organization, $data['category'], $settings);
        $effective = $this->assignments->publishManual($organization, $data['category'], $settings, $request->user());
        $this->audit->record($request, 'organization_configuration.applied', $effective, [], [
            'organization_id' => $organization->id,
            'configuration_category' => $data['category'],
            'previous_version' => $preview['current_configuration_version'],
            'new_version' => $this->assignments->configurationVersionLabel($effective->configuration_version),
            'changed_fields' => $effective->changes,
            'synchronization_status' => $effective->synchronization_status,
        ]);

        return response()->json(['message' => 'Configuration appliquée et mise en attente de synchronisation.', 'configuration' => $effective->fresh()], 201);
    }

    private function validatedSettings(Request $request, string $category): array
    {
        $rules = match ($category) {
            'general' => [
                'settings.default_language' => ['required', 'string', 'max:10'],
                'settings.additional_languages' => ['nullable', 'array'],
                'settings.additional_languages.*' => ['string', 'max:10'],
                'settings.timezone' => ['required', 'timezone:all'],
                'settings.locale' => ['required', Rule::in(['fr_FR', 'en_US', 'es_ES', 'pt_PT'])],
                'settings.date_format' => ['required', Rule::in(['d/m/Y', 'Y-m-d', 'm/d/Y'])],
                'settings.time_format' => ['required', Rule::in(['H:i', 'h:i A'])],
            ],
            'access' => [
                'settings.allowed_profiles' => ['required', 'array', 'min:1'],
                'settings.allowed_profiles.*' => [Rule::in(['ADMIN_COORDINATION', 'ADMIN_PROJECT', 'ADMIN_SITE'])],
            ],
            'security' => [
                'settings.session_duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
                'settings.logout_after_inactivity' => ['required', 'boolean'],
                'settings.maximum_login_attempts' => ['required', 'integer', 'min:1', 'max:20'],
                'settings.temporary_lock_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
                'settings.authentication_policy' => ['required', Rule::in(['standard', 'strict'])],
            ],
            'synchronization' => [
                'settings.automatic_sync' => ['required', 'boolean'],
                'settings.sync_on_reconnect' => ['required', 'boolean'],
                'settings.offline_enabled' => ['required', 'boolean'],
                'settings.configuration_download' => ['required', Rule::in(['automatic', 'manual'])],
                'settings.sync_interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            ],
            'platform' => [
                'settings.notifications_enabled' => ['required', 'boolean'],
                'settings.maintenance_messages' => ['required', 'boolean'],
                'settings.default_page_size' => ['required', 'integer', Rule::in([10, 25, 50])],
                'settings.support_contact_visible' => ['required', 'boolean'],
            ],
        };

        return (array) $request->validate($rules)['settings'];
    }

    private function historyQuery(): Builder
    {
        return OrganizationEffectiveConfiguration::with(['organization:id,name,geographic_access_type', 'appliedBy:id,name'])->latest('effective_at');
    }

    private function historyResource($item): array
    {
        return [
            'id' => $item->id,
            'organization' => $item->organization?->name,
            'access_type' => $item->organization?->geographic_access_type,
            'category' => $item->configuration_category,
            'category_label' => self::CATEGORIES[$item->configuration_category] ?? 'Configuration',
            'action' => $item->intervention_action,
            'version' => 'v1.'.max(0, $item->configuration_version - 1),
            'author' => $item->appliedBy?->name ?? 'Système',
            'status' => $item->synchronization_status,
            'changes' => $item->changes ?? [],
            'created_at' => $item->effective_at,
        ];
    }
}
