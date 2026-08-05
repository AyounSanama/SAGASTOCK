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
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GovernanceMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_administrative_assignment_matrix_is_strict(): void
    {
        [$organization, $project, $facility, $site] = $this->hierarchy('A');
        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);
        $projectAdmin = $this->actor('project_admin', 'project', $project->id);
        $siteAdmin = $this->actor('site_admin', 'site', $site->id);
        $governance = app(GovernanceService::class);
        $scopes = app(UserScopeService::class);

        $this->assertSame(['project_admin', 'site_admin'], $governance->assignableCodes($coordination));
        $this->assertSame(['site_admin'], $governance->assignableCodes($projectAdmin));
        $this->assertSame([], $governance->assignableCodes($siteAdmin));
        $this->assertEqualsCanonicalizing(['project_admin', 'site_admin'], $scopes->assignableRoles($coordination)->pluck('code')->all());
        $this->assertSame(['site_admin'], $scopes->assignableRoles($projectAdmin)->pluck('code')->all());
        $this->assertSame([], $scopes->assignableRoles($siteAdmin)->pluck('code')->all());
    }

    public function test_project_and_site_scopes_do_not_expand_to_siblings(): void
    {
        [$organization, $projectA, $facilityA, $siteA] = $this->hierarchy('A');
        [, $projectB, $facilityB, $siteB] = $this->hierarchy('B', $organization);
        $projectAdmin = $this->actor('project_admin', 'project', $projectA->id);
        $siteAdmin = $this->actor('site_admin', 'site', $siteA->id);
        $scopes = app(UserScopeService::class);

        $this->assertSame([$projectA->id], $scopes->projectIds($projectAdmin)->all());
        $this->assertTrue($scopes->facilityIds($projectAdmin)->contains($facilityA->id));
        $this->assertFalse($scopes->facilityIds($projectAdmin)->contains($facilityB->id));
        $this->assertTrue($scopes->siteIds($projectAdmin)->contains($siteA->id));
        $this->assertFalse($scopes->siteIds($projectAdmin)->contains($siteB->id));
        $this->assertSame([], $scopes->projectIds($siteAdmin)->all());
        $this->assertSame([$siteA->id], $scopes->siteIds($siteAdmin)->all());
        $this->assertFalse($scopes->siteIds($siteAdmin)->contains($siteB->id));
        $this->assertNotSame($projectA->id, $projectB->id);
    }

    public function test_site_admin_has_no_user_or_governance_permissions(): void
    {
        $codes = Role::where('code', 'site_admin')->firstOrFail()->permissions()->pluck('code');
        foreach (['users.view','users.manage','roles.manage','organizations.manage','missions.manage','projects.manage','standard_lists.manage'] as $code) {
            $this->assertFalse($codes->contains($code), $code.' must not be assigned');
        }
        foreach (['stocks.manage','receipts.manage','dispensing.manage','inventories.manage'] as $code) {
            $this->assertTrue($codes->contains($code), $code.' must be assigned');
        }
    }

    public function test_assignable_roles_api_returns_only_the_official_allowed_roles(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('R');

        Sanctum::actingAs($this->actor('coordination_admin', 'organization', $organization->id));
        $this->getJson('/api/v1/assignable-roles')
            ->assertOk()
            ->assertJsonCount(2, 'roles')
            ->assertJsonPath('roles.0.code', 'project_admin')
            ->assertJsonPath('roles.1.code', 'site_admin');

        Sanctum::actingAs($this->actor('project_admin', 'project', $project->id));
        $this->getJson('/api/v1/assignable-roles')
            ->assertOk()
            ->assertJsonCount(1, 'roles')
            ->assertJsonPath('roles.0.code', 'site_admin');

        Sanctum::actingAs($this->actor('site_admin', 'site', $site->id));
        $this->getJson('/api/v1/assignable-roles')
            ->assertOk()
            ->assertExactJson(['roles' => []]);
    }

    public function test_assignable_roles_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/assignable-roles')->assertUnauthorized();
    }

    public function test_coordination_has_organization_management_and_dashboard_navigation(): void
    {
        [$organization] = $this->hierarchy('N');
        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);

        $this->assertTrue($coordination->hasPermission('organizations.manage'));
        $items = app(\App\Services\ApplicationNavigationService::class)->mobileItems($coordination);
        $dashboard = collect($items)->firstWhere('key', 'dashboard');
        $this->assertNotNull($dashboard);
        $this->assertSame('/home', $dashboard['path']);

        Sanctum::actingAs($coordination);
        $this->postJson('/api/v1/organizations', [
            'code' => 'ORG-CREATED', 'name' => 'Organisation créée par Coordination',
            'organization_type' => 'ngo', 'is_active' => true,
        ])->assertCreated()->assertJsonPath('organization.code', 'ORG-CREATED');
        $this->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonFragment(['code' => 'ORG-CREATED']);

        $this->actingAs($coordination)
            ->get(route('configuration.workflow.start', [
                'flowType' => \App\Enums\ConfigurationFlowType::NewOrganization->value,
            ]))
            ->assertRedirect();
    }

    public function test_dynamic_navigation_matches_each_administrative_role(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('MENU');
        $navigation = app(\App\Services\ApplicationNavigationService::class);

        $coordination = collect($navigation->mobileItems(
            $this->actor('coordination_admin', 'organization', $organization->id)
        ))->pluck('key');
        foreach (['dashboard', 'configuration', 'organizations', 'missions', 'projects', 'funding', 'facilities', 'sites', 'users', 'standard-lists', 'products', 'stocks', 'inventories', 'orders', 'reports', 'synchronization', 'settings', 'activity_logs', 'profile'] as $key) {
            $this->assertTrue($coordination->contains($key), "Coordination menu is missing {$key}");
        }
        $this->assertFalse($coordination->contains('receipts'));
        $this->assertFalse($coordination->contains('dispensing'));

        $projectMenu = collect($navigation->mobileItems(
            $this->actor('project_admin', 'project', $project->id)
        ))->pluck('key');
        foreach (['dashboard', 'organizations', 'missions', 'projects', 'facilities', 'sites', 'users', 'standard-lists', 'products', 'stocks', 'inventories', 'orders', 'reports', 'synchronization', 'project_settings', 'activity_logs', 'profile'] as $key) {
            $this->assertTrue($projectMenu->contains($key), "Project menu is missing {$key}");
        }
        foreach (['configuration', 'funding', 'settings', 'site_settings', 'receipts', 'dispensing'] as $key) {
            $this->assertFalse($projectMenu->contains($key), "Project menu must not contain {$key}");
        }

        $siteMenu = collect($navigation->mobileItems(
            $this->actor('site_admin', 'site', $site->id)
        ))->pluck('key');
        foreach (['dashboard', 'products', 'stocks', 'receipts', 'dispensing', 'inventories', 'orders', 'reports', 'synchronization', 'site_settings', 'local_activity_logs', 'profile'] as $key) {
            $this->assertTrue($siteMenu->contains($key), "Site menu is missing {$key}");
        }
        foreach (['configuration', 'organizations', 'missions', 'projects', 'funding', 'facilities', 'sites', 'users', 'standard-lists', 'settings', 'project_settings', 'activity_logs'] as $key) {
            $this->assertFalse($siteMenu->contains($key), "Site menu must not contain {$key}");
        }

        $icons = collect(config('pharmacare_ui.module_icons'));
        foreach ([$coordination, $projectMenu, $siteMenu] as $menu) {
            foreach ($menu as $key) {
                $iconKey = match ($key) {
                    'project_settings', 'site_settings' => 'settings',
                    'local_activity_logs' => 'activity_logs',
                    'standard-lists' => 'standard_lists',
                    default => $key,
                };
                $this->assertTrue($icons->has($iconKey), "Material icon is missing for {$key}");
            }
        }
    }

    public function test_direct_web_routes_enforce_the_same_permissions_as_navigation(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('DIRECT');

        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);
        $this->actingAs($coordination)->get('/configuration')->assertOk();
        $this->actingAs($coordination)->get('/receipts')->assertForbidden();

        $projectAdmin = $this->actor('project_admin', 'project', $project->id);
        $this->actingAs($projectAdmin)->get('/project-settings')->assertOk();
        $this->actingAs($projectAdmin)->get('/site-settings')->assertForbidden();

        $siteAdmin = $this->actor('site_admin', 'site', $site->id);
        $this->actingAs($siteAdmin)->get('/receipts')->assertOk();
        $this->actingAs($siteAdmin)->get('/organizations')->assertForbidden();
    }

    public function test_shared_dashboard_is_dynamic_and_coordination_menu_keeps_required_order(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('DASH');
        $navigation = app(\App\Services\ApplicationNavigationService::class);

        $owner = $this->actor('owner', 'platform', $organization->id);
        Sanctum::actingAs($owner);
        $ownerWidgets = collect($this->getJson('/api/v1/dashboard')->assertOk()->json('widgets'))->pluck('key');
        $this->assertTrue($ownerWidgets->contains('organizations'));
        $this->assertTrue($ownerWidgets->contains('users_active'));
        $this->assertFalse($ownerWidgets->contains('stock_quantity'));
        $this->assertFalse(collect($navigation->mobileItems($owner))->pluck('key')->contains('configuration'));

        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);
        $coordinationMenu = collect($navigation->mobileItems($coordination))->pluck('key')->values();
        $this->assertSame('configuration', $coordinationMenu->get(0));
        $this->assertSame('dashboard', $coordinationMenu->get(1));
        Sanctum::actingAs($coordination);
        $coordinationWidgets = collect($this->getJson('/api/v1/dashboard')->assertOk()->json('widgets'))->pluck('key');
        $this->assertTrue($coordinationWidgets->contains('missions'));
        $this->assertTrue($coordinationWidgets->contains('projects'));
        $this->assertTrue($coordinationWidgets->contains('sites'));

        Sanctum::actingAs($this->actor('project_admin', 'project', $project->id));
        $projectWidgets = collect($this->getJson('/api/v1/dashboard')->assertOk()->json('widgets'))->pluck('key');
        $this->assertTrue($projectWidgets->contains('projects'));
        $this->assertTrue($projectWidgets->contains('facilities'));
        $this->assertTrue($projectWidgets->contains('sites'));

        Sanctum::actingAs($this->actor('site_admin', 'site', $site->id));
        $siteWidgets = collect($this->getJson('/api/v1/dashboard')->assertOk()->json('widgets'))->pluck('key');
        $this->assertTrue($siteWidgets->contains('products'));
        $this->assertTrue($siteWidgets->contains('stock_quantity'));
        $this->assertTrue($siteWidgets->contains('stockouts'));
        $this->assertFalse($siteWidgets->contains('organizations'));
        $this->assertFalse($siteWidgets->contains('users_active'));
    }

    public function test_coordination_and_project_admin_can_create_only_authorized_accounts(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('U');
        $projectRole = Role::where('code', 'project_admin')->firstOrFail();
        $siteRole = Role::where('code', 'site_admin')->firstOrFail();
        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);

        Sanctum::actingAs($coordination);
        $this->postJson('/api/v1/users', [
            'name' => 'Admin Projet Créé', 'email' => 'project.created@example.org',
            'role_id' => $projectRole->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ])->assertCreated()->assertJsonPath('user.roles.0.code', 'project_admin');
        $this->postJson('/api/v1/users', [
            'name' => 'Admin Site Créé', 'email' => 'site.by.coordination@example.org',
            'role_id' => $siteRole->id, 'scope_type' => 'site', 'scope_id' => $site->id,
        ])->assertCreated()->assertJsonPath('user.roles.0.code', 'site_admin');

        $projectAdmin = $this->actor('project_admin', 'project', $project->id);
        Sanctum::actingAs($projectAdmin);
        $this->postJson('/api/v1/users', [
            'name' => 'Admin Site Projet', 'email' => 'site.by.project@example.org',
            'role_id' => $siteRole->id, 'scope_type' => 'site', 'scope_id' => $site->id,
        ])->assertCreated()->assertJsonPath('user.roles.0.code', 'site_admin');
        $this->postJson('/api/v1/users', [
            'name' => 'Projet Interdit', 'email' => 'project.forbidden@example.org',
            'role_id' => $projectRole->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ])->assertForbidden();

        $siteAdmin = $this->actor('site_admin', 'site', $site->id);
        Sanctum::actingAs($siteAdmin);
        $this->postJson('/api/v1/users', [
            'name' => 'Interdit', 'email' => 'site.forbidden@example.org',
            'role_id' => $siteRole->id, 'scope_type' => 'site', 'scope_id' => $site->id,
        ])->assertForbidden();
    }

    public function test_project_admin_sees_the_dynamic_user_form_fields(): void
    {
        [, $project] = $this->hierarchy('F');
        $projectAdmin = $this->actor('project_admin', 'project', $project->id);

        $this->actingAs($projectAdmin)->get('/users')
            ->assertOk()
            ->assertSee('id="create-role"', false)
            ->assertSee('id="create-organization"', false)
            ->assertSee('id="create-mission"', false)
            ->assertSee('id="create-project"', false)
            ->assertSee('id="create-facility"', false)
            ->assertSee('id="create-site"', false);
    }

    private function actor(string $roleCode, string $scopeType, string $scopeId): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, ['scope_type' => $scopeType, 'scope_id' => $scopeId]);
        return $user;
    }

    private function hierarchy(string $suffix, ?Organization $organization = null): array
    {
        $organization ??= Organization::create(['code' => 'ORG-'.$suffix, 'name' => 'Organisation '.$suffix]);
        $country = Country::firstOrCreate(['iso2' => 'C'.$suffix], ['name' => 'Pays '.$suffix]);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'M-'.$suffix, 'name' => 'Mission '.$suffix]);
        $project = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'P-'.$suffix, 'name' => 'Projet '.$suffix]);
        $facility = HealthFacility::create(['organization_id' => $organization->id, 'code' => 'F-'.$suffix, 'name' => 'Formation '.$suffix, 'facility_type' => 'clinic']);
        $facility->projects()->attach($project);
        $site = Site::create(['organization_id' => $organization->id, 'health_facility_id' => $facility->id, 'code' => 'S-'.$suffix, 'name' => 'Site '.$suffix, 'site_type' => 'dispensing']);
        return [$organization, $project, $facility, $site];
    }
}
