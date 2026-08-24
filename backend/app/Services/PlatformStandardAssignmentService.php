<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationEffectiveConfiguration;
use App\Models\PlatformStandard;
use App\Models\PlatformStandardAssignment;
use App\Models\PlatformStandardVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformStandardAssignmentService
{
    public const MANUAL_CATEGORIES = ['general', 'access', 'security', 'synchronization', 'platform'];

    public function previewManual(Organization $organization, string $category, array $settings): array
    {
        $this->ensureManualCategory($category);
        $current = OrganizationEffectiveConfiguration::where('organization_id', $organization->id)
            ->where('configuration_category', $category)->where('status', 'active')->latest('configuration_version')->first();
        $before = $this->definitionFromConfiguration($current?->configuration ?? []);
        $after = $this->sanitizeSettings($settings);
        $nextNumber = ((int) OrganizationEffectiveConfiguration::where('organization_id', $organization->id)->max('configuration_version')) + 1;

        return [
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'category_key' => $category,
            'category_label' => $this->categoryLabel($category),
            'current_configuration_version' => $current ? $this->configurationVersionLabel($current->configuration_version) : 'Aucune',
            'next_configuration_version' => $this->configurationVersionLabel($nextNumber),
            'before_configuration' => $before,
            'after_configuration' => $after,
            'changes' => $this->changes($before, $after),
        ];
    }

    public function publishManual(Organization $organization, string $category, array $settings, User $actor, bool $force = false, string $action = 'applied'): OrganizationEffectiveConfiguration
    {
        $preview = $this->previewManual($organization, $category, $settings);
        if ($preview['changes'] === [] && ! $force) {
            throw ValidationException::withMessages(['settings' => 'Aucune modification à appliquer.']);
        }

        return DB::transaction(function () use ($organization, $category, $settings, $actor, $preview, $action): OrganizationEffectiveConfiguration {
            $standard = PlatformStandard::withTrashed()->firstOrCreate(
                ['code' => 'ORG_CONFIG_'.strtoupper($category)],
                [
                    'category' => $this->platformCategory($category),
                    'name' => 'Configuration '.$this->categoryLabel($category),
                    'description' => 'Support technique interne de la configuration manuelle.',
                    'definition' => [], 'status' => 'published', 'is_active' => true,
                    'created_by' => $actor->id, 'updated_by' => $actor->id,
                ]
            );
            if ($standard->trashed()) $standard->restore();
            $standard->update(['definition' => $this->sanitizeSettings($settings), 'status' => 'published', 'is_active' => true, 'updated_by' => $actor->id]);
            $standardVersionNumber = ((int) $standard->versions()->max('version_number')) + 1;
            $version = $standard->versions()->create([
                'version_number' => $standardVersionNumber, 'status' => 'published',
                'snapshot' => ['category' => $category, 'definition' => $this->sanitizeSettings($settings)],
                'change_notes' => 'Configuration manuelle appliquée à '.$organization->name,
                'created_by' => $actor->id, 'published_by' => $actor->id, 'published_at' => now(),
            ]);

            PlatformStandardAssignment::where('organization_id', $organization->id)
                ->where('platform_standard_id', $standard->id)->where('status', 'published')->update(['status' => 'superseded']);
            $current = OrganizationEffectiveConfiguration::where('organization_id', $organization->id)
                ->where('configuration_category', $category)->where('status', 'active')->latest('configuration_version')->first();
            $current?->update(['status' => 'superseded']);

            $assignment = PlatformStandardAssignment::create([
                'organization_id' => $organization->id, 'platform_standard_id' => $standard->id,
                'platform_standard_version_id' => $version->id, 'status' => 'published',
                'assigned_by' => $actor->id, 'published_by' => $actor->id, 'published_at' => now(),
            ]);
            $number = ((int) OrganizationEffectiveConfiguration::where('organization_id', $organization->id)->max('configuration_version')) + 1;
            $configuration = ['category' => $category, 'definition' => $this->sanitizeSettings($settings)];

            return OrganizationEffectiveConfiguration::create([
                'organization_id' => $organization->id, 'configuration_category' => $category, 'intervention_action' => $action,
                'platform_standard_id' => $standard->id, 'platform_standard_assignment_id' => $assignment->id,
                'configuration_version' => $number, 'previous_configuration_version' => $current?->configuration_version,
                'standard_version_number' => $version->version_number, 'configuration' => $configuration,
                'changes' => $preview['changes'],
                'checksum' => hash('sha256', json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'status' => 'active', 'effective_at' => now(), 'applied_by' => $actor->id,
                'synchronization_status' => 'pending', 'synchronization_required_at' => now(),
            ]);
        });
    }

    public function configurationVersionLabel(?int $number): string
    {
        return $number ? 'v1.'.($number - 1) : 'Aucune';
    }
    public function preview(Organization $organization, PlatformStandardVersion $version): array
    {
        $standard = $version->standard;
        $this->ensurePublishable($standard, $version);
        $current = OrganizationEffectiveConfiguration::where('organization_id', $organization->id)
            ->where('platform_standard_id', $standard->id)->where('status', 'active')->latest('configuration_version')->first();
        $beforeSnapshot = $current?->configuration ?? [];
        $afterSnapshot = $version->snapshot;
        $before = $this->normalizedDefinition($beforeSnapshot);
        $after = $this->normalizedDefinition($afterSnapshot);

        return [
            'organization_id' => $organization->id, 'organization_name' => $organization->name,
            'standard_id' => $standard->id, 'standard_name' => $standard->name,
            'version_id' => $version->id, 'version_label' => $version->label,
            'current_version' => $current ? 'v'.$current->standard_version_number.'.0' : 'Aucune',
            'current_configuration_version' => $current ? 'v'.$current->configuration_version : 'Aucune',
            'before_configuration' => $before,
            'after_configuration' => $after,
            'changes' => $this->changes($before, $after),
        ];
    }

    public function publish(Organization $organization, PlatformStandardVersion $version, User $actor): OrganizationEffectiveConfiguration
    {
        $standard = $version->standard;
        $this->ensurePublishable($standard, $version);

        return DB::transaction(function () use ($organization, $standard, $version, $actor): OrganizationEffectiveConfiguration {
            PlatformStandardAssignment::where('organization_id', $organization->id)
                ->where('platform_standard_id', $standard->id)->where('status', 'published')
                ->update(['status' => 'superseded']);
            OrganizationEffectiveConfiguration::where('organization_id', $organization->id)
                ->where('platform_standard_id', $standard->id)->where('status', 'active')
                ->update(['status' => 'superseded']);

            $assignment = PlatformStandardAssignment::create([
                'organization_id' => $organization->id, 'platform_standard_id' => $standard->id,
                'platform_standard_version_id' => $version->id, 'status' => 'published',
                'assigned_by' => $actor->id, 'published_by' => $actor->id, 'published_at' => now(),
            ]);
            $number = ((int) OrganizationEffectiveConfiguration::where('organization_id', $organization->id)
                ->where('platform_standard_id', $standard->id)->max('configuration_version')) + 1;
            $configuration = $version->snapshot;
            $configuration['definition'] = $this->normalizedDefinition($configuration);

            return OrganizationEffectiveConfiguration::create([
                'organization_id' => $organization->id, 'platform_standard_id' => $standard->id,
                'platform_standard_assignment_id' => $assignment->id, 'configuration_version' => $number,
                'standard_version_number' => $version->version_number, 'configuration' => $configuration,
                'checksum' => hash('sha256', json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'status' => 'active', 'effective_at' => now(), 'applied_by' => $actor->id,
                'synchronization_status' => 'pending', 'synchronization_required_at' => now(),
            ]);
        });
    }

    private function ensurePublishable(PlatformStandard $standard, PlatformStandardVersion $version): void
    {
        if ($version->platform_standard_id !== $standard->id || $version->status !== 'published' || ! $standard->is_active) {
            throw ValidationException::withMessages(['version_id' => 'Seule une version publiée d’un standard actif peut être affectée.']);
        }
    }

    private function changes(array $before, array $after): array
    {
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        return collect($keys)->map(function (string $key) use ($before, $after): array {
            $old = $before[$key] ?? null; $new = $after[$key] ?? null;
            return [
                'key' => $key,
                'label' => $this->label($key),
                'old' => $old,
                'new' => $new,
                'type' => ! array_key_exists($key, $before) ? 'added' : (! array_key_exists($key, $after) ? 'removed' : 'changed'),
            ];
        })->filter(fn (array $change) => $change['old'] !== $change['new'])->values()->all();
    }

    private function normalizedDefinition(array $snapshot): array
    {
        $definition = is_array($snapshot['definition'] ?? null)
            ? $snapshot['definition']
            : $snapshot;

        if (isset($definition['key']) && array_key_exists('value', $definition)) {
            return [(string) $definition['key'] => $this->typedValue($definition['value'])];
        }

        return $definition;
    }

    private function typedValue(mixed $value): mixed
    {
        if (! is_string($value)) return $value;
        return match (strtolower(trim($value))) {
            'true', 'activé', 'active' => true,
            'false', 'désactivé', 'inactive' => false,
            default => is_numeric($value) ? $value + 0 : $value,
        };
    }

    private function definitionFromConfiguration(array $configuration): array
    {
        return is_array($configuration['definition'] ?? null) ? $configuration['definition'] : [];
    }

    private function sanitizeSettings(array $settings): array
    {
        $sensitive = ['password', 'token', 'secret', 'refresh_token', 'access_token', 'patient', 'prescription'];
        foreach (array_keys($settings) as $key) {
            if (collect($sensitive)->contains(fn ($term) => str_contains(strtolower((string) $key), $term))) {
                throw ValidationException::withMessages(['settings' => 'Un paramètre interdit a été détecté.']);
            }
        }
        ksort($settings);
        return $settings;
    }

    private function ensureManualCategory(string $category): void
    {
        if (! in_array($category, self::MANUAL_CATEGORIES, true)) {
            throw ValidationException::withMessages(['category' => 'Catégorie de configuration invalide.']);
        }
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'general' => 'Général', 'access' => 'Accès & rôles', 'security' => 'Sécurité',
            'synchronization' => 'Synchronisation', 'platform' => 'Paramètres plateforme',
        };
    }

    private function platformCategory(string $category): string
    {
        return match ($category) {
            'general' => 'general_reference', 'access' => 'access_profile', default => 'technical_standard',
        };
    }

    private function label(string $key): string
    {
        return match ($key) {
            'session_duration_minutes' => 'Durée de session',
            'logout_after_inactivity' => 'Déconnexion après inactivité',
            'maximum_login_attempts', 'max_login_attempts' => 'Nombre maximum de tentatives',
            'sync_on_reconnect' => 'Synchronisation au retour du réseau',
            'automatic_sync' => 'Synchronisation automatique',
            'default_language' => 'Langue principale',
            'timezone' => 'Fuseau horaire',
            'additional_languages' => 'Langues supplémentaires',
            'locale' => 'Paramètres régionaux',
            'date_format' => 'Format de date',
            'time_format' => 'Format de l’heure',
            'temporary_lock_minutes' => 'Durée du verrouillage temporaire',
            'authentication_policy' => 'Politique d’authentification',
            'allowed_profiles' => 'Profils d’accès autorisés',
            'offline_enabled' => 'Fonctionnement hors connexion',
            'configuration_download' => 'Téléchargement des configurations',
            'sync_interval_minutes' => 'Intervalle de synchronisation',
            'notifications_enabled' => 'Notifications de plateforme',
            'maintenance_messages' => 'Messages de maintenance',
            'default_page_size' => 'Taille des listes par défaut',
            'support_contact_visible' => 'Contact support visible',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }
}
