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

    private function administrator(): User
    {
        $view = Permission::create(['code' => 'missions.view', 'name' => 'Consulter les missions']);
        $manage = Permission::create(['code' => 'missions.manage', 'name' => 'Gérer les missions']);
        $role = Role::create(['code' => 'mission_admin', 'name' => 'Administrateur missions']);
        $role->permissions()->attach([$view->id, $manage->id]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id, ['scope_type' => 'platform']);
        return $user;
    }

    public function test_mission_is_scoped_to_its_organization_and_country(): void
    {
        Sanctum::actingAs($this->administrator());
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Test']);
        $other = Organization::create(['code' => 'AUTRE', 'name' => 'Autre ONG']);
        $country = Country::create(['iso2' => 'CM', 'name' => 'Cameroun']);

        $created = $this->postJson("/api/v1/organizations/{$organization->id}/missions", [
            'country_id' => $country->id,
            'code' => 'CM_YDE',
            'name' => 'Mission Yaoundé',
            'starts_on' => '2026-01-01',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('mission.country.iso2', 'CM');

        $missionId = $created->json('mission.id');
        $this->getJson("/api/v1/organizations/{$organization->id}/missions")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/organizations/{$other->id}/missions")
            ->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/organizations/{$other->id}/missions/{$missionId}", [
            'country_id' => $country->id, 'code' => 'CM_YDE', 'name' => 'Intrusion',
        ])->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['event' => 'mission.created', 'auditable_id' => $missionId]);
        $this->deleteJson("/api/v1/organizations/{$organization->id}/missions/{$missionId}")->assertNoContent();
        $this->postJson("/api/v1/organizations/{$organization->id}/missions/archived/{$missionId}/restore")
            ->assertOk()->assertJsonPath('mission.is_active', true);
    }

    public function test_dates_and_permissions_are_validated(): void
    {
        Sanctum::actingAs($this->administrator());
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Test']);
        $country = Country::create(['iso2' => 'FR', 'name' => 'France']);
        $this->postJson("/api/v1/organizations/{$organization->id}/missions", [
            'country_id' => $country->id, 'code' => 'BAD', 'name' => 'Dates invalides',
            'starts_on' => '2026-12-31', 'ends_on' => '2026-01-01',
        ])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/organizations/{$organization->id}/missions")->assertForbidden();
    }
}
