<?php

namespace Tests\Feature;

use App\Models\CatalogReference;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductStandardMapping;
use App\Models\Project;
use App\Models\Role;
use App\Models\StandardList;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** AM-113 — Liste standard générée à partir de la configuration médicale du projet. */
class ProjectStandardListFromConfigurationTest extends TestCase
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
        $this->project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $mission->id, 'code' => 'VIH', 'name' => 'Projet VIH']);
        $this->coordination = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $this->coordination->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $mission->id]);
        Sanctum::actingAs($this->coordination);
    }

    private function ref(string $type, string $code, string $name): CatalogReference
    {
        return CatalogReference::create(['organization_id' => $this->organization->id, 'reference_type' => $type, 'code' => $code, 'name' => $name]);
    }

    private function node(string $code): string
    {
        return CatalogReference::whereNull('organization_id')->where('code', $code)->value('id');
    }

    private function product(string $code, string $careLevelId, ?string $pathologyId = null): Product
    {
        $product = Product::create(['organization_id' => $this->organization->id, 'code' => $code, 'name' => $code, 'product_type' => 'medicine']);
        ProductStandardMapping::create(['organization_id' => $this->organization->id, 'product_id' => $product->id, 'care_level_id' => $careLevelId, 'pathology_id' => $pathologyId]);

        return $product;
    }

    public function test_generation_uses_the_project_configuration_and_the_care_level_hierarchy(): void
    {
        $adults = $this->ref('target_population', 'ADULT', 'Adultes');
        $hiv = $this->ref('pathology', 'HIV', 'Infection à VIH');
        $csi = $this->ref('facility_category', 'CSI', 'Centre de santé intégré');

        $this->product('ARV', $this->node('SSP-PEC-VIH'), $hiv->id);          // programme retenu
        $this->product('PARACETAMOL', $this->node('SSP'));                   // niveau parent : hérité
        $this->product('RUTF', $this->node('SSP-PEC-MALNUT'));              // autre programme : exclu
        $this->product('HOSPITAL_ONLY', $this->node('SSS'));                // autre niveau : exclu

        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", [
            'care_level_ids' => [$this->node('SSP-PEC-VIH')],
            'target_population_ids' => [$adults->id],
            'pathologies' => [['pathology_id' => $hiv->id, 'target_population_ids' => [$adults->id]]],
        ])->assertOk();

        // Aucun niveau, population ni pathologie saisis : la configuration du projet s'applique.
        $codes = collect($this->postJson("/api/v1/projects/{$this->project->id}/standard-list/generate", [
            'facility_category_id' => $csi->id,
        ])->assertOk()->json('products'))->pluck('code')->sort()->values()->all();

        $this->assertSame(['ARV', 'PARACETAMOL'], $codes);
    }

    public function test_selecting_a_whole_level_includes_its_programs(): void
    {
        $children = $this->ref('target_population', 'U5', 'Enfants < 5 ans');
        $sam = $this->ref('pathology', 'MAS', 'Malnutrition aiguë sévère');
        $csi = $this->ref('facility_category', 'CSI', 'Centre de santé intégré');
        $this->product('RUTF', $this->node('SSP-PEC-MALNUT'), $sam->id);

        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", [
            'care_level_ids' => [$this->node('SSP')],
            'target_population_ids' => [$children->id],
            'pathologies' => [['pathology_id' => $sam->id, 'target_population_ids' => [$children->id]]],
        ])->assertOk();

        $this->postJson("/api/v1/projects/{$this->project->id}/standard-list/generate", ['facility_category_id' => $csi->id])
            ->assertOk()->assertJsonPath('products.0.code', 'RUTF');
    }

    public function test_published_list_is_flagged_when_the_medical_configuration_changes(): void
    {
        $adults = $this->ref('target_population', 'ADULT', 'Adultes');
        $pregnant = $this->ref('target_population', 'PREG', 'Femmes enceintes');
        $hiv = $this->ref('pathology', 'HIV', 'Infection à VIH');
        $csi = $this->ref('facility_category', 'CSI', 'Centre de santé intégré');
        $arv = $this->product('ARV', $this->node('SSP-PEC-VIH'), $hiv->id);
        $config = fn (array $populations) => [
            'care_level_ids' => [$this->node('SSP-PEC-VIH')],
            'target_population_ids' => $populations,
            'pathologies' => [['pathology_id' => $hiv->id, 'target_population_ids' => [$adults->id]]],
        ];
        $url = "/api/v1/projects/{$this->project->id}/standard-list";

        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", $config([$adults->id]))->assertOk();
        $this->postJson($url, ['facility_category_id' => $csi->id, 'code' => 'VIH_STD', 'name' => 'Liste VIH', 'product_ids' => [$arv->id]])->assertCreated();
        $list = StandardList::where('scope_id', $this->project->id)->firstOrFail();
        $this->postJson("$url/{$list->id}/publish")->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('needs_regeneration', false);

        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", $config([$adults->id, $pregnant->id]))->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('needs_regeneration', true);
    }
}
