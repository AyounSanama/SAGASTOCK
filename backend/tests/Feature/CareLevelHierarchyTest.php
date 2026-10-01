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

/** AM-111 — Niveaux de soins hiérarchiques : Niveau → Catégorie → Programme. */
class CareLevelHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->mission = Mission::create([
            'organization_id' => $this->organization->id,
            'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => 'MAROUA', 'name' => 'Coordination Maroua',
        ]);
    }

    private function actor(string $role, string $scopeType, string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function global(string $code): CatalogReference
    {
        return CatalogReference::whereNull('organization_id')->where('reference_type', 'care_level')->where('code', $code)->firstOrFail();
    }

    public function test_default_hierarchy_from_the_specification_is_available(): void
    {
        $this->assertSame(1, $this->global('SSP')->depth);
        $this->assertSame(2, $this->global('SSP-PEC')->depth);
        $program = $this->global('SSP-PEC-VIH');
        $this->assertSame(3, $program->depth);
        $this->assertSame('Programme PEC VIH', $program->name);
        $this->assertSame($this->global('SSP-PEC')->id, $program->parent_id);

        Sanctum::actingAs($this->actor('coordination_admin', 'mission', $this->mission->id));
        $this->getJson("/api/v1/organizations/{$this->organization->id}/catalog/references/care-level-tree")
            ->assertOk()
            ->assertJsonPath('levels.3', 'Programme')
            ->assertJsonFragment(['code' => 'SSS-PEC-TB', 'level_label' => 'Programme']);
    }

    public function test_coordination_adds_a_new_service_level_and_attaches_children(): void
    {
        $coordination = $this->actor('coordination_admin', 'mission', $this->mission->id);

        $this->actingAs($coordination)->get(route('projects.medical-references'))
            ->assertOk()->assertSee('Programme PEC Paludisme')->assertSee('Ajouter un service');

        $this->post(route('projects.medical-references.care-levels.store'), ['code' => 'STERT', 'name' => 'Soins de santé tertiaire'])
            ->assertSessionHasNoErrors();
        $tertiary = CatalogReference::where('organization_id', $this->organization->id)->where('code', 'STERT')->firstOrFail();
        $this->assertSame(1, $tertiary->depth);

        $this->post(route('projects.medical-references.care-levels.store'), ['code' => 'SSP-NUT', 'name' => 'Nutrition', 'parent_id' => $this->global('SSP')->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('catalog_references', ['code' => 'SSP-NUT', 'depth' => 2, 'parent_id' => $this->global('SSP')->id]);
    }

    public function test_hierarchy_rules_are_enforced_server_side(): void
    {
        Sanctum::actingAs($this->actor('coordination_admin', 'mission', $this->mission->id));
        $url = '/api/v1/projects/medical-references/care-levels';

        // Pas de quatrième niveau.
        $this->postJson($url, ['code' => 'TOO-DEEP', 'name' => 'Trop profond', 'parent_id' => $this->global('SSP-PEC-VIH')->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $category = $this->postJson($url, ['code' => 'CAT', 'name' => 'Catégorie locale', 'parent_id' => $this->global('SSS')->id])
            ->assertCreated()->json('reference.id');
        $this->postJson($url, ['code' => 'PROG', 'name' => 'Programme local', 'parent_id' => $category])
            ->assertCreated();
        // Un parent ne peut pas être archivé tant qu'il a des enfants actifs.
        $this->deleteJson("$url/$category")->assertUnprocessable();
        $this->getJson('/api/v1/projects/medical-references')
            ->assertOk()->assertJsonPath('can_manage', true)->assertJsonFragment(['code' => 'PROG', 'depth' => 3]);
    }

    public function test_foreign_parent_and_project_admin_writes_are_refused(): void
    {
        $other = Organization::create(['code' => 'MSF', 'name' => 'MSF']);
        $foreign = CatalogReference::create(['organization_id' => $other->id, 'reference_type' => 'care_level', 'code' => 'X', 'name' => 'Niveau étranger']);
        $coordination = $this->actor('coordination_admin', 'mission', $this->mission->id);
        $this->actingAs($coordination)
            ->post(route('projects.medical-references.care-levels.store'), ['code' => 'Y', 'name' => 'Intrus', 'parent_id' => $foreign->id])
            ->assertSessionHasErrors('parent_id');

        $project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'P', 'name' => 'Projet']);
        $projectAdmin = $this->actor('project_admin', 'project', $project->id);
        $this->actingAs($projectAdmin)
            ->post(route('projects.medical-references.care-levels.store'), ['code' => 'Z', 'name' => 'Refusé'])
            ->assertForbidden();
        $this->assertDatabaseMissing('catalog_references', ['code' => 'Z']);
    }
}
