<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationNavigationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCoordinationMissionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp(); $this->seed(DatabaseSeeder::class);
    }

    public function test_coordination_has_mission_permissions_and_second_sidebar_item(): void
    {
        $organization = $this->organization('COORD'); $coordination = $this->actor('coordination_admin', 'organization', $organization);
        $this->assertTrue($coordination->hasPermission('missions.view'));
        $this->assertTrue($coordination->hasPermission('missions.manage'));
        $keys = collect(app(ApplicationNavigationService::class)->mobileItems($coordination))->pluck('key')->values();
        $this->assertSame('dashboard', $keys[0]);
        $this->assertSame('missions', $keys[1]);
        $this->assertSame(1, $keys->filter(fn ($key) => $key === 'missions')->count());
        $this->actingAs($coordination)->get('/missions')->assertOk()->assertSee('Missions');
    }

    public function test_mission_widget_and_api_are_limited_to_coordination_organization(): void
    {
        $a = $this->organization('A'); $b = $this->organization('B');
        $country = Country::where('iso2', 'CM')->firstOrFail();
        Mission::create(['organization_id' => $a->id, 'country_id' => $country->id, 'code' => 'A1', 'name' => 'Mission A', 'is_active' => true]);
        Mission::create(['organization_id' => $b->id, 'country_id' => $country->id, 'code' => 'B1', 'name' => 'Mission B', 'is_active' => true]);
        $coordination = $this->actor('coordination_admin', 'organization', $a);

        Sanctum::actingAs($coordination);
        $widgets = collect($this->getJson('/api/v1/dashboard')->assertOk()->json('widgets'));
        $this->assertSame(1, $widgets->firstWhere('key', 'missions')['value']);
        $this->getJson("/api/v1/organizations/{$a->id}/missions")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/organizations/{$b->id}/missions")->assertNotFound();
    }

    public function test_missions_remain_absent_and_forbidden_for_sago_and_other_roles(): void
    {
        $organization = $this->organization('SEC');
        $sago = $this->actor('sago_admin', 'platform');
        $project = $this->actor('project_admin', 'project');
        $navigation = app(ApplicationNavigationService::class);
        $this->assertFalse(collect($navigation->items($sago))->pluck('key')->contains('missions'));
        $this->assertFalse(collect($navigation->items($project))->pluck('key')->contains('missions'));
        $this->actingAs($sago)->get('/missions')->assertForbidden();
        Sanctum::actingAs($sago);
        $this->getJson("/api/v1/organizations/{$organization->id}/missions")->assertForbidden();
    }

    public function test_coordination_reads_only_its_provisioned_country_and_cannot_create_missions(): void
    {
        $organization = $this->organization('MULTI');
        $cameroon = Country::where('iso2', 'CM')->firstOrFail();
        $chad = Country::where('iso2', 'TD')->firstOrFail();
        $organization->countries()->sync([$cameroon->id, $chad->id]);
        $missions = app(\App\Services\CoordinationProvisioningService::class)->provision($organization, [$cameroon->id, $chad->id]);
        $coordination = $this->actor('coordination_admin', 'organization', $organization);
        $ownId = app(\App\Services\UserScopeService::class)->coordinationMissionIds($coordination)->first();
        $this->actingAs($coordination)->get('/missions')->assertRedirect(route('organizations.missions.show', [$organization, $ownId]));
        $this->get(route('organizations.missions.show', [$organization, $ownId]))->assertOk()->assertDontSee('Créer une mission');
        foreach ($missions as $mission) {
            if ($mission->id !== $ownId) $this->get(route('organizations.missions.show', [$organization, $mission]))->assertNotFound();
        }
        $this->post(route('organizations.missions.store', $organization), ['country_id' => $cameroon->id, 'code' => 'MANUAL', 'name' => 'Interdite'])->assertForbidden();
        $this->assertDatabaseMissing('missions', ['code' => 'MANUAL']);
    }

    public function test_coordination_manages_projects_inside_its_mission(): void
    {
        $organization = $this->organization('PROJECTS');
        $other = $this->organization('OTHER');
        $country = Country::where('iso2', 'CM')->firstOrFail();
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'CM', 'name' => 'Mission Cameroun']);
        $otherMission = Mission::create(['organization_id' => $other->id, 'country_id' => $country->id, 'code' => 'OTHER', 'name' => 'Mission étrangère']);
        $coordination = $this->actor('coordination_admin', 'organization', $organization);

        $this->actingAs($coordination)->get(route('organizations.missions.show', [$organization, $mission]))
            ->assertOk()->assertSee('Projets de la mission')->assertSee('Ajouter un projet')
            ->assertSee('name="mission_id" value="'.$mission->id.'"', false);

        $this->post(route('organizations.projects.store', $organization), [
            'mission_id' => $mission->id,
            'code' => 'NUTRITION',
            'name' => 'Projet Nutrition',
            'description' => 'Prise en charge nutritionnelle',
            'admin' => ['first_name' => 'Admin', 'last_name' => 'Projet', 'email' => 'nutrition@example.test', 'password' => 'PharmaCare!2026', 'password_confirmation' => 'PharmaCare!2026'],
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('projects', [
            'organization_id' => $organization->id,
            'mission_id' => $mission->id,
            'code' => 'NUTRITION',
        ]);

        $this->post(route('organizations.projects.store', $organization), [
            'mission_id' => $otherMission->id,
            'code' => 'INTRUSION',
            'name' => 'Projet étranger',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('projects', ['organization_id' => $organization->id, 'code' => 'INTRUSION']);
        $this->actingAs($coordination)->get(route('organizations.missions.show', [$other, $otherMission]))->assertNotFound();
    }

    public function test_coordination_creates_first_admin_with_each_project_and_cannot_cross_organizations(): void
    {
        $organization = $this->organization('ALIMA');
        $other = $this->organization('MSF');
        $country = Country::where('iso2', 'CM')->firstOrFail();
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'CM', 'name' => 'Coordination']);
        $foreignMission = Mission::create(['organization_id' => $other->id, 'country_id' => $country->id, 'code' => 'CM', 'name' => 'Autre coordination']);
        $actor = $this->actor('coordination_admin', 'mission', $organization);
        $this->actingAs($actor);
        foreach (['Jean', 'Marie'] as $name) {
            $this->post(route('organizations.projects.store', $organization), [
                'mission_id' => $mission->id, 'code' => strtoupper($name), 'name' => 'Projet '.$name,
                'admin' => ['first_name' => $name, 'last_name' => 'Projet', 'email' => strtolower($name).'@example.test', 'password' => 'PharmaCare!2026', 'password_confirmation' => 'PharmaCare!2026'],
            ])->assertRedirect()->assertSessionHasNoErrors();
            $project = Project::where('code', strtoupper($name))->firstOrFail();
            $this->assertDatabaseHas('role_user', ['user_id' => User::where('email', strtolower($name).'@example.test')->value('id'), 'scope_type' => 'project', 'scope_id' => $project->id]);
        }
        $this->get(route('organizations.missions.show', [$organization, $mission]))->assertOk()->assertSee('Jean Projet')->assertSee('Marie Projet');
        $this->post(route('organizations.projects.store', $other), ['mission_id' => $foreignMission->id, 'code' => 'INTRUS', 'name' => 'Interdit'])->assertNotFound();
        $this->post(route('users.store'), ['email' => 'intrus@example.test'])->assertForbidden();
        $this->assertDatabaseMissing('projects', ['code' => 'INTRUS']);
        $this->assertDatabaseMissing('users', ['email' => 'intrus@example.test']);
    }

    private function organization(string $code): Organization
    {
        return Organization::create(['code' => $code, 'name' => 'Organisation '.$code, 'is_active' => true]);
    }

    private function actor(string $code, string $scope, ?Organization $organization = null): User
    {
        $user = User::factory()->create(['is_active' => true, 'organization_id' => $organization?->id]);
        $scopeId = $organization?->id;
        if ($code === 'coordination_admin' && $organization?->missions()->exists()) {
            $scope = 'mission';
            $scopeId = $organization->missions()->value('id');
        }
        $user->roles()->attach(Role::where('code', $code)->firstOrFail(), ['scope_type' => $scope, 'scope_id' => $scopeId]);
        return $user;
    }
}
