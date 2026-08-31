<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MissionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(Organization $organization): User
    {
        $view = Permission::create(['code' => 'missions.view', 'name' => 'Consulter les missions']);
        $manage = Permission::create(['code' => 'missions.manage', 'name' => 'Gérer les missions']);
        $role = Role::create(['code' => 'mission_manager_test', 'name' => 'Gestionnaire de missions']);
        $role->permissions()->attach([$view->id, $manage->id]);
        $user = User::factory()->create([
            'is_active' => true,
            'organization_id' => $organization->id,
        ]);
        $user->roles()->attach($role->id, [
            'scope_type' => 'organization',
            'scope_id' => $organization->id,
        ]);

        return $user;
    }

    public function test_mission_is_scoped_to_its_organization_and_country(): void
    {
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Test']);
        $other = Organization::create(['code' => 'AUTRE', 'name' => 'Autre ONG']);
        Sanctum::actingAs($this->administrator($organization));
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun']);
        $organization->countries()->attach($country->id);

        $created = $this->postJson("/api/v1/organizations/{$organization->id}/missions", [
            'country_id' => $country->id,
            'code' => 'CM_YDE',
            'name' => 'Mission Yaoundé',
            'starts_on' => '2026-01-01',
            'address' => 'Yaoundé',
            'manager_name' => 'Responsable mission',
            'phone' => '+237600000000',
            'email' => 'mission@example.org',
            'description' => 'Programme national',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('mission.country.iso2', 'CM');

        $missionId = $created->json('mission.id');
        $this->assertDatabaseHas('missions', [
            'id' => $missionId,
            'address' => 'Yaoundé',
            'manager_name' => 'Responsable mission',
            'email' => 'mission@example.org',
        ]);
        $this->getJson("/api/v1/organizations/{$organization->id}/missions")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/organizations/{$other->id}/missions")
            ->assertNotFound();
        $this->putJson("/api/v1/organizations/{$other->id}/missions/{$missionId}", [
            'country_id' => $country->id, 'code' => 'CM_YDE', 'name' => 'Intrusion',
        ])->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['event' => 'mission.created', 'auditable_id' => $missionId]);
        $this->deleteJson("/api/v1/organizations/{$organization->id}/missions/{$missionId}")->assertNoContent();
        $this->getJson("/api/v1/organizations/{$organization->id}/missions-archived")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $missionId);
        $this->postJson("/api/v1/organizations/{$organization->id}/missions/archived/{$missionId}/restore")
            ->assertOk()->assertJsonPath('mission.is_active', true);
        $this->getJson("/api/v1/organizations/{$organization->id}/missions-archived")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_list_supports_search_country_and_status_filters(): void
    {
        $organization = Organization::create(['code' => 'FILTER', 'name' => 'ONG Filtres']);
        Sanctum::actingAs($this->administrator($organization));
        $cameroon = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun']);
        $chad = Country::firstOrCreate(['iso2' => 'TD'], ['name' => 'Tchad']);
        $organization->countries()->sync([$cameroon->id, $chad->id]);
        $organization->missions()->create(['country_id' => $cameroon->id, 'code' => 'CM-ACTIVE', 'name' => 'Mission Cameroun', 'is_active' => true]);
        $organization->missions()->create(['country_id' => $chad->id, 'code' => 'TD-PAUSE', 'name' => 'Mission Tchad', 'is_active' => false]);

        $this->getJson("/api/v1/organizations/{$organization->id}/missions?search=Cameroun")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'CM-ACTIVE');
        $this->getJson("/api/v1/organizations/{$organization->id}/missions?country_id={$chad->id}&status=inactive")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'TD-PAUSE');
    }

    public function test_dates_and_permissions_are_validated(): void
    {
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Test']);
        Sanctum::actingAs($this->administrator($organization));
        $country = Country::firstOrCreate(['iso2' => 'FR'], ['name' => 'France']);
        $organization->countries()->attach($country->id);
        $this->postJson("/api/v1/organizations/{$organization->id}/missions", [
            'country_id' => $country->id, 'code' => 'BAD', 'name' => 'Dates invalides',
            'starts_on' => '2026-12-31', 'ends_on' => '2026-01-01',
        ])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/organizations/{$organization->id}/missions")->assertForbidden();
    }

    public function test_mission_manager_can_only_use_countries_authorized_for_its_organization(): void
    {
        $organization = Organization::create(['code' => 'MULTI', 'name' => 'ONG Multipays']);
        Sanctum::actingAs($this->administrator($organization));
        $cameroon = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun']);
        $chad = Country::firstOrCreate(['iso2' => 'TD'], ['name' => 'Tchad']);
        $france = Country::firstOrCreate(['iso2' => 'FR'], ['name' => 'France']);
        $organization->countries()->sync([$cameroon->id, $chad->id]);

        foreach ([[$cameroon, 'MISSION_CM'], [$chad, 'MISSION_TD']] as [$country, $code]) {
            $this->postJson("/api/v1/organizations/{$organization->id}/missions", [
                'country_id' => $country->id,
                'code' => $code,
                'name' => "Mission {$country->name}",
            ])->assertCreated();
        }

        $this->postJson("/api/v1/organizations/{$organization->id}/missions", [
            'country_id' => $france->id,
            'code' => 'MISSION_FR',
            'name' => 'Mission France',
        ])->assertUnprocessable()->assertJsonValidationErrors('country_id');

        $this->assertDatabaseCount('missions', 2);
        $this->assertDatabaseMissing('missions', ['organization_id' => $organization->id, 'country_id' => $france->id]);
    }
}
