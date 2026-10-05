<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CatalogReference;
use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Niveau 3 (AM-172) — « Ma Coordination » : valider, refuser, suspendre les FOSA et les comptes. */
class CoordinationValidationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private Project $project;

    private User $coordination;

    private User $projectAdmin;

    private array $facilityFields;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id, 'code' => 'YDE', 'name' => 'Coordination Yaoundé', 'is_active' => true]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'GFF05', 'name' => 'Projet SSP',
            'order_period_months' => 3, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 1,
        ]);
        $this->coordination = $this->user('coordination_admin', 'mission', $this->mission->id);
        $this->projectAdmin = $this->user('project_admin', 'project', $this->project->id);

        $level = CatalogReference::whereNull('organization_id')->where('code', 'SSP')->value('id');
        $adults = CatalogReference::whereNull('organization_id')->where('code', 'POP-ADULT')->value('id');
        $malaria = CatalogReference::create(['organization_id' => $this->organization->id, 'reference_type' => 'pathology', 'code' => 'PALU', 'name' => 'Paludisme simple']);
        Sanctum::actingAs($this->coordination);
        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", [
            'care_level_ids' => [$level], 'target_population_ids' => [$adults],
            'pathologies' => [['pathology_id' => $malaria->id, 'target_population_ids' => [$adults]]],
        ])->assertOk();
        $this->facilityFields = [
            'care_level_id' => $level, 'facility_category_id' => CatalogReference::whereNull('organization_id')->where('code', 'CAT-CSI')->value('id'),
            'target_population_ids' => [$adults], 'pathology_ids' => [$malaria->id],
        ];
    }

    private function user(string $role, string $scopeType, string $scopeId, array $attributes = []): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false, ...$attributes]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function declare(string $code = 'CSI-EKOUM'): HealthFacility
    {
        Sanctum::actingAs($this->projectAdmin);
        $id = $this->postJson("/api/v1/organizations/{$this->organization->id}/facilities", [
            'code' => $code, 'name' => "CSI {$code}", ...$this->facilityFields,
        ])->assertCreated()->json('facility.id');

        return HealthFacility::findOrFail($id);
    }

    /** FOSA validée avec un compte Admin Site créé par l'Admin Projet. */
    private function facilityWithAccount(): array
    {
        $facility = $this->declare();
        Sanctum::actingAs($this->coordination);
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/validate")->assertOk();
        $site = $facility->primarySite()->firstOrFail();
        $account = $this->user('site_admin', 'site', $site->id);

        return [$facility->fresh(), $account];
    }

    public function test_coordination_validates_a_pending_facility_and_opens_its_accounts(): void
    {
        $facility = $this->declare();
        Sanctum::actingAs($this->coordination);
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/validate")
            ->assertOk()->assertJsonPath('facility.validation_status', HealthFacility::STATUS_VALIDATED);

        $facility->refresh();
        $this->assertSame($this->coordination->id, $facility->validated_by);
        $this->assertTrue(AuditLog::where('event', 'facility.validated')->where('auditable_id', $facility->id)->exists());
        // Une FOSA déjà validée ne se valide pas deux fois.
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/validate")->assertStatus(422);
    }

    public function test_refusal_requires_a_reason_and_the_corrected_facility_returns_to_pending(): void
    {
        $facility = $this->declare();
        Sanctum::actingAs($this->coordination);
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/refuse", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/refuse", ['reason' => 'Catégorie incorrecte : CMA, pas CSI.'])
            ->assertOk()->assertJsonPath('facility.validation_status', HealthFacility::STATUS_REFUSED);

        // L'Admin Projet voit le motif, corrige, et la FOSA repasse en attente.
        Sanctum::actingAs($this->projectAdmin);
        $this->getJson("/api/v1/organizations/{$this->organization->id}/facilities/{$facility->id}")
            ->assertOk()->assertJsonPath('facility.refusal_reason', 'Catégorie incorrecte : CMA, pas CSI.');
        $this->putJson("/api/v1/organizations/{$this->organization->id}/facilities/{$facility->id}", [
            'code' => $facility->code, 'name' => 'CMA corrigé', ...$this->facilityFields,
        ])->assertOk();
        $this->assertSame(HealthFacility::STATUS_PENDING, $facility->fresh()->validation_status);
    }

    public function test_suspension_blocks_the_facility_accounts_until_reactivation(): void
    {
        [$facility, $account] = $this->facilityWithAccount();
        $token = $account->createToken('mobile')->plainTextToken;

        Sanctum::actingAs($this->coordination);
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/suspend", ['reason' => 'Arrêt des activités.'])
            ->assertOk()->assertJsonPath('facility.validation_status', HealthFacility::STATUS_SUSPENDED);
        $this->assertSame(0, $account->tokens()->count());

        // Vrai jeton : refusé, et nouvelle connexion impossible.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->post('/login', ['login' => $account->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest('web');

        $this->withoutToken();
        Sanctum::actingAs($this->coordination);
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/reactivate")
            ->assertOk()->assertJsonPath('facility.validation_status', HealthFacility::STATUS_VALIDATED);
        $this->assertNull(\App\Http\Middleware\EnsureAccountIsActive::blockingReason($account->fresh()));
    }

    public function test_coordination_suspends_and_reactivates_a_facility_account(): void
    {
        [, $account] = $this->facilityWithAccount();
        Sanctum::actingAs($this->coordination);
        $this->postJson("/api/v1/coordination/accounts/{$account->id}/suspend")->assertOk()->assertJsonPath('user.is_active', false);
        $this->postJson("/api/v1/coordination/accounts/{$account->id}/reactivate")->assertOk()->assertJsonPath('user.is_active', true);
        // Jamais son propre compte.
        $this->postJson("/api/v1/coordination/accounts/{$this->coordination->id}/suspend")->assertForbidden();
    }

    public function test_only_the_coordination_of_the_facility_can_act(): void
    {
        $facility = $this->declare();
        // L'Admin Projet ne valide pas sa propre FOSA.
        Sanctum::actingAs($this->projectAdmin);
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/validate")->assertForbidden();

        // Coordination en lecture seule : refusée.
        $reader = $this->user('coordination_admin', 'mission', $this->mission->id, ['read_only' => true]);
        Sanctum::actingAs($reader);
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/validate")->assertForbidden();

        // Coordination d'une autre mission : la FOSA n'existe pas pour elle.
        $other = Mission::create(['organization_id' => $this->organization->id, 'country_id' => $this->mission->country_id, 'code' => 'MRA', 'name' => 'Coordination Maroua', 'is_active' => true]);
        Sanctum::actingAs($this->user('coordination_admin', 'mission', $other->id));
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/validate")->assertNotFound();
        $this->assertSame(HealthFacility::STATUS_PENDING, $facility->fresh()->validation_status);
    }

    public function test_ma_coordination_web_tabs_follow_the_mockups(): void
    {
        $facility = $this->declare();
        $this->actingAs($this->coordination);
        $page = fn (string $tab) => $this->get(route('organizations.missions.show', [$this->organization, $this->mission, 'tab' => $tab]));

        $page('projects')->assertOk()->assertSee('Projets et programmes')->assertSee('GFF05')->assertSee('1 FOSA attend votre validation.');
        $page('pending')->assertOk()->assertSee($facility->name)->assertSee('Valider la FOSA')->assertSee('Refuser avec un motif');

        $this->post(route('coordination.facilities.validate', $facility->id))->assertRedirect();
        $this->assertSame(HealthFacility::STATUS_VALIDATED, $facility->fresh()->validation_status);
        $page('facilities')->assertOk()->assertSee($facility->name)->assertSee('Suspendre');
        $page('accounts')->assertOk()->assertSee('Comptes de la coordination')->assertSee('Journal des actions');
        $page('journal')->assertOk()->assertSee('avez validé la FOSA');
    }

    public function test_overview_api_feeds_the_mobile_screens(): void
    {
        $this->declare();
        Sanctum::actingAs($this->coordination);
        $this->getJson('/api/v1/coordination/overview')->assertOk()
            ->assertJsonPath('stats.pending', 1)
            ->assertJsonPath('projects.0.code', 'GFF05')
            ->assertJsonPath('facilities.0.validation_status', HealthFacility::STATUS_PENDING);
        $this->getJson('/api/v1/coordination/journal')->assertOk()->assertJsonPath('data.0.event', 'facility.created');
    }
}
