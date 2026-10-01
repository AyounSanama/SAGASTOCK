<?php

namespace Tests\Feature;

use App\Models\CatalogReference;
use App\Models\Country;
use App\Models\Donor;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Configuration Mission, rubrique « Créer un projet » (captures validées). */
class ConfigurationMissionProjectTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->mission = Mission::create([
            'organization_id' => $this->organization->id,
            'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => 'MAROUA', 'name' => 'Coordination Maroua', 'is_active' => true,
        ]);
        $this->coordination = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $this->coordination->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $this->mission->id]);
    }

    private function payload(array $extra = []): array
    {
        return [
            'mission_id' => $this->mission->id, 'code' => 'GFFO5-VIH', 'name' => 'Projet VIH', 'status' => 'active',
            'order_period_months' => 12, 'delivery_lead_time_months' => 2, 'safety_stock_months' => 1,
            ...$extra,
        ];
    }

    public function test_default_target_populations_are_available_to_every_organization(): void
    {
        $names = CatalogReference::whereNull('organization_id')->where('reference_type', 'target_population')->pluck('name')->sort()->values()->all();

        $this->assertSame(['Adultes', 'Enfants < 5 ans', 'Femmes enceintes'], $names);
    }

    public function test_a_project_is_attached_to_a_single_donor(): void
    {
        $donors = collect(['GFFO5', 'FH4'])->map(fn ($code) => Donor::create(['organization_id' => $this->organization->id, 'code' => $code, 'name' => "Bailleur $code"]));
        Sanctum::actingAs($this->coordination);
        $url = "/api/v1/organizations/{$this->organization->id}/projects";

        $this->postJson($url, $this->payload(['donor_ids' => $donors->pluck('id')->all()]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.donor_ids.0', 'Un projet est rattaché à un seul bailleur : créez un projet par code bailleur.');

        $this->postJson($url, $this->payload(['donor_ids' => [$donors[0]->id], 'donor_reference_code' => 'GFFO5']))->assertCreated();
    }

    public function test_order_period_offers_one_to_twelve_months_with_the_validated_labels(): void
    {
        $page = $this->actingAs($this->coordination)->get(route('modules.projects'))->assertOk();

        $page->assertSee('Périodicité de commande')->assertSee('Stock de sécurité')->assertDontSee('Périodicité des commandes');
        foreach ([5, 7, 11] as $month) {
            $page->assertSee("<option value=\"$month\"", false);
        }
    }

    public function test_only_the_coordination_changes_the_standard_list_table(): void
    {
        $project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'VIH', 'name' => 'Projet VIH']);
        $projectAdmin = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $projectAdmin->roles()->attach(Role::where('code', 'project_admin')->firstOrFail(), ['scope_type' => 'project', 'scope_id' => $project->id]);

        Sanctum::actingAs($projectAdmin);
        $this->getJson("/api/v1/projects/{$project->id}/standard-list")->assertOk();
        $this->postJson("/api/v1/projects/{$project->id}/standard-list", ['code' => 'X', 'name' => 'X', 'product_ids' => []])->assertForbidden();
        $this->actingAs($projectAdmin)->post(route('projects.standard-list.save', $project), ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }
}
