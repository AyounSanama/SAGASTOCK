<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\HealthFacilityConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Niveau 3, lot 4 (AM-172) — Policies utilisateurs, FOSA et projets : mêmes
 * règles pour le Web et l'API, cloisonnement entre deux coordinations.
 */
class AccessPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    /** @var array<string, array{mission: Mission, project: Project, facility: HealthFacility, coordination: User, readOnly: User, projectAdmin: User, siteAdmin: User, siteUser: User}> */
    private array $zones = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->zones['yde'] = $this->zone('YDE');
        $this->zones['dla'] = $this->zone('DLA');
    }

    private function zone(string $code): array
    {
        $mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => $code, 'name' => "Coordination {$code}", 'is_active' => true]);
        $project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $mission->id, 'code' => "P-{$code}", 'name' => "Projet {$code}",
            'order_period_months' => 3, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 1]);
        $facility = HealthFacility::create(['organization_id' => $this->organization->id, 'mission_id' => $mission->id, 'code' => "CSI-{$code}", 'name' => "CSI {$code}",
            'facility_type' => 'health_center', 'validation_status' => HealthFacility::STATUS_VALIDATED, 'is_active' => true]);
        $facility->projects()->sync([$project->id]);
        $site = app(HealthFacilityConfigurationService::class)->ensurePrimarySite($facility);

        return [
            'mission' => $mission, 'project' => $project, 'facility' => $facility,
            'coordination' => $this->user('coordination_admin', 'mission', $mission->id),
            'readOnly' => $this->user('coordination_admin', 'mission', $mission->id, ['read_only' => true]),
            'projectAdmin' => $this->user('project_admin', 'project', $project->id),
            'siteAdmin' => $this->user('site_admin', 'site', $site->id),
            'siteUser' => $this->user('site_user', 'site', $site->id),
        ];
    }

    private function user(string $role, string $scopeType, string $scopeId, array $attributes = []): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false, ...$attributes]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function decision(User $actor, string $ability, mixed $target): ?int
    {
        $response = Gate::forUser($actor)->inspect($ability, $target);

        return $response->allowed() ? null : ($response->status() ?? 403);
    }

    public function test_user_policy_hides_accounts_of_another_coordination(): void
    {
        ['coordination' => $coordination, 'projectAdmin' => $projectAdmin, 'siteAdmin' => $siteAdmin, 'siteUser' => $siteUser] = $this->zones['yde'];
        $other = $this->zones['dla'];

        // Périmètre : visible (et gérable selon la matrice) dans sa coordination, introuvable ailleurs.
        $this->assertNull($this->decision($coordination, 'view', $projectAdmin));
        $this->assertNull($this->decision($coordination, 'update', $projectAdmin));
        $this->assertSame(404, $this->decision($coordination, 'view', $other['projectAdmin']));
        $this->assertSame(404, $this->decision($coordination, 'update', $other['projectAdmin']));
        $this->assertSame(404, $this->decision($projectAdmin, 'view', $other['siteUser']));

        // Matrice des rôles : l'Admin Projet gère les comptes de ses FOSA, pas l'Admin Coordination.
        $this->assertNull($this->decision($projectAdmin, 'resetPassword', $siteAdmin));
        // Comptes placés au-dessus : hors de son périmètre de comptes, donc introuvables.
        $this->assertSame(404, $this->decision($projectAdmin, 'update', $coordination));
        $this->assertSame(404, $this->decision($siteUser, 'update', $siteAdmin));

        // Jamais son propre compte (422), jamais par un compte en lecture seule (403).
        $this->assertSame(422, $this->decision($coordination, 'delete', $coordination));
        $this->assertSame(403, $this->decision($this->zones['yde']['readOnly'], 'update', $projectAdmin));
    }

    public function test_coordination_suspends_only_accounts_of_its_own_projects(): void
    {
        ['coordination' => $coordination, 'readOnly' => $readOnly, 'projectAdmin' => $projectAdmin, 'siteUser' => $siteUser] = $this->zones['yde'];

        $this->assertNull($this->decision($coordination, 'setActive', $projectAdmin));
        $this->assertNull($this->decision($coordination, 'setActive', $siteUser));
        $this->assertNull($this->decision($coordination, 'setActive', $readOnly));
        $this->assertSame(404, $this->decision($coordination, 'setActive', $this->zones['dla']['siteUser']));
        $this->assertSame(403, $this->decision($coordination, 'setActive', $coordination));
        $this->assertSame(403, $this->decision($readOnly, 'setActive', $siteUser));
        $this->assertSame(403, $this->decision($projectAdmin, 'setActive', $siteUser));

        // Même décision par l'API et par le Web.
        Sanctum::actingAs($coordination);
        $this->postJson("/api/v1/coordination/accounts/{$this->zones['dla']['siteUser']->id}/suspend")->assertNotFound();
        $this->actingAs($coordination)->post(route('coordination.accounts.suspend', $this->zones['dla']['siteUser']))->assertNotFound();
        $this->assertTrue($this->zones['dla']['siteUser']->fresh()->is_active);
    }

    public function test_facility_policy_separates_coordinations_and_roles(): void
    {
        ['coordination' => $coordination, 'readOnly' => $readOnly, 'projectAdmin' => $projectAdmin, 'siteAdmin' => $siteAdmin, 'facility' => $facility] = $this->zones['yde'];
        $otherFacility = $this->zones['dla']['facility'];

        $this->assertNull($this->decision($coordination, 'view', $facility));
        $this->assertNull($this->decision($projectAdmin, 'view', $facility));
        $this->assertNull($this->decision($siteAdmin, 'view', $facility));
        $this->assertSame(404, $this->decision($coordination, 'view', $otherFacility));
        $this->assertSame(404, $this->decision($projectAdmin, 'update', $otherFacility));
        $this->assertSame(404, $this->decision($siteAdmin, 'view', $otherFacility));

        // Valider, refuser, suspendre : Admin Coordination de la FOSA uniquement.
        $this->assertNull($this->decision($coordination, 'coordinate', $facility));
        $this->assertSame(404, $this->decision($coordination, 'coordinate', $otherFacility));
        $this->assertSame(403, $this->decision($readOnly, 'coordinate', $facility));
        $this->assertSame(403, $this->decision($projectAdmin, 'coordinate', $facility));

        Sanctum::actingAs($coordination);
        $this->postJson("/api/v1/coordination/facilities/{$otherFacility->id}/suspend", ['reason' => 'Contrôle de cloisonnement'])->assertNotFound();
        Sanctum::actingAs($projectAdmin);
        $this->getJson("/api/v1/organizations/{$this->organization->id}/facilities/{$otherFacility->id}")->assertNotFound();
        $this->postJson("/api/v1/coordination/facilities/{$facility->id}/suspend", ['reason' => 'Contrôle de cloisonnement'])->assertForbidden();
        $this->assertSame(HealthFacility::STATUS_VALIDATED, $otherFacility->fresh()->validation_status);
        $this->assertSame(HealthFacility::STATUS_VALIDATED, $facility->fresh()->validation_status);
    }

    public function test_project_policy_limits_projects_to_their_coordination(): void
    {
        ['coordination' => $coordination, 'readOnly' => $readOnly, 'projectAdmin' => $projectAdmin, 'siteUser' => $siteUser, 'project' => $project] = $this->zones['yde'];
        $otherProject = $this->zones['dla']['project'];

        $this->assertNull($this->decision($coordination, 'view', $project));
        $this->assertNull($this->decision($projectAdmin, 'view', $project));
        $this->assertSame(404, $this->decision($coordination, 'view', $otherProject));
        $this->assertSame(404, $this->decision($projectAdmin, 'update', $otherProject));
        $this->assertSame(404, $this->decision($siteUser, 'view', $project));

        // Configuration médicale et Liste Standard : Coordination uniquement.
        $this->assertNull($this->decision($coordination, 'configure', $project));
        $this->assertSame(403, $this->decision($projectAdmin, 'configure', $project));
        $this->assertSame(403, $this->decision($readOnly, 'configure', $project));
        $this->assertSame(404, $this->decision($coordination, 'configure', $otherProject));

        Sanctum::actingAs($coordination);
        $this->getJson("/api/v1/projects/{$otherProject->id}")->assertNotFound();
        Sanctum::actingAs($projectAdmin);
        $this->getJson("/api/v1/projects/{$project->id}/medical-configuration")->assertOk()->assertJsonPath('can_manage', false);
        $this->actingAs($coordination)->get(route('projects.medical-configuration', $otherProject->id))->assertNotFound();

        // Restauration d'un projet archivé : uniquement dans ses missions.
        $otherProject->delete();
        $this->assertSame(404, $this->decision($coordination, 'restore', $otherProject));
        Sanctum::actingAs($coordination);
        $this->postJson("/api/v1/organizations/{$this->organization->id}/projects/archived/{$otherProject->id}/restore")->assertNotFound();
        $this->assertSoftDeleted($otherProject);
    }
}
