<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $view = Permission::create(['code' => 'projects.view', 'name' => 'Consulter les projets']);
        $manage = Permission::create(['code' => 'projects.manage', 'name' => 'Gérer les projets']);
        $role = Role::create(['code' => 'custom_project_manager', 'name' => 'Administrateur projets']);
        $role->permissions()->attach([$view->id, $manage->id]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id, ['scope_type' => 'platform']);
        return $user;
    }

    public function test_project_is_scoped_to_organization_and_mission(): void
    {
        Sanctum::actingAs($this->administrator());
        $country = Country::create(['iso2' => 'CM', 'name' => 'Cameroun']);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG']);
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Autre']);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'MISSION', 'name' => 'Mission']);

        $created = $this->postJson("/api/v1/organizations/{$organization->id}/projects", [
            'mission_id' => $mission->id, 'code' => 'PROJET_1', 'name' => 'Projet santé', 'is_active' => true,
        ])->assertCreated()->assertJsonPath('project.mission.country.iso2', 'CM');
        $id = $created->json('project.id');

        $this->getJson("/api/v1/organizations/{$organization->id}/projects")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/organizations/{$other->id}/projects")->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/organizations/{$other->id}/projects/{$id}", [
            'mission_id' => $mission->id, 'code' => 'PROJET_1', 'name' => 'Intrusion',
        ])->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['event' => 'project.created', 'auditable_id' => $id]);
        $this->deleteJson("/api/v1/organizations/{$organization->id}/projects/{$id}")->assertNoContent();
        $this->postJson("/api/v1/organizations/{$organization->id}/projects/archived/{$id}/restore")
            ->assertOk()->assertJsonPath('project.is_active', true);
    }

    public function test_project_rejects_a_mission_from_another_organization(): void
    {
        Sanctum::actingAs($this->administrator());
        $country = Country::create(['iso2' => 'FR', 'name' => 'France']);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG']);
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Autre']);
        $mission = Mission::create(['organization_id' => $other->id, 'country_id' => $country->id, 'code' => 'OTHER', 'name' => 'Autre mission']);
        $this->postJson("/api/v1/organizations/{$organization->id}/projects", [
            'mission_id' => $mission->id, 'code' => 'BAD', 'name' => 'Projet invalide',
        ])->assertUnprocessable();
    }
}
