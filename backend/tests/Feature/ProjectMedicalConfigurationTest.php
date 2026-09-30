<?php

namespace Tests\Feature;

use App\Models\CatalogReference;
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

/** AM-112 — Projet → Niveaux de soins → Populations cibles → Pathologies. */
class ProjectMedicalConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Project $project;

    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $mission = Mission::create([
            'organization_id' => $this->organization->id,
            'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => 'MAROUA', 'name' => 'Coordination Maroua',
        ]);
        $this->project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $mission->id, 'code' => 'NUT', 'name' => 'Projet Nutrition']);
        $this->coordination = $this->actor('coordination_admin', 'mission', $mission->id);
    }

    private function actor(string $role, string $scopeType, string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function reference(string $type, string $code, string $name): CatalogReference
    {
        return CatalogReference::create(['organization_id' => $this->organization->id, 'reference_type' => $type, 'code' => $code, 'name' => $name]);
    }

    private function careLevel(string $code): string
    {
        return CatalogReference::whereNull('organization_id')->where('code', $code)->value('id');
    }

    public function test_coordination_configures_the_project_and_project_admin_reads_it(): void
    {
        $adults = $this->reference('target_population', 'ADULT', 'Adultes');
        $children = $this->reference('target_population', 'U5', 'Enfants < 5 ans');
        $malaria = $this->reference('pathology', 'PALU', 'Paludisme');
        $sam = $this->reference('pathology', 'MAS', 'Malnutrition aiguë sévère');
        Sanctum::actingAs($this->coordination);

        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", [
            'care_level_ids' => [$this->careLevel('SSP'), $this->careLevel('SSP-PEC-MALNUT')],
            'target_population_ids' => [$adults->id, $children->id],
            'pathologies' => [
                ['pathology_id' => $malaria->id, 'target_population_ids' => [$adults->id, $children->id]],
                ['pathology_id' => $sam->id, 'target_population_ids' => [$children->id]],
            ],
        ])->assertOk()
            ->assertJsonPath('configuration.is_configured', true)
            ->assertJsonCount(2, 'configuration.care_levels')
            ->assertJsonCount(2, 'configuration.pathologies');

        $this->assertDatabaseCount('project_pathology_populations', 3);
        $this->assertDatabaseHas('audit_logs', ['event' => 'project.medical_configuration.updated', 'auditable_id' => $this->project->id]);

        $projectAdmin = $this->actor('project_admin', 'project', $this->project->id);
        Sanctum::actingAs($projectAdmin);
        $this->getJson("/api/v1/projects/{$this->project->id}/medical-configuration")
            ->assertOk()->assertJsonPath('can_manage', false)->assertJsonPath('options', null)
            ->assertJsonFragment(['name' => 'Malnutrition aiguë sévère']);
        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", [
            'care_level_ids' => [$this->careLevel('SSS')], 'target_population_ids' => [$adults->id],
        ])->assertForbidden();

        $this->actingAs($projectAdmin)->get(route('projects.medical-configuration', $this->project))
            ->assertOk()->assertSee('Paludisme')->assertDontSee('Enregistrer la configuration médicale');
    }

    public function test_pathology_can_only_target_populations_selected_for_the_project(): void
    {
        $adults = $this->reference('target_population', 'ADULT', 'Adultes');
        $pregnant = $this->reference('target_population', 'PREG', 'Femmes enceintes');
        $malaria = $this->reference('pathology', 'PALU', 'Paludisme');

        $this->actingAs($this->coordination)->get(route('projects.medical-configuration', $this->project))
            ->assertOk()->assertSee('Enregistrer la configuration médicale')->assertSee('Programme PEC VIH')->assertSee('Femmes enceintes');

        $this->actingAs($this->coordination)
            ->put(route('projects.medical-configuration.update', $this->project), [
                'care_level_ids' => [$this->careLevel('SSP')],
                'target_population_ids' => [$adults->id],
                'pathologies' => [$malaria->id => [$pregnant->id]],
            ])->assertSessionHasErrors('pathologies.0.target_population_ids');

        $this->assertDatabaseCount('project_target_populations', 0);
    }

    public function test_references_from_another_organization_or_of_the_wrong_type_are_refused(): void
    {
        $other = Organization::create(['code' => 'MSF', 'name' => 'MSF']);
        $foreign = CatalogReference::create(['organization_id' => $other->id, 'reference_type' => 'target_population', 'code' => 'X', 'name' => 'Étrangère']);
        $pathologyAsPopulation = $this->reference('pathology', 'PALU', 'Paludisme');
        Sanctum::actingAs($this->coordination);

        foreach ([$foreign->id, $pathologyAsPopulation->id] as $invalid) {
            $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", [
                'care_level_ids' => [$this->careLevel('SSP')], 'target_population_ids' => [$invalid],
            ])->assertUnprocessable()->assertJsonValidationErrors('target_population_ids');
        }
    }

    public function test_coordination_adds_populations_and_pathologies_to_its_referential(): void
    {
        $this->actingAs($this->coordination)
            ->post(route('projects.medical-references.store', 'target_population'), ['code' => 'PREG', 'name' => 'Femmes enceintes'])
            ->assertSessionHasNoErrors();
        Sanctum::actingAs($this->coordination);
        $this->postJson('/api/v1/projects/medical-references/pathology', ['code' => 'TB', 'name' => 'Tuberculose'])->assertCreated();
        $this->postJson('/api/v1/projects/medical-references/pathology', ['code' => 'TB', 'name' => 'Doublon'])->assertUnprocessable();

        $this->getJson('/api/v1/projects/medical-references')
            ->assertOk()->assertJsonFragment(['name' => 'Femmes enceintes'])->assertJsonFragment(['name' => 'Tuberculose']);
    }
}
