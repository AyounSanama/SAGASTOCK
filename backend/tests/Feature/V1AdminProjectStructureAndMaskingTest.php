<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\ApplicationNavigationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Lot b (AM-161) : structure de l'Admin Projet et masquage des fonctions EN TROP
 * de l'audit `07` (menu, routes et API refusés, code conservé).
 */
class V1AdminProjectStructureAndMaskingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private Project $project;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id, 'code' => 'MAROUA', 'name' => 'Coordination Maroua']);
        $this->project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'VIH', 'name' => 'Projet VIH']);
        $facility = HealthFacility::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'CSI-1', 'name' => 'CSI Dogba', 'facility_type' => 'health_center']);
        $facility->projects()->attach($this->project);
        $this->site = Site::create(['organization_id' => $this->organization->id, 'health_facility_id' => $facility->id, 'code' => 'S-1', 'name' => 'Pharmacie', 'site_type' => 'dispensing']);
    }

    private function actor(string $role, string $scopeType, string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false, 'name' => 'Awa Ndiaye']);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    public function test_project_admin_menu_has_four_entries_with_facilities_and_accounts_as_tabs(): void
    {
        $admin = $this->actor('project_admin', 'project', $this->project->id);
        $menu = collect(app(ApplicationNavigationService::class)->items($admin))->where('menu', true);
        $this->assertSame(['Tableau de bord', 'Projet & FOSA', 'Liste standard', 'Synchronisation', 'Mon profil'], $menu->pluck('label')->values()->all());

        // Niveau 5 (maquette AdminProjet 02) : onglets FOSA, Comptes utilisateurs, Paramètres d'approvisionnement.
        $this->actingAs($admin)->get('/health-facilities')->assertOk()
            ->assertSee('class="pa-tabs"', false)
            ->assertSee('Comptes utilisateurs')->assertSee('Paramètres d’approvisionnement')
            // Filtres Organisation et Mission masqués.
            ->assertDontSee('<label>Organisation<select', false)->assertDontSee('Missions couvertes');
        $this->get('/users')->assertOk()->assertSee('class="pa-tabs"', false);
    }

    public function test_topbar_shows_the_scope_breadcrumb_and_two_letter_initials(): void
    {
        $admin = $this->actor('project_admin', 'project', $this->project->id);

        $this->actingAs($admin)->get('/health-facilities')->assertOk()
            ->assertSee('topbar-breadcrumb', false)
            ->assertSee('ALIMA')->assertSee('Coordination Maroua')->assertSee('Projet VIH')
            ->assertSee('<span class="profile-avatar">AN</span>', false)
            ->assertSee('data-connection-status', false);
    }

    public function test_v1_api_restrictions_apply_to_real_bearer_tokens(): void
    {
        // Sans Sanctum::actingAs : le jeton est résolu comme pour un vrai appel mobile.
        $coordination = $this->actor('coordination_admin', 'mission', $this->mission->id);
        $token = $coordination->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")->getJson('/api/v1/users')
            ->assertForbidden()->assertJsonPath('code', 'module_not_available_v1');
    }

    public function test_project_admin_reads_the_catalog_but_cannot_change_products(): void
    {
        Sanctum::actingAs($this->actor('project_admin', 'project', $this->project->id));
        $url = "/api/v1/organizations/{$this->organization->id}/catalog/products";

        $this->getJson($url)->assertOk();
        $this->postJson($url, ['code' => 'X', 'name' => 'X'])->assertForbidden()->assertJsonPath('code', 'module_not_available_v1');
    }

    public function test_facility_roles_no_longer_see_the_products_menu_but_keep_their_stock(): void
    {
        $user = $this->actor('site_user', 'site', $this->site->id);
        $keys = collect(app(ApplicationNavigationService::class)->items($user))->pluck('key');
        $this->assertFalse($keys->contains('products'));
        $this->assertTrue($keys->contains('stocks'));

        $this->actingAs($user)->get('/products')->assertForbidden();
        $this->followingRedirects()->get('/stocks')->assertOk()->assertSee('Stock');
        // L'API catalogue reste disponible pour la dispensation et les entrées sur mobile.
        Sanctum::actingAs($user);
        $this->getJson("/api/v1/organizations/{$this->organization->id}/catalog/products")->assertOk();
    }

    public function test_clinical_validation_and_community_destination_are_hidden_in_v1(): void
    {
        $admin = $this->actor('site_admin', 'site', $this->site->id);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/organizations/{$this->organization->id}/prescriptions/00000000-0000-0000-0000-000000000000/validate", ['decision' => 'approve'])
            ->assertForbidden()->assertJsonPath('code', 'module_not_available_v1');

        $this->postJson("/api/v1/organizations/{$this->organization->id}/dispensations", ['destination_type' => 'community'])
            ->assertUnprocessable()->assertJsonValidationErrors('destination_type');
    }
}
