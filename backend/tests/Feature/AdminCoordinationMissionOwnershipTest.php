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

    public function test_mission_page_and_web_crud_use_only_authorized_countries(): void
    {
        $organization = $this->organization('MULTI');
        $cameroon = Country::where('iso2', 'CM')->firstOrFail();
        $chad = Country::where('iso2', 'TD')->firstOrFail();
        $france = Country::where('iso2', 'FR')->firstOrFail();
        $organization->countries()->sync([$cameroon->id, $chad->id]);
        $coordination = $this->actor('coordination_admin', 'organization', $organization);

        $this->actingAs($coordination)->get('/missions')
            ->assertOk()
            ->assertSee('Créer une mission')
            ->assertSee('Cameroun')
            ->assertSee('Tchad')
            ->assertDontSee('France');

        $this->post(route('organizations.missions.store', $organization), [
            'country_id' => $cameroon->id,
            'code' => 'CM_TEST',
            'name' => 'Mission Cameroun',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('missions', ['organization_id' => $organization->id, 'code' => 'CM_TEST']);

        $this->from('/missions')->post(route('organizations.missions.store', $organization), [
            'country_id' => $france->id,
            'code' => 'FR_TEST',
            'name' => 'Mission France',
        ])->assertRedirect('/missions')->assertSessionHasErrors('country_id');
        $this->assertDatabaseMissing('missions', ['organization_id' => $organization->id, 'code' => 'FR_TEST']);
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
            'is_active' => '1',
        ])->assertRedirect();
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

    public function test_coordination_creates_multiple_project_admin_users_and_cannot_cross_organizations(): void
    {
        $organization = $this->organization('ALIMA');
        $other = $this->organization('MSF');
        $country = Country::where('iso2', 'CM')->firstOrFail();
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'ALIMA_CM', 'name' => 'Mission Cameroun']);
        $otherMission = Mission::create(['organization_id' => $other->id, 'country_id' => $country->id, 'code' => 'MSF_CM', 'name' => 'Mission MSF']);
        $project = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'NUTRITION', 'name' => 'Projet Nutrition']);
        $otherProject = Project::create(['organization_id' => $other->id, 'mission_id' => $otherMission->id, 'code' => 'OTHER', 'name' => 'Projet MSF']);
        $coordination = $this->actor('coordination_admin', 'organization', $organization);
        $role = Role::where('code', 'project_admin')->firstOrFail();

        foreach ([['Jean', 'Dupont', 'jean'], ['Marie', 'Martin', 'marie']] as [$first, $last, $login]) {
            $this->actingAs($coordination)->post(route('users.store'), [
                'form_context' => 'mission-project-admin',
                'first_name' => $first,
                'last_name' => $last,
                'username' => $login,
                'email' => "$login@example.test",
                'role_id' => $role->id,
                'organization_id' => $organization->id,
                'mission_id' => $mission->id,
                'project_id' => $project->id,
                'is_active' => '1',
            ])->assertRedirect(route('organizations.missions.show', [$organization, $mission]));
        }

        $this->assertSame(2, User::whereHas('roles', fn ($query) => $query
            ->where('roles.code', 'project_admin')->where('role_user.scope_type', 'project')
            ->where('role_user.scope_id', $project->id))->count());
        $this->actingAs($coordination)->get(route('organizations.missions.show', [$organization, $mission]))
            ->assertOk()->assertSee('Jean Dupont')->assertSee('Marie Martin')->assertSee('ADMIN_PROJECT');

        $this->actingAs($coordination)->post(route('users.store'), [
            'form_context' => 'mission-project-admin',
            'first_name' => 'Paul', 'last_name' => 'Intrus', 'username' => 'paul',
            'email' => 'paul@example.test', 'role_id' => $role->id,
            'organization_id' => $other->id, 'mission_id' => $otherMission->id,
            'project_id' => $otherProject->id,
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'paul@example.test']);
    }

    private function organization(string $code): Organization
    {
        return Organization::create(['code' => $code, 'name' => 'Organisation '.$code, 'is_active' => true]);
    }

    private function actor(string $code, string $scope, ?Organization $organization = null): User
    {
        $user = User::factory()->create(['is_active' => true, 'organization_id' => $organization?->id]);
        $user->roles()->attach(Role::where('code', $code)->firstOrFail(), ['scope_type' => $scope, 'scope_id' => $organization?->id]);
        return $user;
    }
}
