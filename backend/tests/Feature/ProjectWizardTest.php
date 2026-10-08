<?php

namespace Tests\Feature;

use App\Models\CatalogReference;
use App\Models\Country;
use App\Models\Donor;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductStandardMapping;
use App\Models\Project;
use App\Models\Role;
use App\Models\StandardListVersion;
use App\Models\User;
use App\Services\ApplicationNavigationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Niveau 2 — Menu « Référentiels » et assistant « Créer un projet / programme » en 4 étapes. */
class ProjectWizardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        $this->mission = Mission::create([
            'organization_id' => $this->organization->id,
            'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => 'YDE', 'name' => 'Coordination Yaoundé',
        ]);
        $this->coordination = $this->user('coordination_admin', 'mission', $this->mission->id);
    }

    public function test_coordination_menu_follows_the_mockups(): void
    {
        $menu = collect(app(ApplicationNavigationService::class)->items($this->coordination))
            ->filter(fn (array $item) => $item['menu'] && $item['key'] !== 'profile');

        $this->assertSame(['Tableau de bord', 'Ma Coordination', 'Référentiels', 'Liste Standard'], $menu->pluck('label')->values()->all());
        // Masqué du menu, pas supprimé : la route reste ouverte.
        $this->actingAs($this->coordination)->get('/projects')->assertOk();

        $this->actingAs($this->coordination)->get('/funding')->assertOk()
            ->assertSee('Référentiels')->assertSee('Bailleurs')->assertSee('Référentiel médical')
            ->assertDontSee('Associations au projet')
            ->assertDontSee('section=programs', false);
    }

    public function test_legacy_entry_points_open_the_wizard(): void
    {
        $this->actingAs($this->coordination)->get('/projects?create=1')->assertRedirect(route('projects.wizard.create'));
        $this->actingAs($this->coordination)->get('/projects')->assertOk()
            ->assertSee(route('projects.wizard.create'), false)
            ->assertDontSee('data-sheet-open="project-create-sheet"', false);
        $this->actingAs($this->coordination)->get(route('projects.wizard.create'))->assertOk()
            ->assertSee('Étape 1 sur 4')->assertSee('Projet bailleur')->assertSee('Programme national')
            ->assertSee('Coordination Yaoundé (votre coordination)')->assertSee('Enregistrer le brouillon');
    }

    public function test_full_wizard_creates_an_active_project_with_its_published_standard_list(): void
    {
        $donor = Donor::create(['organization_id' => $this->organization->id, 'code' => 'BA', 'name' => 'Bailleur A', 'is_active' => true]);
        $adults = $this->ref('target_population', 'ADULT', 'Adultes');
        $malaria = $this->ref('pathology', 'PALU', 'Paludisme simple');
        $act = $this->product('ACT', 'Artéméther / Luméfantrine', $this->node('SSP'), $malaria->id);
        $paracetamol = $this->product('PARA', 'Paracétamol', $this->node('SSP'));
        $extra = Product::create(['organization_id' => $this->organization->id, 'code' => 'ZINC', 'name' => 'Zinc', 'product_type' => 'medicine']);

        // Étapes 1-2 : brouillon.
        $this->actingAs($this->coordination)->post(route('projects.wizard.store'), [
            'type' => 'donor_project', 'mission_id' => $this->mission->id, 'implementing_partner' => 'ONG Santé',
            'donor_id' => $donor->id, 'code' => 'GFFO5', 'name' => 'Appui aux soins de santé primaire',
            'starts_on' => '2026-01-01', 'ends_on' => '2027-12-31',
        ])->assertRedirect();
        $project = Project::where('code', 'GFFO5')->firstOrFail();
        $this->assertSame('draft', $project->status);
        $this->assertFalse($project->is_active);
        $this->assertSame('GFFO5', $project->donor_reference_code);
        $this->assertSame([$donor->id], $project->donors()->pluck('donors.id')->all());

        // Étape 3 : liste régénérée à la volée, produit décoché conservé, ajout manuel.
        $state = ['care_level_ids' => [$this->node('SSP')], 'target_population_ids' => [$adults->id], 'pathology_ids' => [$malaria->id]];
        $this->actingAs($this->coordination)->get(route('projects.wizard.show', [$project, 'standard-list']).'?'.http_build_query([
            'refresh' => 1, ...$state, 'listed' => [$act->id, $paracetamol->id], 'retained' => [$act->id], 'added' => [$extra->id],
        ]))->assertOk()
            ->assertSee('Liste Standard générée : <span data-total>3</span> produits, <span data-retained>2</span> retenus', false)
            ->assertSee('Paludisme simple');

        $this->actingAs($this->coordination)->put(route('projects.wizard.standard-list.update', $project), [
            ...$state, 'retained' => [$act->id, $extra->id], 'added' => [$extra->id],
        ])->assertRedirect(route('projects.wizard.show', [$project, 'supply']));
        $version = StandardListVersion::whereHas('standardList', fn ($query) => $query->where('scope_id', $project->id))->firstOrFail();
        $this->assertSame('draft', $version->status);
        $this->assertEqualsCanonicalizing([$act->id, $extra->id], $version->products()->pluck('products.id')->all());

        // Réouverture : le décochage et l'ajout sont relus depuis la version enregistrée.
        $this->actingAs($this->coordination)->get(route('projects.wizard.show', [$project, 'standard-list']))->assertOk()
            ->assertSee('<span data-retained>2</span>', false)->assertSee('Ajouté');

        // Étape 4 : stock de sécurité décimal, projet actif, liste validée.
        $this->actingAs($this->coordination)->get(route('projects.wizard.show', [$project, 'supply']))->assertOk()
            ->assertSee('Créer le projet')->assertSee('2 produits retenus');
        $this->actingAs($this->coordination)->put(route('projects.wizard.supply.update', $project), [
            'order_period_months' => 3, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 0.5,
            'inventory_date' => '2026-12-25', 'order_submission_date' => '2027-01-05', 'order_receipt_date' => '2027-02-05',
        ])->assertRedirect(route('modules.missions'));

        $project->refresh();
        $this->assertSame('active', $project->status);
        $this->assertTrue($project->is_active);
        $this->assertSame(0.5, $project->safety_stock_months);
        $this->assertSame('2026-12-25', $project->inventory_date->format('Y-m-d'));
        $this->assertSame('published', $version->fresh()->status);
        $this->assertDatabaseHas('project_supply_settings_history', ['project_id' => $project->id, 'order_period_months' => 3, 'inventory_date' => '2026-12-25']);
    }

    public function test_drafts_and_validation_rules(): void
    {
        // Programme national : bailleur facultatif ; « Enregistrer le brouillon » revient à Ma Coordination.
        $this->actingAs($this->coordination)->post(route('projects.wizard.store'), [
            'intent' => 'draft', 'type' => 'national_program', 'mission_id' => $this->mission->id,
            'implementing_partner' => 'Ministère de la Santé', 'code' => 'PNLT', 'name' => 'Tuberculose',
        ])->assertRedirect(route('modules.missions'));
        $project = Project::where('code', 'PNLT')->firstOrFail();
        $this->assertSame('PNLT', $project->moh_program_code);
        $this->assertSame('draft', $project->status);

        // Projet bailleur sans bailleur ; code déjà pris.
        $this->actingAs($this->coordination)->from(route('projects.wizard.create'))->post(route('projects.wizard.store'), [
            'type' => 'donor_project', 'mission_id' => $this->mission->id, 'implementing_partner' => 'ONG', 'code' => 'PNLT', 'name' => 'Doublon',
        ])->assertSessionHasErrors(['donor_id', 'code']);

        // Étape 4 sans paramètres : refusée, sauf en brouillon.
        $this->actingAs($this->coordination)->put(route('projects.wizard.supply.update', $project), [])
            ->assertSessionHasErrors(['order_period_months', 'delivery_lead_time_months', 'safety_stock_months']);
        $this->actingAs($this->coordination)->put(route('projects.wizard.supply.update', $project), ['safety_stock_months' => 3])
            ->assertSessionHasErrors('safety_stock_months');
        $this->actingAs($this->coordination)->put(route('projects.wizard.supply.update', $project), ['intent' => 'draft'])
            ->assertRedirect(route('modules.missions'));
        $this->assertSame('draft', $project->fresh()->status);
    }

    public function test_add_donor_from_step_two(): void
    {
        $this->actingAs($this->coordination)->postJson(route('projects.wizard.donors.store'), [
            'mission_id' => $this->mission->id, 'name' => 'Bailleur B', 'code' => 'BB',
        ])->assertCreated()->assertJsonPath('name', 'Bailleur B');
        $this->assertDatabaseHas('donors', ['organization_id' => $this->organization->id, 'code' => 'BB']);

        $this->actingAs($this->coordination)->postJson(route('projects.wizard.donors.store'), [
            'mission_id' => $this->mission->id, 'name' => 'Doublon', 'code' => 'BB',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_add_service_from_step_three_without_leaving_the_wizard(): void
    {
        $project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'P1', 'name' => 'Projet', 'status' => 'draft']);
        $this->actingAs($this->coordination)->get(route('projects.wizard.show', [$project, 'standard-list']))->assertOk()
            ->assertSee('data-open-dialog="service-dialog"', false)->assertDontSee('#ajouter-un-service', false)
            // Rattachement : chemin complet (deux catégories du même nom restent distinctes).
            ->assertSee('Soins de santé primaire › ', false);

        // Web : programme rattaché à « Soins de santé primaire », propre à l'organisation.
        $this->actingAs($this->coordination)->postJson(route('projects.wizard.services.store', $project), [
            'parent_id' => $this->node('SSP'), 'name' => 'Programme PEC Diabète', 'code' => 'DIAB',
        ])->assertCreated()->assertJsonPath('name', 'Programme PEC Diabète')->assertJsonPath('depth', 2);
        $this->assertDatabaseHas('catalog_references', ['organization_id' => $this->organization->id, 'reference_type' => 'care_level', 'code' => 'DIAB']);
        $this->actingAs($this->coordination)->postJson(route('projects.wizard.services.store', $project), ['name' => 'Doublon', 'code' => 'DIAB'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        // Mobile : nouveau niveau de soins, proposé ensuite dans les choix de l'étape 3.
        \Laravel\Sanctum\Sanctum::actingAs($this->coordination);
        $id = $this->postJson("/api/v1/projects/{$project->id}/wizard/services", ['name' => 'Soins tertiaires', 'code' => 'STER'])
            ->assertCreated()->assertJsonPath('service.depth', 1)->json('service.id');
        $this->assertContains($id, collect($this->getJson("/api/v1/projects/{$project->id}/wizard/standard-list")->assertOk()->json('options.care_levels'))->pluck('id'));

        // Coordination d'une autre mission : projet introuvable.
        $otherMission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => $this->mission->country_id, 'code' => 'DLA', 'name' => 'Coordination Douala']);
        \Laravel\Sanctum\Sanctum::actingAs($this->user('coordination_admin', 'mission', $otherMission->id));
        $this->postJson("/api/v1/projects/{$project->id}/wizard/services", ['name' => 'Intrus', 'code' => 'INTRUS'])->assertNotFound();
    }

    public function test_mobile_api_runs_the_same_four_steps(): void
    {
        $adults = $this->ref('target_population', 'ADULT', 'Adultes');
        $malaria = $this->ref('pathology', 'PALU', 'Paludisme simple');
        $act = $this->product('ACT', 'Artéméther / Luméfantrine', $this->node('SSP'), $malaria->id);
        \Laravel\Sanctum\Sanctum::actingAs($this->coordination);

        $this->getJson('/api/v1/projects/wizard/options')->assertOk()
            ->assertJsonPath('missions.0.name', 'Coordination Yaoundé')
            ->assertJsonPath('missions.0.country', 'Cameroun')
            ->assertJsonPath('types.national_program', 'Programme national')->assertJsonPath('can_add_service', true);
        $donor = $this->postJson('/api/v1/projects/wizard/donors', ['mission_id' => $this->mission->id, 'name' => 'Bailleur A', 'code' => 'BA'])
            ->assertCreated()->json('id');

        $id = $this->postJson('/api/v1/projects/wizard', [
            'type' => 'donor_project', 'mission_id' => $this->mission->id, 'implementing_partner' => 'ONG Santé',
            'donor_id' => $donor, 'code' => 'FH4', 'name' => 'Santé maternelle',
        ])->assertCreated()->assertJsonPath('project.status', 'draft')->assertJsonPath('resume_step', 'standard-list')->json('project.id');

        $state = ['care_level_ids' => [$this->node('SSP')], 'target_population_ids' => [$adults->id], 'pathology_ids' => [$malaria->id]];
        $this->getJson("/api/v1/projects/$id/wizard/standard-list?".http_build_query(['refresh' => 1, ...$state]))->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('products.0.code', 'ACT')->assertJsonPath('products.0.pathology', 'Paludisme simple');
        $this->putJson("/api/v1/projects/$id/wizard/standard-list", [...$state, 'retained' => [$act->id]])->assertOk()
            ->assertJsonPath('summary.retained', 1)->assertJsonPath('resume_step', 'supply');
        $this->putJson("/api/v1/projects/$id/wizard/supply", ['order_period_months' => 3, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 0.75])
            ->assertOk()->assertJsonPath('project.status', 'active')->assertJsonPath('project.safety_stock_months', 0.75);

        // Admin Projet : assistant refusé.
        $projectAdmin = $this->user('project_admin', 'project', $id);
        \Laravel\Sanctum\Sanctum::actingAs($projectAdmin);
        $this->getJson('/api/v1/projects/wizard/options')->assertForbidden();
        $this->putJson("/api/v1/projects/$id/wizard/supply", [])->assertForbidden();
    }

    public function test_wizard_is_reserved_to_the_coordination_of_the_project(): void
    {
        $project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'P1', 'name' => 'Projet', 'status' => 'draft']);
        $projectAdmin = $this->user('project_admin', 'project', $project->id);
        $this->actingAs($projectAdmin)->get(route('projects.wizard.create'))->assertForbidden();
        $this->actingAs($projectAdmin)->put(route('projects.wizard.supply.update', $project), [])->assertForbidden();

        // Coordination d'une autre mission : projet introuvable, mission refusée.
        $otherMission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => $this->mission->country_id, 'code' => 'DLA', 'name' => 'Coordination Douala']);
        $other = $this->user('coordination_admin', 'mission', $otherMission->id);
        $this->actingAs($other)->get(route('projects.wizard.show', [$project, 'identity']))->assertNotFound();
        $this->actingAs($other)->post(route('projects.wizard.store'), [
            'type' => 'national_program', 'mission_id' => $this->mission->id, 'implementing_partner' => 'ONG', 'code' => 'INTRUS', 'name' => 'Intrus',
        ])->assertSessionHasErrors('mission_id');
        $this->assertDatabaseMissing('projects', ['code' => 'INTRUS']);
    }

    private function user(string $role, string $scopeType, string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function ref(string $type, string $code, string $name): CatalogReference
    {
        return CatalogReference::create(['organization_id' => $this->organization->id, 'reference_type' => $type, 'code' => $code, 'name' => $name]);
    }

    private function node(string $code): string
    {
        return CatalogReference::whereNull('organization_id')->where('code', $code)->value('id');
    }

    private function product(string $code, string $name, string $careLevelId, ?string $pathologyId = null): Product
    {
        $product = Product::create(['organization_id' => $this->organization->id, 'code' => $code, 'name' => $name, 'product_type' => 'medicine']);
        ProductStandardMapping::create(['organization_id' => $this->organization->id, 'product_id' => $product->id, 'care_level_id' => $careLevelId, 'pathology_id' => $pathologyId]);

        return $product;
    }
}
