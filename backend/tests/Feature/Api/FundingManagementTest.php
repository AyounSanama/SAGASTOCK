<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FundingManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Admin Coordination de la mission du projet (rôle officiel qui configure les projets). */
    private function administrator(Project $project): User
    {
        $user = User::factory()->create(['organization_id' => $project->organization_id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $project->mission_id]);

        return $user;
    }

    private function context(): array
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG']);
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun']);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'MISSION', 'name' => 'Mission']);
        $project = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'PROJECT', 'name' => 'Projet']);
        return [$organization, $project];
    }

    public function test_donor_and_program_can_be_created_and_attached_to_project(): void
    {
        [$organization, $project] = $this->context();
        Sanctum::actingAs($this->administrator($project));

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
        [$organization, $project] = $this->context();
        Sanctum::actingAs($this->administrator($project));
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Autre']);
        $donor = $other->donors()->create(['code' => 'BAD', 'name' => 'Autre bailleur'])->toArray();
        // La Coordination ne peut pas non plus créer de bailleur dans une autre organisation.
        $this->assertContains($this->postJson("/api/v1/organizations/{$other->id}/donors", ['code' => 'BAD2', 'name' => 'Intrus'])->status(), [403, 404]);
        $this->postJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/donors", [
            'donor_id' => $donor['id'],
        ])->assertUnprocessable();
    }

    public function test_sidebar_funding_module_opens_real_workspace_and_persists_web_forms(): void
    {
        // « Configuration des projets » (Web) : doublon à retirer du menu (check-up des
        // maquettes, 01/10). Ses formulaires visent /organizations/..., bloqué par le
        // masquage V1 pour la Coordination : on vérifie ici la logique conservée.
        $this->withoutMiddleware(\App\Http\Middleware\EnforceV1ModuleAvailability::class);
        [$organization, $project] = $this->context();
        $admin = $this->administrator($project);

        // Niveau 2 : la Coordination y voit « Référentiels > Bailleurs » ; Programmes
        // et rattachement au projet passent par l'assistant (onglets masqués).
        $this->actingAs($admin)->get('/funding')
            ->assertOk()
            ->assertSee('Référentiels')
            ->assertSee('Bailleurs')
            ->assertSee('Référentiel médical')
            ->assertDontSee('Ajouter un programme')
            ->assertSee('donor-create-sheet')
            ->assertSee('overflow-x:clip', false);

        $this->actingAs($admin)->get('/funding?section=programs')
            ->assertOk()
            ->assertSee('section-donors', false)
            ->assertDontSee('Associations au projet');

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

        // Rattachements enregistrés ; la page « Référentiels » ne liste que les bailleurs.
        $this->actingAs($admin)->get('/funding?organization_id='.$organization->id.'&project_id='.$project->id)
            ->assertOk()->assertSee('Alliance Gavi')->assertDontSee('CONV-2026');
        $this->assertDatabaseHas('program_project', ['project_id' => $project->id, 'program_id' => $program->id]);
        $this->assertDatabaseHas('project_donors', [
            'project_id' => $project->id, 'donor_id' => $donor->id, 'currency' => 'XAF',
        ]);
    }

    public function test_sidebar_funding_workspace_rejects_an_organization_outside_user_scope(): void
    {
        [$organization, $project] = $this->context();
        $other = Organization::create(['code' => 'OTHER-SCOPE', 'name' => 'Organisation hors périmètre']);
        $user = $this->administrator($project);

        $this->actingAs($user)->get('/funding?organization_id='.$other->id)->assertNotFound();
    }
}
