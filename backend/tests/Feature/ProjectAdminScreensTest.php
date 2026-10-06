<?php

namespace Tests\Feature;

use App\Models\CatalogReference;
use App\Models\Country;
use App\Models\Donor;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductStandardMapping;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Niveau 5 — Écrans Web de l'Admin Projet (maquettes AdminProjet 01 à 04). */
class ProjectAdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Project $project;

    private User $admin;

    private array $fosa;

    private Product $act;

    private Product $paracetamol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        $mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id, 'code' => 'YDE', 'name' => 'Coordination Yaoundé', 'is_active' => true]);
        $coordination = $this->user('coordination_admin', 'mission', $mission->id);
        $donor = Donor::create(['organization_id' => $this->organization->id, 'code' => 'BA', 'name' => 'Bailleur A', 'is_active' => true]);
        $adults = CatalogReference::create(['organization_id' => $this->organization->id, 'reference_type' => 'target_population', 'code' => 'AD', 'name' => 'Adultes']);
        $malaria = CatalogReference::create(['organization_id' => $this->organization->id, 'reference_type' => 'pathology', 'code' => 'PALU', 'name' => 'Paludisme simple']);
        $ssp = CatalogReference::whereNull('organization_id')->where('code', 'SSP')->value('id');
        $this->act = $this->product('ACT', 'Artéméther', $ssp, $malaria->id);
        $this->paracetamol = $this->product('PARA', 'Paracétamol', $ssp);

        // Projet actif créé par la Coordination avec l'assistant (Paracétamol non retenu).
        $this->actingAs($coordination)->post(route('projects.wizard.store'), [
            'type' => 'donor_project', 'mission_id' => $mission->id, 'implementing_partner' => 'ONG Santé',
            'donor_id' => $donor->id, 'code' => 'GFFO5', 'name' => 'Appui aux soins', 'starts_on' => '2026-01-01', 'ends_on' => '2027-12-31',
        ]);
        $this->project = Project::where('code', 'GFFO5')->firstOrFail();
        $state = ['care_level_ids' => [$ssp], 'target_population_ids' => [$adults->id], 'pathology_ids' => [$malaria->id]];
        $this->actingAs($coordination)->put(route('projects.wizard.standard-list.update', $this->project), [...$state, 'retained' => [$this->act->id]]);
        $this->actingAs($coordination)->put(route('projects.wizard.supply.update', $this->project), [
            'order_period_months' => 3, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 0.5, 'inventory_date' => '2026-12-25',
        ]);
        $this->admin = $this->user('project_admin', 'project', $this->project->id);
        $this->fosa = [
            'name' => 'CSI de Nkolndongo', 'code' => 'FOSA-001', 'care_level_id' => $ssp,
            'facility_category_id' => CatalogReference::where('reference_type', 'facility_category')->where('code', 'CAT-CSI')->value('id'),
            'target_population_ids' => [$adults->id], 'pathology_ids' => [$malaria->id],
            'order_period_months' => 3, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 1,
        ];
    }

    public function test_dashboard_shows_project_information_and_indicators(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('Vue d’ensemble du projet GFFO5')
            ->assertSee('FOSA actives')->assertSee('Comptes utilisateurs FOSA')->assertSee('Produits en Liste Standard')
            ->assertSee('Informations du projet')->assertSee('Bailleur A · GFFO5')->assertSee('01/01/2026 – 31/12/2027')
            ->assertSee('Entrepôt central → Pharmacie du projet')->assertSee('0,5 mois')->assertSee('25/12/2026')
            ->assertSee('Ajouter une FOSA');
    }

    public function test_declare_configure_and_list_a_facility(): void
    {
        // Déclaration (maquette 03) : préremplie depuis le projet, « en attente ».
        $this->actingAs($this->admin)->get(route('project-admin.facilities.create'))->assertOk()
            ->assertSee('Configuration de la FOSA')->assertSee('Appui aux soins')->assertSee('readonly', false)
            ->assertSee('Seule la Coordination peut ajouter ou retirer');
        $this->actingAs($this->admin)->getJson(route('project-admin.facilities.preview').'?'.http_build_query([
            'care_level_id' => $this->fosa['care_level_id'], 'target_population_ids' => $this->fosa['target_population_ids'], 'pathology_ids' => $this->fosa['pathology_ids'],
        ]))->assertOk()->assertJsonPath('count', 1);
        $this->actingAs($this->admin)->post(route('project-admin.facilities.store'), $this->fosa)
            ->assertRedirect(route('modules.health-facilities'))->assertSessionHas('status');
        $facility = HealthFacility::where('code', 'FOSA-001')->firstOrFail();
        $this->assertSame(HealthFacility::STATUS_PENDING, $facility->validation_status);
        $this->assertSame('2026-12-25', $facility->inventory_date?->format('Y-m-d'), 'Date d’inventaire reprise du projet');

        // Liste « Projet & FOSA » (maquette 02) : filtres et statut.
        $this->actingAs($this->admin)->get('/health-facilities')->assertOk()
            ->assertSee('Projet GFFO5 — Formations sanitaires')->assertSee('FOSA (1)')
            ->assertSee('CSI de Nkolndongo')->assertSee('Centre de santé intégré')->assertSee('En attente')->assertSee('1 sur 1 FOSA');
        $this->actingAs($this->admin)->get('/health-facilities?status=active')->assertOk()->assertSee('Aucune FOSA ne correspond');

        // Modification (validée puis désactivée).
        $facility->update(['validation_status' => HealthFacility::STATUS_VALIDATED]);
        $this->actingAs($this->admin)->get(route('project-admin.facilities.edit', $facility))->assertOk()
            ->assertSee('FOSA active')->assertSee('Aperçu de la liste');
        $this->actingAs($this->admin)->put(route('project-admin.facilities.update', $facility), [...$this->fosa, 'name' => 'CSI Nkolndongo', 'is_active' => 0])
            ->assertRedirect(route('project-admin.facilities.edit', $facility));
        $this->assertFalse($facility->fresh()->is_active);
        $this->assertSame('CSI Nkolndongo', $facility->fresh()->name);
        $this->actingAs($this->admin)->get('/health-facilities')->assertSee('Inactive');

        // Onglet « Paramètres d'approvisionnement ».
        $this->actingAs($this->admin)->get(route('project-admin.supply'))->assertOk()
            ->assertSee('Entrepôt central → Pharmacie du projet')->assertSee('CSI Nkolndongo');
    }

    public function test_standard_list_shows_the_validated_list_and_exports(): void
    {
        $this->actingAs($this->admin)->get('/standard-lists')->assertRedirect(route('project-admin.standard-list'));
        $this->actingAs($this->admin)->get(route('project-admin.standard-list'))->assertOk()
            ->assertSee('ONG Santé · Bailleur A GFFO5')->assertSee('Consultation uniquement')
            ->assertSee('Artéméther')->assertSee('Paracétamol')->assertSee('Paludisme simple')
            ->assertSee('<span class="pa-badge success">Oui</span>', false)->assertSee('<span class="pa-badge neutral">Non</span>', false);

        $export = $this->actingAs($this->admin)->get(route('project-admin.standard-list.export'));
        $export->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_mobile_api_mirrors_the_web_screens(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/project-admin/dashboard')->assertOk()
            ->assertJsonPath('project.code', 'GFFO5')->assertJsonPath('project.donor', 'Bailleur A')
            ->assertJsonPath('project.safety_stock_months', 0.5)->assertJsonPath('stats.standard_list_products', 1);
        $this->getJson('/api/v1/project-admin/facilities/options')->assertOk()
            ->assertJsonPath('supply_defaults.inventory_date', '2026-12-25')->assertJsonCount(1, 'target_populations');

        $id = $this->postJson('/api/v1/project-admin/facilities', $this->fosa)->assertCreated()
            ->assertJsonPath('facility.validation_status', 'pending')->assertJsonPath('facility.standard_list_count', 1)->json('facility.id');
        $this->putJson("/api/v1/project-admin/facilities/$id", [...$this->fosa, 'name' => 'CSI renommé'])->assertOk()
            ->assertJsonPath('facility.name', 'CSI renommé');
        $this->getJson('/api/v1/project-admin/facilities')->assertOk()
            ->assertJsonPath('facilities.0.status.label', 'En attente')->assertJsonPath('facilities.0.populations', 'Adultes');

        $this->getJson('/api/v1/project-admin/standard-list')->assertOk()
            ->assertJsonCount(2, 'products')->assertJsonPath('pathologies.0', 'Paludisme simple');
        $this->getJson("/api/v1/project-admin/standard-list?facility=$id")->assertOk()
            ->assertJsonPath('facility_id', $id)->assertJsonCount(1, 'products');
    }

    public function test_coordination_applies_project_supply_to_all_facilities(): void
    {
        $this->actingAs($this->admin)->post(route('project-admin.facilities.store'), [...$this->fosa, 'order_period_months' => 6, 'safety_stock_months' => 2]);
        $facility = HealthFacility::where('code', 'FOSA-001')->firstOrFail();
        $coordination = User::whereHas('roles', fn ($roles) => $roles->where('code', 'coordination_admin'))->firstOrFail();

        $this->actingAs($coordination)->get(route('projects.wizard.show', [$this->project, 'supply']))->assertOk()
            ->assertSee('Appliquer à toutes les FOSA');
        $this->actingAs($coordination)->post(route('projects.supply.apply', $this->project), ['confirm' => 1])
            ->assertRedirect()->assertSessionHas('status');
        $facility->refresh();
        $this->assertSame(3, $facility->order_period_months);
        $this->assertSame(0.5, $facility->safety_stock_months);
        // L'Admin Projet ne peut pas l'appliquer.
        $this->actingAs($this->admin)->post(route('projects.supply.apply', $this->project), ['confirm' => 1])->assertForbidden();
    }

    public function test_another_project_facility_is_not_reachable(): void
    {
        $other = HealthFacility::create(['organization_id' => $this->organization->id, 'code' => 'AUTRE', 'name' => 'FOSA voisine', 'facility_type' => 'health_center', 'validation_status' => 'validated']);
        $this->actingAs($this->admin)->get(route('project-admin.facilities.edit', $other))->assertNotFound();
        $this->actingAs($this->admin)->put(route('project-admin.facilities.update', $other), $this->fosa)->assertNotFound();
        $this->actingAs($this->admin)->get(route('project-admin.standard-list', ['facility' => $other->id]))->assertNotFound();
    }

    private function user(string $role, string $scopeType, string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function product(string $code, string $name, string $careLevelId, ?string $pathologyId = null): Product
    {
        $product = Product::create(['organization_id' => $this->organization->id, 'code' => $code, 'name' => $name, 'product_type' => 'medicine']);
        ProductStandardMapping::create(['organization_id' => $this->organization->id, 'product_id' => $product->id, 'care_level_id' => $careLevelId, 'pathology_id' => $pathologyId]);

        return $product;
    }
}
