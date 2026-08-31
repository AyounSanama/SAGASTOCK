<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinationCountryScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordination_admin_only_reads_its_coordination_and_cannot_mutate_missions(): void
    {
        [$user, $organization, $mission, $otherMission] = $this->context();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/organizations/{$organization->id}/missions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mission->id);

        $this->postJson("/api/v1/organizations/{$organization->id}/missions", [
            'country_id' => $mission->country_id,
            'code' => 'FORBIDDEN',
            'name' => 'Modification interdite',
        ])->assertForbidden();

        $this->actingAs($user)
            ->get(route('organizations.missions.show', [$organization, $otherMission]))
            ->assertNotFound();
    }

    public function test_projects_are_created_only_inside_the_assigned_coordination(): void
    {
        [$user, $organization, $mission, $otherMission] = $this->context();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/organizations/{$organization->id}/projects", [
            'mission_id' => $mission->id,
            'code' => 'OWN-PROJECT',
            'name' => 'Projet autorisé',
            'is_active' => true,
        ])->assertCreated();

        $this->postJson("/api/v1/organizations/{$organization->id}/projects", [
            'mission_id' => $otherMission->id,
            'code' => 'CROSS-PROJECT',
            'name' => 'Projet hors périmètre',
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseHas('projects', ['mission_id' => $mission->id, 'code' => 'OWN-PROJECT']);
        $this->assertDatabaseMissing('projects', ['code' => 'CROSS-PROJECT']);
    }

    public function test_login_payload_exposes_offline_coordination_contract_and_navigation(): void
    {
        [$user, , $mission] = $this->context();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'Secret@123',
            'device_name' => 'Android test',
            'device_id' => '123e4567-e89b-12d3-a456-426614174333',
            'platform' => 'android',
        ])->assertOk()
            ->assertJsonPath('user.mission_id', $mission->id)
            ->assertJsonPath('user.country_id', $mission->country_id)
            ->assertJsonPath('user.coordination.id', $mission->id);

        $missionNavigation = collect($response->json('user.navigation'))->firstWhere('key', 'missions');
        $this->assertSame('Ma Coordination', $missionNavigation['label']);
        $this->assertContains($mission->id, $response->json('user.access_scope.mission_ids'));
    }

    private function context(): array
    {
        $organization = Organization::create(['code' => 'COORD', 'name' => 'Organisation Coordination']);
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun', 'is_active' => true]);
        $otherCountry = Country::firstOrCreate(['iso2' => 'TD'], ['name' => 'Tchad', 'is_active' => true]);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'COORD-CM', 'name' => 'Coordination Cameroun', 'is_active' => true]);
        $otherMission = Mission::create(['organization_id' => $organization->id, 'country_id' => $otherCountry->id, 'code' => 'COORD-TD', 'name' => 'Coordination Tchad', 'is_active' => true]);
        Project::create(['organization_id' => $organization->id, 'mission_id' => $otherMission->id, 'code' => 'OTHER', 'name' => 'Autre projet', 'is_active' => true]);

        $permissions = collect(['missions.view', 'missions.manage', 'projects.view', 'projects.manage', 'dashboard.view'])
            ->map(fn (string $code) => Permission::firstOrCreate(['code' => $code], ['name' => $code]));
        $role = Role::create(['code' => 'coordination_admin', 'name' => 'Admin Coordination', 'scope_type' => 'mission', 'is_active' => true]);
        $role->permissions()->attach($permissions);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'password' => 'Secret@123',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->roles()->attach($role, ['scope_type' => 'mission', 'scope_id' => $mission->id]);

        return [$user, $organization, $mission, $otherMission];
    }
}
