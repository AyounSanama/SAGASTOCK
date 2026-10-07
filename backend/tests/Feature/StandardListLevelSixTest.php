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
use App\Models\StandardListVersion;
use App\Models\User;
use App\Services\HealthFacilityManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Niveau 6 — Listes Standard (Coordination). */
class StandardListLevelSixTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private Project $project;

    private User $coordination;

    private User $projectAdmin;

    private string $ssp;

    private CatalogReference $adults;

    private CatalogReference $children;

    private CatalogReference $malaria;

    private CatalogReference $diarrhoea;

    private Product $act;

    private Product $diazepam;

    private HealthFacility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        $this->mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id, 'code' => 'YDE', 'name' => 'Coordination Yaoundé', 'is_active' => true]);
        $this->coordination = $this->user('coordination_admin', 'mission', $this->mission->id);
        $donor = Donor::create(['organization_id' => $this->organization->id, 'code' => 'BA', 'name' => 'Bailleur A', 'is_active' => true]);
        $this->adults = $this->reference('target_population', 'AD', 'Adultes');
        $this->children = $this->reference('target_population', 'U5', 'Enfants < 5 ans');
        $this->malaria = $this->reference('pathology', 'PALU', 'Paludisme simple');
        $this->diarrhoea = $this->reference('pathology', 'DIAR', 'Diarrhées');
        $this->ssp = CatalogReference::whereNull('organization_id')->where('code', 'SSP')->value('id');
        $this->act = $this->product('ACT', 'Artéméther', $this->ssp, $this->malaria->id, $this->adults->id);
        $this->diazepam = $this->product('DIAZ', 'Diazépam injectable', $this->ssp, $this->malaria->id, $this->adults->id);
        // Les diarrhées ne concernent que les enfants dans le catalogue.
        $this->product('SRO', 'Sels de réhydratation orale', $this->ssp, $this->diarrhoea->id, $this->children->id);

        $this->actingAs($this->coordination)->post(route('projects.wizard.store'), [
            'type' => 'donor_project', 'mission_id' => $this->mission->id, 'implementing_partner' => 'ONG Santé',
            'donor_id' => $donor->id, 'code' => 'GFFO5', 'name' => 'Appui aux soins', 'starts_on' => '2026-01-01', 'ends_on' => '2027-12-31',
        ]);
        $this->project = Project::where('code', 'GFFO5')->firstOrFail();
        $this->actingAs($this->coordination)->put(route('projects.wizard.standard-list.update', $this->project), [
            ...$this->state(), 'retained' => [$this->act->id, $this->diazepam->id],
        ]);
        $this->actingAs($this->coordination)->put(route('projects.wizard.supply.update', $this->project), [
            'order_period_months' => 3, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 1,
        ]);
        $this->projectAdmin = $this->user('project_admin', 'project', $this->project->id);
        $this->facility = HealthFacility::create([
            'organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'FOSA-001', 'name' => 'CSI de Nkolndongo',
            'facility_type' => 'health_center', 'care_level_id' => $this->ssp, 'validation_status' => HealthFacility::STATUS_VALIDATED, 'is_active' => true,
        ]);
        $this->facility->projects()->attach($this->project->id);
        $this->facility->targetPopulations()->attach($this->adults->id);
        $this->facility->pathologies()->attach($this->malaria->id);
    }

    public function test_programme_laboratoire_and_its_exams_are_proposed_as_activities(): void
    {
        $lab = CatalogReference::whereNull('organization_id')->where('reference_type', 'care_level')->where('code', 'LAB')->firstOrFail();
        $this->assertSame('Programme Laboratoire', $lab->name);
        $smear = $this->reference('laboratory_exam', 'GE', 'Goutte épaisse');
        $test = $this->product('TDR', 'Test de diagnostic rapide paludisme', $lab->id, null, null);
        ProductStandardMapping::where('product_id', $test->id)->update(['laboratory_exam_id' => $smear->id]);

        Sanctum::actingAs($this->coordination);
        $state = ['care_level_ids' => [$lab->id], 'target_population_ids' => [$this->adults->id], 'pathology_ids' => [$smear->id]];
        $response = $this->getJson("/api/v1/projects/{$this->project->id}/wizard/standard-list?".http_build_query(['refresh' => 1, ...$state]))->assertOk();
        $exam = collect($response->json('options.pathologies'))->firstWhere('id', $smear->id);
        $this->assertSame('laboratory_exam', $exam['type']);
        $this->assertTrue($exam['suggested']);
        $this->assertTrue($response->json('options.suggestions'));
        $this->assertSame(['TDR'], collect($response->json('products'))->pluck('code')->all());
        $this->assertTrue(collect($response->json('options.care_levels'))->contains('name', 'Programme Laboratoire'));

        // L'examen s'enregistre dans la configuration médicale comme une activité.
        $this->actingAs($this->coordination)->put(route('projects.wizard.standard-list.update', $this->project), [...$state, 'retained' => [$test->id]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('project_pathology_populations', ['project_id' => $this->project->id, 'pathology_id' => $smear->id]);
    }

    public function test_pathologies_are_proposed_by_care_level_and_population(): void
    {
        Sanctum::actingAs($this->coordination);
        $options = fn (array $populations) => collect($this->getJson("/api/v1/projects/{$this->project->id}/wizard/standard-list?".http_build_query([
            'refresh' => 1, 'care_level_ids' => [$this->ssp], 'target_population_ids' => $populations, 'pathology_ids' => [],
        ]))->assertOk()->json('options.pathologies'))->keyBy('name');

        $adults = $options([$this->adults->id]);
        $this->assertTrue($adults['Paludisme simple']['suggested']);
        $this->assertFalse($adults['Diarrhées']['suggested'], 'Diarrhées : produits réservés aux enfants');
        $children = $options([$this->children->id]);
        $this->assertTrue($children['Diarrhées']['suggested']);
        $this->assertFalse($children['Paludisme simple']['suggested']);

        // Web : les autres restent accessibles derrière « + N autres ».
        $this->actingAs($this->coordination)->get(route('projects.wizard.show', [$this->project, 'standard-list']).'?'.http_build_query([
            'refresh' => 1, 'care_level_ids' => [$this->ssp], 'target_population_ids' => [$this->adults->id],
        ]))->assertOk()->assertSee('data-other-activity', false)->assertSee('+ 1 autres')
            ->assertSee('Importer depuis Excel')->assertSee('Créer et retenir');
    }

    public function test_wizard_creates_a_new_product_with_the_organisation_codification(): void
    {
        $this->actingAs($this->coordination)->put(route('projects.wizard.standard-list.products.store', $this->project), [
            ...$this->state(), 'listed' => [$this->act->id, $this->diazepam->id], 'retained' => [$this->act->id, $this->diazepam->id],
            'new_product' => ['code' => 'ONG-AMOX-500', 'name' => 'Amoxicilline 500 mg', 'packaging' => 'Gélule, boîte de 1000', 'barcode' => '6001234567890',
                'care_level_id' => $this->ssp, 'activity_id' => $this->malaria->id],
        ])->assertRedirect(route('projects.wizard.show', [$this->project, 'standard-list']))->assertSessionHasNoErrors();

        $product = Product::where('code', 'ONG-AMOX-500')->firstOrFail();
        $this->assertSame('Gélule, boîte de 1000', $product->packaging);
        $this->assertDatabaseHas('product_codes', ['product_id' => $product->id, 'code_type' => 'barcode', 'value' => '6001234567890']);
        $this->assertDatabaseHas('product_standard_mappings', ['product_id' => $product->id, 'care_level_id' => $this->ssp, 'pathology_id' => $this->malaria->id]);
        $draft = StandardListVersion::where('status', 'draft')->latest('version_number')->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->act->id, $this->diazepam->id, $product->id], $draft->products()->pluck('products.id')->all());

        // Code déjà utilisé : refusé.
        $this->actingAs($this->coordination)->from(route('projects.wizard.show', [$this->project, 'standard-list']))
            ->put(route('projects.wizard.standard-list.products.store', $this->project), [...$this->state(), 'new_product' => ['code' => 'ONG-AMOX-500', 'name' => 'Doublon']])
            ->assertSessionHasErrors('code');
    }

    public function test_excel_import_adds_products_mappings_and_reports_errors(): void
    {
        $existing = Product::create(['organization_id' => $this->organization->id, 'code' => 'OTHER', 'name' => 'Autre', 'product_type' => 'medicine']);
        $existing->codes()->create(['code_type' => 'barcode', 'value' => '111', 'is_primary' => true]);
        $file = $this->excel([
            ['Code', 'Désignation', 'Conditionnement', 'Niveau de soins', 'Population', 'Pathologie / activité', 'Catégorie FOSA', 'Code-barres'],
            ['PARA', 'Paracétamol 500 mg', 'Comprimé, boîte de 1000', 'Soins de santé primaire', 'Adultes', 'Fièvre / douleur', '', '222'],
            ['ACT', 'Artéméther / Luméfantrine 20/120 mg', 'Plaquette de 24', 'SSP', 'adultes', 'Paludisme simple', '', ''],
            ['ZINC', 'Zinc 20 mg', '', 'Niveau inconnu', '', '', '', ''],
            ['FER', 'Fer / acide folique', '', '', '', '', '', '111'],
            ['', '', '', '', '', '', '', ''],
        ]);

        $this->actingAs($this->coordination)->post(route('coordination.standard-list.import', $this->project), ['file' => $file])
            ->assertRedirect()->assertSessionHas('status', fn ($status) => str_contains($status, '2 produit(s) importé(s), dont 1 nouveau(x)'))
            ->assertSessionHas('import_errors', fn ($errors) => count($errors) === 2
                && str_contains($errors[0], 'Ligne 4') && str_contains($errors[0], 'Niveau inconnu')
                && str_contains($errors[1], 'Ligne 5') && str_contains($errors[1], '111'));

        $paracetamol = Product::where('code', 'PARA')->firstOrFail();
        $this->assertSame('Comprimé, boîte de 1000', $paracetamol->packaging);
        $fever = CatalogReference::where('organization_id', $this->organization->id)->where('name', 'Fièvre / douleur')->firstOrFail();
        $this->assertSame('pathology', $fever->reference_type, 'Pathologie inconnue ajoutée au référentiel de l’organisation');
        $this->assertDatabaseHas('product_standard_mappings', ['product_id' => $paracetamol->id, 'care_level_id' => $this->ssp, 'target_population_id' => $this->adults->id, 'pathology_id' => $fever->id]);
        $this->assertSame('Artéméther / Luméfantrine 20/120 mg', $this->act->fresh()->name, 'Produit existant mis à jour par son code');
        $this->assertDatabaseMissing('products', ['code' => 'ZINC']);

        // Ajoutés à une nouvelle version validée ; l'ancienne est remplacée.
        $published = StandardListVersion::where('status', 'published')->firstOrFail();
        $this->assertSame(2, $published->version_number);
        $this->assertTrue($published->products()->whereKey($paracetamol->id)->exists());
        $this->assertSame(1, StandardListVersion::where('status', 'superseded')->count());

        $this->actingAs($this->coordination)->get(route('coordination.standard-list.template'))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_coordination_unchecks_articles_per_facility(): void
    {
        $management = app(HealthFacilityManagementService::class);
        $this->assertEqualsCanonicalizing([$this->act->id, $this->diazepam->id], $management->standardList($this->facility)->pluck('id')->all());

        $this->actingAs($this->coordination)->get('/standard-lists')->assertRedirect(route('coordination.standard-list.show'));
        $this->actingAs($this->coordination)->get(route('coordination.standard-list.show', ['project' => $this->project->id, 'facility' => $this->facility->id]))
            ->assertOk()->assertSee('Retenu pour la FOSA')->assertSee('Enregistrer pour cette FOSA')->assertSee('Diazépam injectable');

        // Le diazépam injectable n'est pas envoyé dans ce centre de santé.
        $this->actingAs($this->coordination)->put(route('coordination.standard-list.facility.update', [$this->project, $this->facility]), ['retained' => [$this->act->id]])
            ->assertRedirect()->assertSessionHas('status', fn ($status) => str_contains($status, '1 article(s) décoché(s)'));
        $this->assertSame([$this->act->id], $management->standardList($this->facility)->pluck('id')->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'health_facility.standard_list.updated']);

        // L'Admin Projet voit « Non » pour cette FOSA, sans pouvoir modifier.
        $this->actingAs($this->projectAdmin)->get(route('project-admin.standard-list', ['facility' => $this->facility->id]))->assertOk()
            ->assertSeeInOrder(['Diazépam injectable', 'Non']);
        $this->actingAs($this->projectAdmin)->put(route('coordination.standard-list.facility.update', [$this->project, $this->facility]), ['retained' => []])
            ->assertForbidden();

        // Réintégration (API mobile).
        Sanctum::actingAs($this->coordination);
        $this->getJson("/api/v1/coordination/standard-list?project={$this->project->id}&facility={$this->facility->id}")->assertOk()
            ->assertJsonPath('can_manage', true)
            ->assertJsonFragment(['code' => 'DIAZ', 'retained' => false]);
        $this->putJson("/api/v1/coordination/standard-list/{$this->project->id}/facilities/{$this->facility->id}", ['retained' => [$this->act->id, $this->diazepam->id]])
            ->assertOk()->assertJson(['excluded' => 0, 'restored' => 1]);
        $this->assertCount(2, $management->standardList($this->facility));
    }

    public function test_barcode_is_linked_to_a_single_product(): void
    {
        $this->actingAs($this->coordination)->put(route('coordination.standard-list.barcode.update', [$this->project, $this->act]), ['barcode' => '6001 2345 678'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_codes', ['product_id' => $this->act->id, 'code_type' => 'barcode', 'value' => '60012345678']);
        $this->actingAs($this->coordination)->put(route('coordination.standard-list.barcode.update', [$this->project, $this->diazepam]), ['barcode' => '60012345678'])
            ->assertSessionHasErrors('barcode');

        Sanctum::actingAs($this->coordination);
        $this->putJson("/api/v1/coordination/standard-list/{$this->project->id}/products/{$this->act->id}/barcode", ['barcode' => ''])
            ->assertOk()->assertJsonPath('barcode', null);
        $this->assertDatabaseMissing('product_codes', ['product_id' => $this->act->id, 'code_type' => 'barcode']);
    }

    public function test_only_the_coordination_of_the_project_manages_the_list(): void
    {
        $other = Mission::create(['organization_id' => $this->organization->id, 'country_id' => $this->mission->country_id, 'code' => 'DLA', 'name' => 'Coordination Douala', 'is_active' => true]);
        $outsider = $this->user('coordination_admin', 'mission', $other->id);
        $this->actingAs($outsider)->put(route('coordination.standard-list.facility.update', [$this->project, $this->facility]), ['retained' => []])->assertNotFound();
        $this->actingAs($outsider)->post(route('coordination.standard-list.products.store', $this->project), ['code' => 'X', 'name' => 'X'])->assertNotFound();
        $this->actingAs($this->projectAdmin)->post(route('coordination.standard-list.products.store', $this->project), ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->assertDatabaseMissing('products', ['code' => 'X']);
        $this->assertDatabaseMissing('health_facility_product_exclusions', ['health_facility_id' => $this->facility->id]);
    }

    private function state(): array
    {
        return ['care_level_ids' => [$this->ssp], 'target_population_ids' => [$this->adults->id], 'pathology_ids' => [$this->malaria->id]];
    }

    private function reference(string $type, string $code, string $name): CatalogReference
    {
        return CatalogReference::create(['organization_id' => $this->organization->id, 'reference_type' => $type, 'code' => $code, 'name' => $name, 'is_active' => true]);
    }

    private function user(string $role, string $scopeType, string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function product(string $code, string $name, string $careLevelId, ?string $pathologyId, ?string $populationId): Product
    {
        $product = Product::create(['organization_id' => $this->organization->id, 'code' => $code, 'name' => $name, 'product_type' => 'medicine']);
        ProductStandardMapping::create(['organization_id' => $this->organization->id, 'product_id' => $product->id, 'care_level_id' => $careLevelId,
            'pathology_id' => $pathologyId, 'target_population_id' => $populationId]);

        return $product;
    }

    private function excel(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'liste').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'liste.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
