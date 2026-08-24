<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SagoPlatformBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sago_configuration_contains_only_platform_domains(): void
    {
        $this->actingAs($this->sago())->get('/configuration')
            ->assertOk()
            ->assertSee('Organisations')
            ->assertSee('Standards &amp; Référentiels', false)
            ->assertDontSee('Missions / Pays')
            ->assertDontSee('Listes standards de médicaments');
    }

    public function test_sago_is_denied_operational_web_and_api_even_with_legacy_direct_permissions(): void
    {
        $sago = $this->sago(['missions.view', 'missions.manage', 'projects.view', 'products.view']);
        $organization = Organization::create(['code' => 'BOUNDARY', 'name' => 'Boundary']);

        $this->actingAs($sago)->get('/missions')->assertForbidden();
        $this->actingAs($sago)->get("/organizations/{$organization->id}/missions")->assertForbidden();

        Sanctum::actingAs($sago);
        $this->getJson("/api/v1/organizations/{$organization->id}/missions")->assertForbidden();
        $this->getJson("/api/v1/organizations/{$organization->id}/projects")->assertForbidden();
    }

    public function test_sago_keeps_organization_administration_access(): void
    {
        $sago = $this->sago();
        $this->actingAs($sago)->get('/configuration/organization')->assertOk();
        Sanctum::actingAs($sago);
        $this->getJson('/api/v1/organizations')->assertOk();
    }

    private function sago(array $direct = []): User
    {
        $base = collect(['dashboard.view', 'configuration.view', 'configuration.platform.manage', 'organizations.view', 'organizations.manage']);
        $permissions = $base->merge($direct)->unique()->map(
            fn ($code) => Permission::firstOrCreate(['code' => $code], ['name' => $code]),
        );
        $role = Role::firstOrCreate(['code' => 'sago_admin'], [
            'name' => 'Admin Sago', 'is_active' => true, 'is_system' => true,
        ]);
        $role->permissions()->sync($permissions->whereIn('code', $base)->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role, ['scope_type' => 'platform']);
        $user->directPermissions()->sync($permissions->whereIn('code', $direct)->pluck('id'));
        return $user;
    }
}
