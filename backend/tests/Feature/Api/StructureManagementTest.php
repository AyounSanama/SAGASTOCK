<?php

namespace Tests\Feature\Api;

use App\Models\HealthFacility;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructureManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $scopeType = 'platform', ?string $scopeId = null): User
    {
        $permissions = collect([
            'structures.view' => 'Consulter les structures',
            'structures.manage' => 'Gérer les structures',
            'modules.manage' => 'Gérer les activations',
        ])->map(fn ($name, $code) => Permission::create(compact('code', 'name')));
        $role = Role::create(['code' => 'structure_admin_'.uniqid(), 'name' => 'Administrateur structures']);
        $role->permissions()->attach($permissions->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id, ['scope_type' => $scopeType, 'scope_id' => $scopeId]);
        return $user;
    }

    public function test_complete_structure_lifecycle_and_module_activation(): void
    {
        $organization = Organization::create(['code' => 'ONG_CM', 'name' => 'ONG Cameroun']);
        Sanctum::actingAs($this->user());

        $facility = $this->postJson("/api/v1/organizations/{$organization->id}/facilities", [
            'code' => 'FOSA_01', 'name' => 'Hôpital central', 'facility_type' => 'hospital',
            'care_level' => 'District', 'is_active' => true,
        ])->assertCreated()->assertJsonPath('facility.name', 'Hôpital central')->json('facility');

        $department = $this->postJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}/departments", [
            'code' => 'PHAR', 'name' => 'Département pharmacie', 'department_type' => 'pharmacy',
        ])->assertCreated()->json('department');

        $pharmacy = $this->postJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}/pharmacies", [
            'department_id' => $department['id'], 'code' => 'PHA_C', 'name' => 'Pharmacie centrale', 'pharmacy_type' => 'central',
        ])->assertCreated()->json('pharmacy');

        $site = $this->postJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}/sites", [
            'department_id' => $department['id'], 'pharmacy_id' => $pharmacy['id'],
            'code' => 'STOCK_A', 'name' => 'Magasin principal', 'site_type' => 'stock',
        ])->assertCreated()->json('site');

        $this->putJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}/sites/{$site['id']}", [
            'code' => 'STOCK_A', 'name' => 'Magasin pharmaceutique', 'site_type' => 'stock_and_dispensing',
        ])->assertOk()->assertJsonPath('site.name', 'Magasin pharmaceutique');

        $this->putJson("/api/v1/organizations/{$organization->id}/module-activations", [
            'target_type' => 'facility', 'target_id' => $facility['id'],
            'module_code' => 'stocks', 'is_enabled' => true,
        ])->assertOk()->assertJsonPath('activation.is_enabled', true);

        $this->deleteJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}/sites/{$site['id']}")->assertNoContent();
        $this->postJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}/sites/archived/{$site['id']}/restore")
            ->assertOk()->assertJsonPath('site.is_active', true);
        $this->deleteJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}")->assertNoContent();
        $this->assertSoftDeleted('health_facilities', ['id' => $facility['id']]);
        $this->postJson("/api/v1/organizations/{$organization->id}/facilities/archived/{$facility['id']}/restore")
            ->assertOk()->assertJsonPath('facility.is_active', true);

        $this->getJson("/api/v1/organizations/{$organization->id}/structures")
            ->assertOk()->assertJsonFragment(['name' => 'Magasin pharmaceutique']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'facility.restored', 'auditable_id' => $facility['id']]);
        $this->assertDatabaseHas('module_activations', ['target_id' => $facility['id'], 'module_code' => 'stocks', 'is_enabled' => true]);
    }

    public function test_structure_access_is_isolated_by_organization(): void
    {
        $inside = Organization::create(['code' => 'ORG_A', 'name' => 'Organisation A']);
        $outside = Organization::create(['code' => 'ORG_B', 'name' => 'Organisation B']);
        Sanctum::actingAs($this->user('organization', $inside->id));

        $this->getJson("/api/v1/organizations/{$inside->id}/structures")->assertOk();
        $this->getJson("/api/v1/organizations/{$outside->id}/structures")->assertNotFound();
        $this->postJson("/api/v1/organizations/{$outside->id}/facilities", [
            'code' => 'FORBIDDEN', 'name' => 'Interdit', 'facility_type' => 'clinic',
        ])->assertNotFound();
        $this->assertDatabaseMissing('health_facilities', ['code' => 'FORBIDDEN']);
    }

    public function test_cross_facility_relations_are_rejected(): void
    {
        $organization = Organization::create(['code' => 'ORG', 'name' => 'Organisation']);
        Sanctum::actingAs($this->user());
        $first = HealthFacility::create(['organization_id' => $organization->id, 'code' => 'F1', 'name' => 'FOSA 1', 'facility_type' => 'clinic']);
        $second = HealthFacility::create(['organization_id' => $organization->id, 'code' => 'F2', 'name' => 'FOSA 2', 'facility_type' => 'clinic']);
        $department = $first->departments()->create(['code' => 'D1', 'name' => 'Service 1', 'department_type' => 'clinical']);

        $this->postJson("/api/v1/organizations/{$organization->id}/facilities/{$second->id}/pharmacies", [
            'department_id' => $department->id, 'code' => 'BAD', 'name' => 'Pharmacie invalide', 'pharmacy_type' => 'central',
        ])->assertUnprocessable();
    }

    public function test_web_structure_interface_is_connected_and_responsive_ready(): void
    {
        $organization = Organization::create(['code' => 'WEB_ORG', 'name' => 'Organisation Web']);
        $user = $this->user();

        $this->actingAs($user)->get("/organizations/{$organization->id}/structures")
            ->assertOk()->assertSee('Nouvelle formation sanitaire')->assertSee('pharmacare-logo.png');
        $this->actingAs($user)->post("/organizations/{$organization->id}/facilities", [
            'code' => 'WEB_FOSA', 'name' => 'Centre de santé Web',
            'facility_type' => 'health_center', 'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('status');
        $facility = HealthFacility::where('code', 'WEB_FOSA')->firstOrFail();
        $this->actingAs($user)->get("/organizations/{$organization->id}/structures?facility={$facility->id}")
            ->assertOk()->assertSee('Centre de santé Web')->assertSee('Départements')->assertSee('Pharmacies')->assertSee('Sites');
    }
}
