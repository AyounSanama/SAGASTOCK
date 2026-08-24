<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\OrganizationEffectiveConfiguration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformConfigurationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sago_can_preview_and_apply_an_organization_configuration(): void
    {
        Sanctum::actingAs($this->sago());
        $organization = Organization::create(['code' => 'API-CONFIG', 'name' => 'API Config', 'is_active' => true]);
        $payload = [
            'category' => 'security',
            'settings' => [
                'session_duration_minutes' => 60,
                'logout_after_inactivity' => true,
                'maximum_login_attempts' => 5,
                'temporary_lock_minutes' => 15,
                'authentication_policy' => 'standard',
            ],
        ];

        $this->postJson("/api/v1/platform-configuration/organizations/{$organization->id}/preview", $payload)
            ->assertOk()->assertJsonPath('preview.category_key', 'security');
        $this->postJson("/api/v1/platform-configuration/organizations/{$organization->id}/apply", $payload)
            ->assertCreated()->assertJsonPath('configuration.synchronization_status', 'pending');

        $this->assertDatabaseHas('organization_effective_configurations', [
            'organization_id' => $organization->id,
            'configuration_category' => 'security',
            'status' => 'active',
        ]);
        $this->assertSame(1, OrganizationEffectiveConfiguration::where('organization_id', $organization->id)->count());
    }

    public function test_non_sago_cannot_access_platform_configuration_api(): void
    {
        $role = Role::create(['code' => 'coordination_admin', 'name' => 'Admin Coordination', 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role, ['scope_type' => 'organization']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/platform-configuration')->assertForbidden();
    }

    private function sago(): User
    {
        $permissions = collect(['platform_standards.view', 'standards.assign'])->map(
            fn ($code) => Permission::firstOrCreate(['code' => $code], ['name' => $code]),
        );
        $role = Role::create(['code' => 'sago_admin', 'name' => 'Admin Sago', 'is_active' => true]);
        $role->permissions()->sync($permissions->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role, ['scope_type' => 'platform']);

        return $user;
    }
}
