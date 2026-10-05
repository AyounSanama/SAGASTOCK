<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCreationEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_coordination_sees_the_official_project_form_from_page_and_dashboard(): void
    {
        [$actor, $organization, $mission] = $this->coordination();

        $this->actingAs($actor)->get('/projects')
            ->assertOk()
            ->assertSee('Créer un projet')
            ->assertSee('data-sheet-open="project-create-sheet"', false)
            ->assertSee('aria-labelledby="project-create-sheet-title"', false)
            ->assertSee('name="admin[email]"', false)
            ->assertSee('funding-picker', false)
            ->assertSee('Aucun bailleur disponible');

        // Tableau de bord de la Coordination (maquette 07, AM-172) : pas de
        // raccourci de création ; le bouton unique est dans « Ma Coordination ».
        $this->actingAs($actor)->get('/dashboard')
            ->assertOk()
            ->assertSee('Situation par projet')
            ->assertDontSee('Créer un utilisateur');

        $this->actingAs($actor)->get('/organizations/'.$organization->id.'/missions/'.$mission->id)
            ->assertOk()
            ->assertSee('/projects?create=1', false);
    }

    public function test_project_admin_never_sees_project_creation_action(): void
    {
        [, $organization, $mission] = $this->coordination();
        $project = Project::create([
            'organization_id' => $organization->id,
            'mission_id' => $mission->id,
            'code' => 'P1',
            'name' => 'Projet existant',
        ]);
        $actor = User::factory()->create(['organization_id' => $organization->id]);
        $actor->roles()->attach(Role::where('code', 'project_admin')->firstOrFail(), [
            'scope_type' => 'project',
            'scope_id' => $project->id,
        ]);

        $this->actingAs($actor)->get('/projects')
            ->assertOk()
            ->assertDontSee('name="admin[email]"', false)
            ->assertDontSee('Créer un projet');
        $this->actingAs($actor)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Créer un projet')
            ->assertDontSee('Organisations accessibles')
            ->assertDontSee('Votre périmètre actuel')
            ->assertDontSee('Aucune organisation accessible');
    }

    public function test_mission_scope_alone_resolves_its_organization(): void
    {
        [$actor, $organization] = $this->coordination(false);

        $this->assertNull($actor->organization_id);
        $this->actingAs($actor)->get('/projects')
            ->assertOk()
            ->assertSee($organization->name)
            ->assertSee('data-sheet-open="project-create-sheet"', false);
    }

    /** @return array{User, Organization, Mission} */
    private function coordination(bool $storeOrganizationOnUser = true): array
    {
        $organization = Organization::create(['code' => 'ORG_CM', 'name' => 'Organisation Cameroun']);
        $mission = Mission::create([
            'organization_id' => $organization->id,
            'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => 'CM',
            'name' => 'Coordination Cameroun',
        ]);
        $actor = User::factory()->create([
            'organization_id' => $storeOrganizationOnUser ? $organization->id : null,
            'is_active' => true,
        ]);
        $actor->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), [
            'scope_type' => 'mission',
            'scope_id' => $mission->id,
        ]);

        return [$actor, $organization, $mission];
    }
}
