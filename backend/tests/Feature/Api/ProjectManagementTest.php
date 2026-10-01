<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Admin Coordination de la mission (rôle officiel qui gère les projets). */
    private function administrator(Mission $mission): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['organization_id' => $mission->organization_id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $mission->id]);

        return $user;
    }

    public function test_project_is_scoped_to_organization_and_mission(): void
    {
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun']);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG']);
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Autre']);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'MISSION', 'name' => 'Mission']);
        Sanctum::actingAs($this->administrator($mission));

        $created = $this->postJson("/api/v1/organizations/{$organization->id}/projects", ['order_period_months' => 1, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 1, 
            'mission_id' => $mission->id, 'code' => 'PROJET_1', 'name' => 'Projet santé', 'is_active' => true,
        ])->assertCreated()->assertJsonPath('project.mission.country.iso2', 'CM');
        $id = $created->json('project.id');

        $this->getJson("/api/v1/organizations/{$organization->id}/projects")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/organizations/{$organization->id}/projects?search=PROJET_1&status=active")
            ->assertOk()->assertJsonCount(1, 'data');
        // Organisation hors périmètre de la Coordination : introuvable.
        $this->getJson("/api/v1/organizations/{$other->id}/projects")->assertNotFound();
        $this->putJson("/api/v1/organizations/{$other->id}/projects/{$id}", [
            'mission_id' => $mission->id, 'code' => 'PROJET_1', 'name' => 'Intrusion',
        ])->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['event' => 'project.created', 'auditable_id' => $id]);
        $this->deleteJson("/api/v1/organizations/{$organization->id}/projects/{$id}")->assertNoContent();
        $this->getJson("/api/v1/organizations/{$organization->id}/projects/archived")
            ->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson("/api/v1/organizations/{$organization->id}/projects/archived/{$id}/restore")
            ->assertOk()->assertJsonPath('project.is_active', true);
    }

    public function test_project_rejects_a_mission_from_another_organization(): void
    {
        $country = Country::firstOrCreate(['iso2' => 'FR'], ['name' => 'France']);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG']);
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Autre']);
        $ownMission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'OWN', 'name' => 'Mission']);
        $mission = Mission::create(['organization_id' => $other->id, 'country_id' => $country->id, 'code' => 'OTHER', 'name' => 'Autre mission']);
        Sanctum::actingAs($this->administrator($ownMission));
        $this->postJson("/api/v1/organizations/{$organization->id}/projects", ['order_period_months' => 1, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 1, 
            'mission_id' => $mission->id, 'code' => 'BAD', 'name' => 'Projet invalide',
        ])->assertUnprocessable();
    }
}
