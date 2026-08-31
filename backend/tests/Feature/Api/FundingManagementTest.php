<?php

namespace Tests\Feature\Api;

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

class FundingManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $view = Permission::create(['code' => 'funding.view', 'name' => 'Consulter les financements']);
        $manage = Permission::create(['code' => 'funding.manage', 'name' => 'Gérer les financements']);
        $projects = Permission::firstOrCreate(['code' => 'projects.view'], ['name' => 'Consulter les projets']);
        $role = Role::create(['code' => 'funding_admin', 'name' => 'Administrateur financements']);
        $role->permissions()->attach([$view->id, $manage->id, $projects->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['scope_type' => 'platform']);
        return $user;
    }

    private function context(): array
    {
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG']);
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun']);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'MISSION', 'name' => 'Mission']);
        $project = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'PROJECT', 'name' => 'Projet']);
        return [$organization, $project];
    }

    public function test_donor_and_program_can_be_created_and_attached_to_project(): void
    {
        Sanctum::actingAs($this->administrator());
        [$organization, $project] = $this->context();

        $donor = $this->postJson("/api/v1/organizations/{$organization->id}/donors", [
            'code' => 'UE', 'name' => 'Union européenne',
        ])->assertCreated()->json('donor');
        $program = $this->postJson("/api/v1/organizations/{$organization->id}/programs", [
            'donor_id' => $donor['id'], 'code' => 'SANTE', 'name' => 'Programme santé',
        ])->assertCreated()->json('program');

        $this->postJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/donors", [
            'donor_id' => $donor['id'], 'funding_amount' => 125000, 'currency' => 'eur',
        ])->assertOk();
        $this->postJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/programs", [
            'program_id' => $program['id'],
        ])->assertOk();
        $this->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/funding")
            ->assertOk()->assertJsonCount(1, 'project_donors')->assertJsonCount(1, 'project_programs');

        $this->assertDatabaseHas('project_donors', ['project_id' => $project->id, 'donor_id' => $donor['id'], 'currency' => 'EUR']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'project.program_attached', 'auditable_id' => $project->id]);

        $this->putJson("/api/v1/organizations/{$organization->id}/donors/{$donor['id']}", [
            'code' => 'UNICEF', 'name' => 'UNICEF actualisé', 'is_active' => true,
        ])->assertOk()->assertJsonPath('donor.name', 'UNICEF actualisé');
        $this->putJson("/api/v1/organizations/{$organization->id}/programs/{$program['id']}", [
            'donor_id' => $donor['id'], 'code' => 'SANTE', 'name' => 'Programme santé actualisé', 'is_active' => true,
        ])->assertOk();
        $this->deleteJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/donors/{$donor['id']}")->assertNoContent();
        $this->deleteJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/programs/{$program['id']}")->assertNoContent();
        $this->deleteJson("/api/v1/organizations/{$organization->id}/donors/{$donor['id']}")->assertNoContent();
        $this->getJson("/api/v1/organizations/{$organization->id}/projects-setup")
            ->assertOk()
            ->assertJsonPath('archived_donors.0.id', $donor['id']);
        $this->postJson("/api/v1/organizations/{$organization->id}/donors/archived/{$donor['id']}/restore")->assertOk();
        $this->deleteJson("/api/v1/organizations/{$organization->id}/programs/{$program['id']}")->assertNoContent();
        $this->getJson("/api/v1/organizations/{$organization->id}/projects-setup")
            ->assertOk()
            ->assertJsonPath('archived_programs.0.id', $program['id']);
        $this->postJson("/api/v1/organizations/{$organization->id}/programs/archived/{$program['id']}/restore")->assertOk();
    }

    public function test_cross_organization_donor_cannot_be_attached(): void
    {
        Sanctum::actingAs($this->administrator());
        [$organization, $project] = $this->context();
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Autre']);
        $donor = $this->postJson("/api/v1/organizations/{$other->id}/donors", ['code' => 'BAD', 'name' => 'Autre bailleur'])
            ->assertCreated()->json('donor');
        $this->postJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/donors", [
            'donor_id' => $donor['id'],
        ])->assertUnprocessable();
    }

    public function test_sidebar_funding_module_opens_real_workspace_and_persists_web_forms(): void
    {
        $admin = $this->administrator();
        [$organization, $project] = $this->context();

        $this->actingAs($admin)->get('/funding')
            ->assertOk()
            ->assertSee('Configuration des projets')
            ->assertSee('Projets')
            ->assertSee('Bailleurs')
            ->assertSee('Programmes')
            ->assertSee('donor-create-sheet')
            ->assertSee('overflow-x:clip', false)
            ->assertSee('grid-template-columns:minmax(0,1fr) minmax(0,1fr)', false)
            ->assertSee($project->name);

        $this->actingAs($admin)->get('/funding?section=programs')
            ->assertOk()
            ->assertSee('section-programs', false)
            ->assertSee('Ajouter un programme');

        $this->actingAs($admin)->from('/funding')->post("/organizations/{$organization->id}/donors", [
            'code' => 'GAVI', 'name' => 'Alliance Gavi', 'email' => 'contact@gavi.test',
        ])->assertRedirect('/funding')->assertSessionHas('status');
        $donor = $organization->donors()->where('code', 'GAVI')->firstOrFail();

        $this->actingAs($admin)->from('/funding')->post("/organizations/{$organization->id}/programs", [
            'donor_id' => $donor->id, 'code' => 'VACCINS', 'name' => 'Programme Vaccins',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ])->assertRedirect('/funding')->assertSessionHas('status');
        $program = $organization->programs()->where('code', 'VACCINS')->firstOrFail();

        $this->actingAs($admin)->from('/funding')->post("/organizations/{$organization->id}/projects/{$project->id}/donors", [
            'donor_id' => $donor->id, 'funding_amount' => 250000, 'currency' => 'xaf',
            'agreement_reference' => 'CONV-2026',
        ])->assertRedirect('/funding');
        $this->actingAs($admin)->from('/funding')->post("/organizations/{$organization->id}/projects/{$project->id}/programs", [
            'program_id' => $program->id,
        ])->assertRedirect('/funding');

        $this->actingAs($admin)->get('/funding?organization_id='.$organization->id.'&project_id='.$project->id)
            ->assertOk()->assertSee('Alliance Gavi')->assertSee('Programme Vaccins')->assertSee('CONV-2026');
        $this->assertDatabaseHas('project_donors', [
            'project_id' => $project->id, 'donor_id' => $donor->id, 'currency' => 'XAF',
        ]);
    }

    public function test_sidebar_funding_workspace_rejects_an_organization_outside_user_scope(): void
    {
        [$organization] = $this->context();
        $other = Organization::create(['code' => 'OTHER-SCOPE', 'name' => 'Organisation hors périmètre']);
        $role = Role::create(['code' => 'scoped_funding', 'name' => 'Financement organisation']);
        $role->permissions()->attach([
            Permission::firstOrCreate(['code' => 'funding.view'], ['name' => 'Consulter les financements'])->id,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['scope_type' => 'organization', 'scope_id' => $organization->id]);

        $this->actingAs($user)->get('/funding?organization_id='.$other->id)->assertNotFound();
    }
}
