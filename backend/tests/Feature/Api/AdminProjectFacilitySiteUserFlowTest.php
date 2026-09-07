<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProjectFacilitySiteUserFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_complete_project_facility_site_and_fosa_user_flow_is_consistently_scoped(): void
    {
        $organization = Organization::create(['code' => 'E2E_FOSA', 'name' => 'Organisation E2E FOSA']);
        $country = Country::where('iso2', 'CM')->firstOrFail();
        $mission = Mission::create([
            'organization_id' => $organization->id,
            'country_id' => $country->id,
            'code' => 'CM_E2E',
            'name' => 'Coordination Cameroun E2E',
            'is_active' => true,
        ]);
        $projectA = Project::create([
            'organization_id' => $organization->id,
            'mission_id' => $mission->id,
            'code' => 'PROJECT_A',
            'name' => 'Projet A',
            'is_active' => true,
        ]);
        $projectB = Project::create([
            'organization_id' => $organization->id,
            'mission_id' => $mission->id,
            'code' => 'PROJECT_B',
            'name' => 'Projet B',
            'is_active' => true,
        ]);
        $projectAdmin = User::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        $projectAdmin->roles()->attach(Role::where('code', 'project_admin')->firstOrFail(), [
            'scope_type' => 'project',
            'scope_id' => $projectA->id,
        ]);
        Sanctum::actingAs($projectAdmin);

        $facility = $this->postJson("/api/v1/organizations/{$organization->id}/facilities", [
            'code' => 'FOSA_A',
            'name' => 'Formation sanitaire A',
            'facility_type' => 'health_center',
            'care_level' => 'primary',
            // Un Admin Projet ne peut pas imposer un autre projet au serveur.
            'project_ids' => [$projectB->id],
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('facility.projects.0.id', $projectA->id)
            ->assertJsonPath('facility.mission.id', $mission->id)
            ->json('facility');

        $this->assertDatabaseHas('health_facility_project', [
            'health_facility_id' => $facility['id'],
            'project_id' => $projectA->id,
        ]);
        $this->assertDatabaseMissing('health_facility_project', [
            'health_facility_id' => $facility['id'],
            'project_id' => $projectB->id,
        ]);

        $this->getJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}")
            ->assertOk()->assertJsonPath('facility.id', $facility['id']);

        $this->putJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}", [
            'code' => 'FOSA_A',
            'name' => 'Formation sanitaire A modifiée',
            'facility_type' => 'health_center',
            'care_level' => 'primary',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('facility.name', 'Formation sanitaire A modifiée');

        $site = $this->postJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}/sites", [
            'code' => 'SITE_A',
            'name' => 'Pharmacie principale A',
            'site_type' => 'stock_and_dispensing',
            'is_active' => true,
        ])->assertCreated()->json('site');

        $this->getJson("/api/v1/organizations/{$organization->id}/facilities/{$facility['id']}/sites/{$site['id']}")
            ->assertOk()->assertJsonPath('site.health_facility.id', $facility['id']);
        $this->getJson("/api/v1/organizations/{$organization->id}/structures")
            ->assertOk()->assertJsonFragment(['id' => $site['id'], 'name' => 'Pharmacie principale A']);

        $siteAdminRole = Role::where('code', 'site_admin')->firstOrFail();
        $password = 'PharmaCare!2026';
        $fosaUser = $this->postJson('/api/v1/users', [
            'name' => 'Gérant FOSA A',
            'first_name' => 'Gérant',
            'last_name' => 'FOSA A',
            'username' => 'gerant_fosa_a',
            'email' => 'gerant.fosa.a@example.org',
            'role_id' => $siteAdminRole->id,
            'scope_type' => 'site',
            'scope_id' => $site['id'],
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertCreated()->json('user');

        $this->getJson("/api/v1/users/{$fosaUser['id']}")->assertOk();
        $this->putJson("/api/v1/users/{$fosaUser['id']}", ['phone' => '690000001'])
            ->assertOk()->assertJsonPath('user.phone', '690000001');

        $foreignFacility = HealthFacility::create([
            'organization_id' => $organization->id,
            'mission_id' => $mission->id,
            'code' => 'FOSA_B',
            'name' => 'Formation sanitaire B',
            'facility_type' => 'health_center',
        ]);
        $foreignFacility->projects()->attach($projectB);
        $foreignSite = Site::create([
            'organization_id' => $organization->id,
            'health_facility_id' => $foreignFacility->id,
            'code' => 'SITE_B',
            'name' => 'Site B',
            'site_type' => 'dispensing',
        ]);

        $this->getJson("/api/v1/organizations/{$organization->id}/facilities/{$foreignFacility->id}")->assertNotFound();
        $this->postJson("/api/v1/organizations/{$organization->id}/facilities/{$foreignFacility->id}/sites", [
            'code' => 'FORBIDDEN', 'name' => 'Interdit', 'site_type' => 'dispensing',
        ])->assertForbidden();
        $this->postJson('/api/v1/users', [
            'name' => 'Compte interdit',
            'email' => 'forbidden@example.org',
            'role_id' => $siteAdminRole->id,
            'scope_type' => 'site',
            'scope_id' => $foreignSite->id,
        ])->assertForbidden();

        $this->assertTrue(Hash::check($password, User::findOrFail($fosaUser['id'])->password));
        $this->postJson('/api/v1/auth/login', [
            'login' => 'gerant.fosa.a@example.org',
            'password' => $password,
            'device_name' => 'FOSA E2E Android',
            'device_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            'platform' => 'android',
        ])->assertOk()
            ->assertJsonPath('user.organization_id', $organization->id)
            ->assertJsonPath('user.mission_id', $mission->id)
            ->assertJsonPath('user.project_id', $projectA->id)
            ->assertJsonPath('user.facility_id', $facility['id'])
            ->assertJsonPath('user.site_id', $site['id'])
            ->assertJsonPath('user.dashboard', 'site');
    }
}
