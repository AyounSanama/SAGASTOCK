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

    /** S-07 : plus de rôle non officiel à la plateforme ; rôle de test limité à une organisation. */
    private function user(string $scopeType, ?string $scopeId): User
    {
        $permissions = collect([
            'structures.view' => 'Consulter les structures',
            'structures.manage' => 'Gérer les structures',
            'health_facilities.view' => 'Consulter les formations sanitaires',
            'dispensing_sites.view' => 'Consulter les sites de dispensation',
            'modules.manage' => 'Gérer les activations',
        ])->map(fn ($name, $code) => Permission::firstOrCreate(['code' => $code], ['name' => $name]));
        $role = Role::create(['code' => 'structure_admin_'.uniqid(), 'name' => 'Administrateur structures']);
        $role->permissions()->attach($permissions->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id, ['scope_type' => $scopeType, 'scope_id' => $scopeId]);
        return $user;
    }

    public function test_complete_structure_lifecycle_and_module_activation(): void
    {
        $organization = Organization::create(['code' => 'ONG_CM', 'name' => 'ONG Cameroun']);
        Sanctum::actingAs($this->user('organization', $organization->id));

        $facility = $this->postJson("/api/v1/organizations/{$organization->id}/facilities", [
            'code' => 'FOSA_01', 'name' => 'Hôpital central', 'facility_type' => 'hospital',
            'care_level' => 'secondary', 'is_active' => true,
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
        Sanctum::actingAs($this->user('organization', $organization->id));
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
        $user = $this->user('organization', $organization->id);

        $this->actingAs($user)->get("/organizations/{$organization->id}/structures")
            ->assertOk()->assertSee('Ajouter une formation sanitaire')->assertSee('pharmacare-logo.png');
        $this->actingAs($user)->post("/organizations/{$organization->id}/facilities", [
            'code' => 'WEB_FOSA', 'name' => 'Centre de santé Web',
            'facility_type' => 'health_center', 'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('status');
        $facility = HealthFacility::where('code', 'WEB_FOSA')->firstOrFail();
        $this->actingAs($user)->get("/organizations/{$organization->id}/structures?facility={$facility->id}")
            ->assertOk()->assertSee('Centre de santé Web')->assertSee('Départements')->assertSee('Pharmacies')->assertSee('Sites');
    }

    public function test_sidebar_health_facilities_module_opens_the_real_scoped_workspace(): void
    {
        $inside = Organization::create(['code' => 'FOSA-IN', 'name' => 'Organisation autorisée']);
        $outside = Organization::create(['code' => 'FOSA-OUT', 'name' => 'Organisation interdite']);
        $user = $this->user('organization', $inside->id);
        HealthFacility::create([
            'organization_id' => $inside->id, 'code' => 'CSI-01',
            'name' => 'Centre de santé intégré', 'facility_type' => 'health_center',
        ]);

        $this->actingAs($user)->get('/health-facilities')
            ->assertOk()
            ->assertSee('Formations sanitaires')
            ->assertSee('Centre de santé intégré')
            ->assertSee('facility-create-sheet')
            ->assertSee('overflow-x:clip', false);
        $this->actingAs($user)
            ->get('/health-facilities?organization_id='.$outside->id)
            ->assertNotFound();
    }

    public function test_web_has_a_dedicated_professional_facility_creation_page(): void
    {
        $organization = Organization::create(['code' => 'CREATE_ORG', 'name' => 'Organisation création']);
        $user = $this->user('organization', $organization->id);

        $this->actingAs($user)
            ->get("/organizations/{$organization->id}/facilities/create")
            ->assertOk()
            ->assertSee('Nouvelle formation sanitaire')
            ->assertSee('Enregistrer la formation sanitaire')
            ->assertSee('Fil d’Ariane');

        $this->actingAs($user)
            ->get("/organizations/{$organization->id}/structures")
            ->assertOk()
            ->assertSee('Ajouter une formation sanitaire')
            ->assertSee('Activation des modules')
            ->assertSee('switch')
            ->assertSee('facility-create-sheet');
    }

    public function test_dispensing_sites_sidebar_opens_a_real_scoped_crud_workspace(): void
    {
        $inside = Organization::create(['code' => 'SITE-IN', 'name' => 'Organisation sites']);
        $outside = Organization::create(['code' => 'SITE-OUT', 'name' => 'Organisation externe']);
        $facility = HealthFacility::create([
            'organization_id' => $inside->id,
            'code' => 'FOSA-SITE',
            'name' => 'Hôpital des sites',
            'facility_type' => 'hospital',
        ]);
        $department = $facility->departments()->create([
            'code' => 'DEP-SITE',
            'name' => 'Service pharmacie',
            'department_type' => 'pharmacy',
        ]);
        $pharmacy = $facility->pharmacies()->create([
            'department_id' => $department->id,
            'code' => 'PHA-SITE',
            'name' => 'Pharmacie principale',
            'pharmacy_type' => 'central',
        ]);
        $facility->sites()->create([
            'organization_id' => $inside->id,
            'department_id' => $department->id,
            'pharmacy_id' => $pharmacy->id,
            'code' => 'SITE-01',
            'name' => 'Site de dispensation central',
            'site_type' => 'dispensing',
            'is_active' => true,
        ]);
        $user = $this->user('organization', $inside->id);

        $this->actingAs($user)->get('/dispensing-sites')
            ->assertOk()
            ->assertSee('Sites de dispensation')
            ->assertSee('Site de dispensation central')
            ->assertSee('site-create-sheet')
            ->assertSee('overflow-x:clip', false);

        $this->actingAs($user)->post("/organizations/{$inside->id}/dispensing-sites", [
            'health_facility_id' => $facility->id,
            'department_id' => $department->id,
            'pharmacy_id' => $pharmacy->id,
            'code' => 'SITE-02',
            'name' => 'Dépôt pharmaceutique annexe',
            'site_type' => 'stock_and_dispensing',
            'location' => 'Bâtiment B',
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('sites', [
            'organization_id' => $inside->id,
            'health_facility_id' => $facility->id,
            'code' => 'SITE-02',
            'name' => 'Dépôt pharmaceutique annexe',
        ]);
        $this->actingAs($user)
            ->get('/dispensing-sites?organization_id='.$outside->id)
            ->assertNotFound();
    }

    public function test_coordination_cannot_open_legacy_facility_workspace_even_with_old_permissions(): void
    {
        $assigned = Organization::create(['code' => 'COORD-A', 'name' => 'Organisation assignée']);
        $created = Organization::create(['code' => 'COORD-B', 'name' => 'Nouvelle organisation']);
        $assignedFacility = HealthFacility::create([
            'organization_id' => $assigned->id, 'code' => 'FOSA-A',
            'name' => 'Formation assignée', 'facility_type' => 'hospital',
        ]);
        $createdFacility = HealthFacility::create([
            'organization_id' => $created->id, 'code' => 'FOSA-B',
            'name' => 'Formation nouvelle organisation', 'facility_type' => 'clinic',
        ]);
        $permissions = collect(['organizations.manage', 'dispensing_sites.view', 'structures.manage'])
            ->map(fn (string $code) => Permission::firstOrCreate(['code' => $code], ['name' => $code]));
        $role = Role::create([
            'code' => 'coordination_admin', 'name' => 'Admin Coordination',
            'is_system' => true, 'scope_type' => 'organization',
        ]);
        $role->permissions()->attach($permissions);
        $user = User::factory()->create([
            'organization_id' => $assigned->id, 'is_active' => true,
        ]);
        $user->roles()->attach($role, [
            'scope_type' => 'organization', 'scope_id' => $assigned->id,
        ]);

        $this->actingAs($user)->get('/dispensing-sites')
            ->assertForbidden();
        $this->actingAs($user)->get('/dispensing-sites?organization_id='.$created->id)
            ->assertForbidden();
    }
}
