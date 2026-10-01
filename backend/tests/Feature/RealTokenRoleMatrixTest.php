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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ordre des middlewares : chaque rôle est testé avec un VRAI jeton Bearer
 * (sans Sanctum::actingAs), comme un appel mobile. Les middlewares globaux
 * s'exécutent avant auth:sanctum ; ces tests vérifient qu'ils voient bien
 * l'utilisateur du jeton (une requête autorisée, une requête refusée par rôle).
 */
class RealTokenRoleMatrixTest extends TestCase
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

    /**
     * Chaque appel repart d'un état d'authentification vierge, comme une vraie
     * requête HTTP : sinon, le premier passage par auth:sanctum fixe la garde
     * « sanctum » par défaut pour la suite du test et masque l'erreur d'ordre.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $auth = $this->app['auth'];
        $auth->forgetGuards();
        $auth->setDefaultDriver('web');

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** En-têtes d'un vrai appel mobile pour un rôle. */
    private function bearer(string $role, string $scopeType, ?string $scopeId, bool $readOnly = false): array
    {
        $user = User::factory()->create([
            'organization_id' => $role === 'sago_admin' ? null : $this->organization->id,
            'is_active' => true, 'must_change_password' => false, 'read_only' => $readOnly,
        ]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return ['Authorization' => 'Bearer '.$user->createToken('mobile')->plainTextToken, 'Accept' => 'application/json'];
    }

    public function test_sago_admin_token_is_kept_out_of_operational_data(): void
    {
        $headers = $this->bearer('sago_admin', 'platform', null);

        $this->getJson('/api/v1/organizations', $headers)->assertOk();
        $this->getJson("/api/v1/organizations/{$this->organization->id}/missions", $headers)->assertForbidden();
        // Route sans middleware de permission : seule la frontière Sago la protège.
        $this->getJson("/api/v1/organizations/{$this->organization->id}/operational-report", $headers)
            ->assertForbidden()->assertJsonPath('message', 'Ce domaine opérationnel est réservé aux administrateurs des organisations.');
    }

    public function test_coordination_token_follows_the_v1_modules(): void
    {
        $headers = $this->bearer('coordination_admin', 'mission', $this->mission->id);

        $this->getJson("/api/v1/organizations/{$this->organization->id}/missions", $headers)->assertOk();
        $this->getJson('/api/v1/users', $headers)->assertForbidden()->assertJsonPath('code', 'module_not_available_v1');
    }

    public function test_read_only_coordination_token_cannot_write(): void
    {
        $headers = $this->bearer('coordination_admin', 'mission', $this->mission->id, readOnly: true);

        $this->getJson("/api/v1/projects/{$this->project->id}", $headers)->assertOk();
        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", ['care_level_ids' => []], $headers)
            ->assertForbidden()->assertJsonPath('message', 'Compte en lecture seule : cette action n’est pas autorisée.');
    }

    public function test_project_admin_token_follows_the_v1_modules(): void
    {
        $headers = $this->bearer('project_admin', 'project', $this->project->id);

        $this->getJson("/api/v1/projects/{$this->project->id}", $headers)->assertOk();
        $this->getJson('/api/v1/missions', $headers)->assertForbidden()->assertJsonPath('code', 'module_not_available_v1');
        $this->postJson("/api/v1/organizations/{$this->organization->id}/catalog/products", ['code' => 'X', 'name' => 'X'], $headers)
            ->assertForbidden()->assertJsonPath('code', 'module_not_available_v1');
    }

    public function test_site_admin_token_cannot_use_hidden_v1_features(): void
    {
        $headers = $this->bearer('site_admin', 'site', $this->site->id);

        $this->getJson("/api/v1/organizations/{$this->organization->id}/catalog/products", $headers)->assertOk();
        $this->postJson("/api/v1/organizations/{$this->organization->id}/prescriptions/00000000-0000-0000-0000-000000000000/validate", ['decision' => 'approve'], $headers)
            ->assertForbidden()->assertJsonPath('code', 'module_not_available_v1');
    }

    public function test_site_user_token_is_limited_by_its_permissions(): void
    {
        $headers = $this->bearer('site_user', 'site', $this->site->id);

        $this->getJson("/api/v1/organizations/{$this->organization->id}/catalog/products", $headers)->assertOk();
        $this->getJson('/api/v1/users', $headers)->assertForbidden();
    }

    public function test_missing_or_invalid_token_is_refused(): void
    {
        $this->getJson("/api/v1/projects/{$this->project->id}")->assertUnauthorized();
        $this->getJson("/api/v1/projects/{$this->project->id}", ['Authorization' => 'Bearer 1|faux-jeton'])->assertUnauthorized();
    }
}
