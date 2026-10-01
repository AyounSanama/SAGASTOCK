<?php

namespace Tests\Feature;

use App\Models\CatalogReference;
use App\Models\Country;
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
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Lot c1 (AM-162) — Fiche FOSA complète, validation et comptes FOSA. */
class HealthFacilityConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private Project $project;

    private User $coordination;

    private User $projectAdmin;

    private CatalogReference $adults;

    private CatalogReference $pregnant;

    private CatalogReference $malaria;

    private string $level;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id, 'code' => 'MAROUA', 'name' => 'Coordination Maroua', 'is_active' => true]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'GFF05', 'name' => 'Projet SSP',
            'order_period_months' => 3, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 1,
        ]);
        $this->coordination = $this->user('coordination_admin', 'mission', $this->mission->id);
        $this->projectAdmin = $this->user('project_admin', 'project', $this->project->id);

        $this->adults = CatalogReference::whereNull('organization_id')->where('code', 'POP-ADULT')->firstOrFail();
        $this->pregnant = CatalogReference::whereNull('organization_id')->where('code', 'POP-PW')->firstOrFail();
        $this->malaria = CatalogReference::create(['organization_id' => $this->organization->id, 'reference_type' => 'pathology', 'code' => 'PALU', 'name' => 'Paludisme simple']);
        $this->level = CatalogReference::whereNull('organization_id')->where('code', 'SSP')->value('id');

        Sanctum::actingAs($this->coordination);
        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", [
            'care_level_ids' => [$this->level],
            'target_population_ids' => [$this->adults->id, $this->pregnant->id],
            'pathologies' => [['pathology_id' => $this->malaria->id, 'target_population_ids' => [$this->adults->id, $this->pregnant->id]]],
        ])->assertOk();
    }

    private function user(string $role, string $scopeType, string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function category(string $code = 'CAT-CSI'): string
    {
        return CatalogReference::whereNull('organization_id')->where('code', $code)->value('id');
    }

    private function payload(array $extra = []): array
    {
        return [
            'code' => 'CSI-EKOUM', 'name' => 'CSI d’Ekoumdoum',
            'care_level_id' => $this->level, 'facility_category_id' => $this->category(),
            'target_population_ids' => [$this->adults->id], 'pathology_ids' => [$this->malaria->id],
            ...$extra,
        ];
    }

    private function declare(array $extra = []): HealthFacility
    {
        Sanctum::actingAs($this->projectAdmin);
        $id = $this->postJson("/api/v1/organizations/{$this->organization->id}/facilities", $this->payload($extra))->assertCreated()->json('facility.id');

        return HealthFacility::findOrFail($id);
    }

    public function test_project_admin_declares_a_pending_facility_prefilled_from_the_project(): void
    {
        $facility = $this->declare();

        $this->assertSame(HealthFacility::STATUS_PENDING, $facility->validation_status);
        $this->assertSame($this->projectAdmin->id, $facility->declared_by);
        $this->assertSame([$this->project->id], $facility->projects()->pluck('projects.id')->all());
        $this->assertSame('health_center', $facility->facility_type);
        $this->assertSame([$this->adults->id], $facility->targetPopulations()->pluck('catalog_references.id')->all());
        // DEC-08 : préremplissage depuis le projet, avec historique.
        $this->assertSame([3, 1, 1.0], [$facility->order_period_months, $facility->delivery_lead_time_months, $facility->safety_stock_months]);
        $this->assertDatabaseHas('health_facility_supply_settings_history', ['health_facility_id' => $facility->id, 'source' => 'creation']);
        // DEC-05 : site principal créé, invisible.
        $this->assertDatabaseHas('sites', ['health_facility_id' => $facility->id, 'is_primary' => true]);
    }

    public function test_facility_choices_are_limited_to_the_validated_project_configuration(): void
    {
        Sanctum::actingAs($this->projectAdmin);
        $url = "/api/v1/organizations/{$this->organization->id}/facilities";

        $this->postJson($url, ['code' => 'X', 'name' => 'X'])->assertUnprocessable()
            ->assertJsonValidationErrors(['care_level_id', 'facility_category_id', 'target_population_ids', 'pathology_ids']);
        $children = CatalogReference::whereNull('organization_id')->where('code', 'POP-U5')->value('id');
        $this->postJson($url, $this->payload(['target_population_ids' => [$children]]))->assertUnprocessable()->assertJsonValidationErrors('target_population_ids');
        $this->postJson($url, $this->payload(['care_level_id' => CatalogReference::whereNull('organization_id')->where('code', 'SSS')->value('id')]))
            ->assertUnprocessable()->assertJsonValidationErrors('care_level_id');
        $this->postJson($url, $this->payload(['safety_stock_months' => 3]))->assertUnprocessable()
            ->assertJsonPath('errors.safety_stock_months.0', 'Le stock de sécurité doit être de 0,25 ; 0,5 ; 0,75 ; 1 ; 1,5 ou 2 mois.');
        $this->postJson($url, $this->payload(['code' => 'CSI-OK', 'safety_stock_months' => 0.25, 'order_period_months' => 12]))->assertCreated();

        $options = $this->getJson("$url/options")->assertOk();
        $this->assertEqualsCanonicalizing([$this->adults->id, $this->pregnant->id], collect($options->json('target_populations'))->pluck('id')->all());
        $this->assertCount(5, $options->json('facility_categories'));
        $this->assertContains($this->level, collect($options->json('care_levels'))->pluck('id')->all());
    }

    public function test_facility_accounts_are_refused_until_the_coordination_validates_the_facility(): void
    {
        $facility = $this->declare();
        $account = fn (string $role, string $email) => [
            'name' => 'Compte FOSA', 'email' => $email,
            'role_id' => Role::where('code', $role)->value('id'), 'scope_type' => 'facility', 'scope_id' => $facility->id,
        ];

        $this->postJson('/api/v1/users', $account('site_user', 'u1@example.test'))->assertUnprocessable()
            ->assertJsonPath('message', 'Les comptes de cette FOSA ne pourront être créés qu’après sa validation par la Coordination.');

        $facility->update(['validation_status' => HealthFacility::STATUS_VALIDATED, 'validated_at' => now()]);
        $this->postJson('/api/v1/users', $account('site_admin', 'admin@example.test'))->assertCreated();
        $this->postJson('/api/v1/users', $account('site_user', 'user@example.test'))->assertCreated();
        $primary = $facility->sites()->where('is_primary', true)->value('id');
        $this->assertDatabaseHas('role_user', ['user_id' => User::where('email', 'user@example.test')->value('id'), 'scope_type' => 'site', 'scope_id' => $primary]);
    }

    public function test_project_changes_never_overwrite_facilities_without_the_explicit_action(): void
    {
        $facility = $this->declare();
        $this->project->update(['order_period_months' => 6]);
        $this->assertSame(3, $facility->fresh()->order_period_months);

        Sanctum::actingAs($this->coordination);
        $url = "/api/v1/projects/{$this->project->id}/supply-settings/apply-to-facilities";
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->postJson($url, ['confirm' => true])->assertOk()->assertJsonPath('facilities_updated', 1);
        $this->assertSame(6, $facility->fresh()->order_period_months);
        $this->assertDatabaseHas('health_facility_supply_settings_history', ['health_facility_id' => $facility->id, 'source' => 'project_apply']);

        Sanctum::actingAs($this->projectAdmin);
        $this->postJson($url, ['confirm' => true])->assertForbidden();
    }

    public function test_refused_facility_returns_to_pending_once_corrected(): void
    {
        $facility = $this->declare();
        $facility->update(['validation_status' => HealthFacility::STATUS_REFUSED, 'refusal_reason' => 'Catégorie incorrecte']);

        $this->putJson("/api/v1/organizations/{$this->organization->id}/facilities/{$facility->id}", $this->payload(['facility_category_id' => $this->category('CAT-CMA')]))->assertOk();
        $this->assertSame(HealthFacility::STATUS_PENDING, $facility->fresh()->validation_status);
    }

    public function test_facility_standard_list_follows_its_own_configuration(): void
    {
        $match = Product::create(['organization_id' => $this->organization->id, 'code' => 'ACT', 'name' => 'Artéméther', 'product_type' => 'medicine']);
        ProductStandardMapping::create(['organization_id' => $this->organization->id, 'product_id' => $match->id, 'care_level_id' => $this->level, 'pathology_id' => $this->malaria->id, 'target_population_id' => $this->adults->id]);
        $other = Product::create(['organization_id' => $this->organization->id, 'code' => 'FER', 'name' => 'Fer', 'product_type' => 'medicine']);
        ProductStandardMapping::create(['organization_id' => $this->organization->id, 'product_id' => $other->id, 'care_level_id' => $this->level, 'target_population_id' => $this->pregnant->id]);

        $facility = $this->declare();
        $this->getJson("/api/v1/organizations/{$this->organization->id}/facilities/{$facility->id}/standard-list")->assertOk()
            ->assertJsonPath('count', 1)->assertJsonPath('products.0.code', 'ACT');
    }

    public function test_national_program_is_a_project_type_with_an_optional_donor(): void
    {
        Sanctum::actingAs($this->coordination);
        $this->postJson("/api/v1/organizations/{$this->organization->id}/projects", [
            'mission_id' => $this->mission->id, 'code' => 'PNLT', 'name' => 'Programme tuberculose', 'type' => 'national_program', 'status' => 'draft',
        ])->assertCreated()->assertJsonPath('project.type', 'national_program')->assertJsonPath('project.type_label', 'Programme national');
    }

    public function test_existing_facilities_created_outside_the_v1_declaration_remain_validated(): void
    {
        $facility = HealthFacility::create(['organization_id' => $this->organization->id, 'code' => 'OLD', 'name' => 'Ancienne FOSA', 'facility_type' => 'clinic']);
        $this->assertSame(HealthFacility::STATUS_VALIDATED, $facility->fresh()->validation_status);
        $this->assertSame(1, DB::table('health_facilities')->where('validation_status', 'validated')->count());
    }
}
