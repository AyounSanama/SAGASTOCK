<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationEffectiveConfiguration;
use App\Models\PlatformStandardVersion;
use App\Services\AuditService;
use App\Services\PlatformStandardAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformStandardAssignmentController extends Controller
{
    public function __construct(private readonly PlatformStandardAssignmentService $assignments, private readonly AuditService $audit) {}

    public function preview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'version_id' => ['required', 'uuid', 'exists:platform_standard_versions,id'],
            'category_key' => ['nullable', 'string', 'in:general,access,synchronization,security,platform'],
        ]);
        $organization = Organization::where('is_active', true)->findOrFail($data['organization_id']);
        $version = PlatformStandardVersion::with('standard')->findOrFail($data['version_id']);
        $preview = $this->assignments->preview($organization, $version) + [
            'category_key' => $data['category_key'] ?? null,
            'category_label' => $this->categoryLabel($data['category_key'] ?? null),
        ];
        $request->session()->put('pending_assignment_preview', $preview);

        if (! empty($data['category_key'])) {
            return redirect()->route('configuration.platform-standards.organizations.assist', $organization)
                ->with('assignment_preview', $preview);
        }

        return redirect()->route('configuration.platform-standards.index', ['section' => 'assignments'])
            ->with('assignment_preview', $preview);
    }

    public function previewManual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'category' => ['required', Rule::in(PlatformStandardAssignmentService::MANUAL_CATEGORIES)],
        ]);
        $organization = Organization::where('is_active', true)->findOrFail($data['organization_id']);
        $settings = $this->validatedManualSettings($request, $data['category']);
        $preview = $this->assignments->previewManual($organization, $data['category'], $settings);
        $request->session()->put('pending_manual_configuration', $preview);

        return redirect()->route('configuration.platform-standards.organizations.assist', $organization)
            ->with('assignment_preview', $preview);
    }

    public function publishManual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'category' => ['required', Rule::in(PlatformStandardAssignmentService::MANUAL_CATEGORIES)],
        ]);
        $preview = $request->session()->get('pending_manual_configuration');
        abort_unless(is_array($preview)
            && hash_equals((string) ($preview['organization_id'] ?? ''), $data['organization_id'])
            && hash_equals((string) ($preview['category_key'] ?? ''), $data['category']), 422);
        $organization = Organization::where('is_active', true)->findOrFail($data['organization_id']);
        $effective = $this->assignments->publishManual(
            $organization, $data['category'], (array) ($preview['after_configuration'] ?? []), $request->user()
        );
        $this->audit->record($request, 'organization_configuration.applied', $effective, [], [
            'organization_id' => $organization->id,
            'configuration_category' => $data['category'],
            'previous_version' => $preview['current_configuration_version'],
            'new_version' => $this->assignments->configurationVersionLabel($effective->configuration_version),
            'changed_fields' => $effective->changes,
            'synchronization_status' => $effective->synchronization_status,
        ]);
        $request->session()->forget('pending_manual_configuration');

        return redirect()->route('configuration.platform-standards.organizations.assist', $organization)
            ->with('success', 'Configuration appliquée. Version '.$this->assignments->configurationVersionLabel($effective->configuration_version).' en attente de synchronisation.');
    }

    public function restoreManual(Request $request, OrganizationEffectiveConfiguration $configuration): RedirectResponse
    {
        abort_unless($configuration->configuration_category
            && in_array($configuration->configuration_category, PlatformStandardAssignmentService::MANUAL_CATEGORIES, true), 422);

        $organization = Organization::where('is_active', true)->findOrFail($configuration->organization_id);
        $definition = (array) data_get($configuration->configuration, 'definition', []);
        $restored = $this->assignments->publishManual(
            $organization,
            $configuration->configuration_category,
            $definition,
            $request->user(),
            force: true,
            action: 'restored',
        );
        $this->audit->record($request, 'organization_configuration.restored', $restored, [], [
            'organization_id' => $organization->id,
            'configuration_category' => $configuration->configuration_category,
            'restored_from' => $this->assignments->configurationVersionLabel($configuration->configuration_version),
            'new_version' => $this->assignments->configurationVersionLabel($restored->configuration_version),
            'changed_fields' => $restored->changes,
        ]);

        return redirect()->route('configuration.platform-standards.index', ['tab' => 'history'])
            ->with('success', 'Configuration restaurée dans une nouvelle version. L’historique précédent est conservé.');
    }

    public function confirmPreview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'version_id' => ['required', 'uuid', 'exists:platform_standard_versions,id'],
        ]);
        $preview = $request->session()->get('pending_assignment_preview');
        abort_unless(is_array($preview)
            && hash_equals((string) ($preview['organization_id'] ?? ''), $data['organization_id'])
            && hash_equals((string) ($preview['version_id'] ?? ''), $data['version_id']), 422);

        $request->session()->put('confirmed_assignment_preview', $preview);

        return redirect()->route('configuration.platform-standards.organizations.assist', $data['organization_id'])
            ->with('preview_confirmed', 'Aperçu confirmé. Aucune modification n’a encore été appliquée.');
    }

    public function publish(Request $request): RedirectResponse
    {
        $data = $request->validate(['organization_id' => ['required', 'uuid', 'exists:organizations,id'], 'version_id' => ['required', 'uuid', 'exists:platform_standard_versions,id']]);
        $confirmed = $request->session()->get('confirmed_assignment_preview')
            ?: $request->session()->get('pending_assignment_preview');
        abort_unless(is_array($confirmed)
            && hash_equals((string) ($confirmed['organization_id'] ?? ''), $data['organization_id'])
            && hash_equals((string) ($confirmed['version_id'] ?? ''), $data['version_id']), 422);
        $organization = Organization::where('is_active', true)->findOrFail($data['organization_id']);
        $version = PlatformStandardVersion::with('standard')->findOrFail($data['version_id']);
        $effective = $this->assignments->publish($organization, $version, $request->user());
        $this->audit->record($request, 'platform_standard.assigned_and_published', $effective, [], [
            'organization_id' => $organization->id,
            'configuration_category' => $confirmed['category_key'] ?? null,
            'previous_version' => $confirmed['current_configuration_version'] ?? 'Aucune',
            'new_version' => 'v'.$effective->configuration_version,
            'standard_version' => $version->label,
            'changed_fields' => $confirmed['changes'] ?? [],
            'checksum' => $effective->checksum,
            'synchronization_status' => $effective->synchronization_status,
        ]);
        $request->session()->forget(['pending_assignment_preview', 'confirmed_assignment_preview']);

        return redirect()->route('configuration.platform-standards.organizations.assist', $organization)
            ->with('success', "Configuration {$effective->configuration_version} appliquée à {$organization->name} et mise en attente de synchronisation.");
    }

    private function categoryLabel(?string $key): string
    {
        return match ($key) {
            'general' => 'Général',
            'access' => 'Accès & rôles',
            'synchronization' => 'Synchronisation',
            'security' => 'Sécurité',
            'platform' => 'Paramètres plateforme',
            default => 'Configuration plateforme',
        };
    }

    private function validatedManualSettings(Request $request, string $category): array
    {
        $rules = match ($category) {
            'general' => [
                'settings.default_language' => ['required', 'string', Rule::in(array_keys(config('pharmacare_languages.catalog', [])))],
                'settings.additional_languages' => ['nullable', 'array'],
                'settings.additional_languages.*' => ['string', 'distinct', Rule::in(array_keys(config('pharmacare_languages.catalog', [])))],
                'settings.timezone' => ['required', 'timezone:all'],
                'settings.locale' => ['required', Rule::in(array_keys(config('pharmacare_languages.regional_formats', [])))],
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
}
