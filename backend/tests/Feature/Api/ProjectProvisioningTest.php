<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Donor;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_coordination_admin_creates_project_funding_and_first_project_admin_atomically(): void
    {
        [$actor, $organization, $mission] = $this->coordination();
        $donor = Donor::create(['organization_id' => $organization->id, 'code' => 'GF', 'name' => 'Fonds mondial']);
        $program = Program::create(['organization_id' => $organization->id, 'donor_id' => $donor->id, 'code' => 'MAL', 'name' => 'Paludisme']);
        Sanctum::actingAs($actor);

        $response = $this->postJson("/api/v1/organizations/{$organization->id}/projects", [
            'mission_id' => $mission->id,
            'code' => 'MAL_2026',
            'name' => 'Projet Paludisme 2026',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-08-31',
            'order_period_months' => 1,
            'delivery_lead_time_months' => 2,
            'safety_stock_months' => 3,
            'is_active' => true,
            'donor_ids' => [$donor->id],
            'program_ids' => [$program->id],
            'admin' => [
                'first_name' => 'Alice',
                'last_name' => 'Projet',
                'email' => 'alice.projet@example.org',
                'username' => 'alice_projet',
                'password' => 'PharmaCare!2026',
                'password_confirmation' => 'PharmaCare!2026',
            ],
        ])->assertCreated()
            ->assertJsonPath('project.code', 'MAL_2026')
            ->assertJsonPath('project.donors.0.id', $donor->id)
            ->assertJsonPath('project.programs.0.id', $program->id)
            ->assertJsonPath('project.order_period_months', 1)
            ->assertJsonPath('project.delivery_lead_time_months', 2)
            ->assertJsonPath('project.safety_stock_months', 3)
            ->assertJsonPath('admin_project.roles.0.code', 'project_admin')
            ->assertJsonPath('admin_project.must_change_password', true)
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('temporary_password');

        $projectId = $response->json('project.id');
        $adminId = $response->json('admin_project.id');
        $this->assertDatabaseHas('role_user', [
            'user_id' => $adminId,
            'role_id' => Role::where('code', 'project_admin')->value('id'),
            'scope_type' => 'project',
            'scope_id' => $projectId,
        ]);
        $this->assertDatabaseHas('project_donors', ['project_id' => $projectId, 'donor_id' => $donor->id]);
        $this->assertDatabaseHas('program_project', ['project_id' => $projectId, 'program_id' => $program->id]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'alice.projet@example.org',
            'password' => 'PharmaCare!2026',
            'device_name' => 'Test Project Admin',
            'device_id' => '11111111-2222-4333-8444-555555555555',
            'platform' => 'android',
        ])->assertOk()->assertJsonPath('user.role', 'project_admin');
    }

    public function test_foreign_funding_reference_is_rejected_without_creating_a_project_or_user(): void
    {
        [$actor, $organization, $mission] = $this->coordination();
        $foreign = Organization::create(['code' => 'FOREIGN', 'name' => 'Organisation étrangère']);
        $donor = Donor::create(['organization_id' => $foreign->id, 'code' => 'BAD', 'name' => 'Bailleur étranger']);
        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/organizations/{$organization->id}/projects", [
            'mission_id' => $mission->id,
            'code' => 'INVALID',
            'name' => 'Projet invalide',
            'donor_ids' => [$donor->id],
            'admin' => [
                'first_name' => 'User', 'last_name' => 'Invalid',
                'email' => 'invalid@example.org',
                'password' => 'PharmaCare!2026',
                'password_confirmation' => 'PharmaCare!2026',
            ],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('projects', ['code' => 'INVALID']);
        $this->assertDatabaseMissing('users', ['email' => 'invalid@example.org']);
    }

    public function test_project_update_replaces_funding_relations_and_returns_complete_project(): void
    {
        [$actor, $organization, $mission] = $this->coordination();
        $unicef = Donor::create(['organization_id' => $organization->id, 'code' => 'UNICEF', 'name' => 'UNICEF']);
        $gffo = Donor::create(['organization_id' => $organization->id, 'code' => 'GFFO', 'name' => 'GFFO']);
        $nutrition = Program::create(['organization_id' => $organization->id, 'donor_id' => $gffo->id, 'code' => 'NUT', 'name' => 'Nutrition']);
        $project = Project::create([
            'organization_id' => $organization->id,
            'mission_id' => $mission->id,
            'code' => 'PROJECT_2026',
            'name' => 'Projet initial',
            'is_active' => true,
        ]);
        $project->donors()->attach($unicef);
        Sanctum::actingAs($actor);

        $this->putJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}", [
            'mission_id' => $mission->id,
            'code' => 'PROJECT_2026',
            'name' => 'Projet actualisé',
            'donor_ids' => [$gffo->id],
            'program_ids' => [$nutrition->id],
            'order_period_months' => 3,
            'delivery_lead_time_months' => 2,
            'safety_stock_months' => 1,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('project.name', 'Projet actualisé')
            ->assertJsonPath('project.donors.0.id', $gffo->id)
            ->assertJsonPath('project.programs.0.id', $nutrition->id)
            ->assertJsonPath('project.order_period_months', 3);

        $this->assertDatabaseMissing('project_donors', ['project_id' => $project->id, 'donor_id' => $unicef->id]);
        $this->assertDatabaseHas('project_donors', ['project_id' => $project->id, 'donor_id' => $gffo->id]);
        $this->assertDatabaseHas('program_project', ['project_id' => $project->id, 'program_id' => $nutrition->id]);
    }

    /** @return array{User, Organization, Mission} */
    private function coordination(): array
    {
        $organization = Organization::create(['code' => 'ORG_CM', 'name' => 'Organisation Cameroun']);
        $country = Country::where('iso2', 'CM')->firstOrFail();
        $mission = Mission::create([
            'organization_id' => $organization->id,
            'country_id' => $country->id,
            'code' => 'CM',
            'name' => 'Coordination Cameroun',
        ]);
        $actor = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $actor->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), [
            'scope_type' => 'mission', 'scope_id' => $mission->id,
        ]);

        return [$actor, $organization, $mission];
    }
}
