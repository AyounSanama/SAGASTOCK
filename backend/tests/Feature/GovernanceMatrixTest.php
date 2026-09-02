<?php

namespace Tests\Feature;

use App\Enums\ConfigurationFlowType;
use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\ApplicationNavigationService;
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
        $this->assertSame(['site_user'], $governance->assignableCodes($siteAdmin));
        $this->assertEqualsCanonicalizing(['project_admin', 'site_admin'], $scopes->assignableRoles($coordination)->pluck('code')->all());
        $this->assertSame(['site_admin'], $scopes->assignableRoles($projectAdmin)->pluck('code')->all());
        $this->assertSame(['site_user'], $scopes->assignableRoles($siteAdmin)->pluck('code')->all());
    }

    public function test_official_role_cannot_bypass_assignment_matrix_with_a_platform_pivot(): void
    {
        [$organization] = $this->hierarchy('PLATFORM-GUARD');
        $coordination = $this->actor('coordination_admin', 'platform', $organization->id);

        $this->assertEqualsCanonicalizing(
            ['project_admin', 'site_admin'],
            app(UserScopeService::class)->assignableRoles($coordination)->pluck('code')->all(),
        );
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
        foreach (['users.view', 'users.manage', 'roles.manage', 'organizations.manage', 'missions.manage', 'projects.manage', 'standard_lists.manage'] as $code) {
            $this->assertFalse($codes->contains($code), $code.' must not be assigned');
        }
        foreach (['stocks.manage', 'receipts.manage', 'dispensing.manage', 'inventories.manage'] as $code) {
            $this->assertTrue($codes->contains($code), $code.' must be assigned');
        }
    }

    public function test_assignable_roles_api_returns_only_the_official_allowed_roles(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('R');

        Sanctum::actingAs($this->actor('coordination_admin', 'organization', $organization->id));
        $this->getJson('/api/v1/assignable-roles')->assertForbidden();

        Sanctum::actingAs($this->actor('project_admin', 'project', $project->id));
        $this->getJson('/api/v1/assignable-roles')
            ->assertOk()
            ->assertJsonCount(1, 'roles')
            ->assertJsonPath('roles.0.code', 'site_admin');

        Sanctum::actingAs($this->actor('site_admin', 'site', $site->id));
        $this->getJson('/api/v1/assignable-roles')
            ->assertOk()
            ->assertJsonCount(1, 'roles')
            ->assertJsonPath('roles.0.code', 'site_user')
            ->assertJsonPath('roles.0.scope_type', 'site');
    }

    public function test_sago_admin_can_assign_only_coordination_admin(): void
    {
        [$organization] = $this->hierarchy('SAGO-ASSIGNABLE');

        Sanctum::actingAs($this->actor('sago_admin', 'platform', $organization->id));

        $this->getJson('/api/v1/assignable-roles')
            ->assertOk()
            ->assertJsonCount(1, 'roles')
            ->assertJsonPath('roles.0.code', 'coordination_admin')
            ->assertJsonMissingPath('roles.0.scope_id');
    }

    public function test_assignable_roles_api_never_returns_an_inactive_target_role(): void
    {
        [$organization] = $this->hierarchy('INACTIVE-ASSIGNABLE');
        Role::where('code', 'site_admin')->update(['is_active' => false]);

        Sanctum::actingAs($this->actor('coordination_admin', 'organization', $organization->id));

        $this->getJson('/api/v1/assignable-roles')->assertForbidden();
    }

    public function test_assignable_roles_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/assignable-roles')->assertUnauthorized();
    }

    public function test_coordination_has_organization_management_and_dashboard_navigation(): void
    {
        [$organization] = $this->hierarchy('N');
        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);

        $this->assertFalse($coordination->hasPermission('organizations.manage'));
        $items = app(ApplicationNavigationService::class)->mobileItems($coordination);
        $dashboard = collect($items)->firstWhere('key', 'dashboard');
        $this->assertNotNull($dashboard);
        $this->assertSame('/home', $dashboard['path']);

        Sanctum::actingAs($coordination);
        $this->postJson('/api/v1/organizations', [
            'code' => 'ORG-CREATED', 'name' => 'Organisation créée par Coordination',
            'organization_type' => 'ngo', 'is_active' => true,
        ])->assertForbidden();
        $this->getJson('/api/v1/organizations')->assertForbidden();

        $this->actingAs($coordination)
            ->get(route('configuration.workflow.start', [
                'flowType' => ConfigurationFlowType::NewOrganization->value,
            ]))
            ->assertForbidden();
    }

    public function test_coordination_can_manage_project_funding_but_project_admin_cannot(): void
    {
        $coordinationPermissions = Role::where('code', 'coordination_admin')
            ->firstOrFail()->permissions()->pluck('code');
        $projectPermissions = Role::where('code', 'project_admin')
            ->firstOrFail()->permissions()->pluck('code');

        $this->assertTrue($coordinationPermissions->contains('funding.view'));
        $this->assertTrue($coordinationPermissions->contains('funding.manage'));
        $this->assertFalse($projectPermissions->contains('funding.manage'));
    }

    public function test_dynamic_navigation_matches_each_administrative_role(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('MENU');
        $navigation = app(ApplicationNavigationService::class);

        $coordination = collect($navigation->mobileItems(
            $this->actor('coordination_admin', 'organization', $organization->id)
        ))->pluck('key');
        foreach (['dashboard', 'missions', 'projects', 'standard-lists', 'profile'] as $key) {
            $this->assertTrue($coordination->contains($key), "Coordination menu is missing {$key}");
        }
        foreach (['users', 'facilities', 'sites', 'products', 'stocks', 'receipts', 'dispensing', 'inventory-orders', 'reports', 'synchronization', 'configuration', 'organizations', 'funding', 'inventories', 'orders', 'settings', 'activity_logs'] as $key) {
            $this->assertFalse($coordination->contains($key));
        }

        $projectMenu = collect($navigation->mobileItems(
            $this->actor('project_admin', 'project', $project->id)
        ))->pluck('key');
        foreach (['dashboard', 'projects', 'facilities', 'users', 'standard-lists', 'profile'] as $key) {
            $this->assertTrue($projectMenu->contains($key), "Project menu is missing {$key}");
        }
        foreach (['products', 'stocks', 'receipts', 'dispensing', 'inventory-orders', 'reports', 'synchronization', 'configuration', 'organizations', 'missions', 'funding', 'sites', 'inventories', 'orders', 'settings', 'project_settings', 'site_settings', 'activity_logs'] as $key) {
            $this->assertFalse($projectMenu->contains($key), "Project menu must not contain {$key}");
        }

        $siteMenu = collect($navigation->mobileItems(
            $this->actor('site_admin', 'site', $site->id)
        ))->pluck('key');
        foreach (['dashboard', 'standard-lists', 'products', 'stocks', 'receipts', 'dispensing', 'inventory-orders', 'reports', 'synchronization', 'profile'] as $key) {
            $this->assertTrue($siteMenu->contains($key), "Site menu is missing {$key}");
        }
        foreach (['configuration', 'organizations', 'missions', 'projects', 'funding', 'facilities', 'sites', 'users', 'inventories', 'orders', 'settings', 'project_settings', 'site_settings', 'activity_logs', 'local_activity_logs'] as $key) {
            $this->assertFalse($siteMenu->contains($key), "Site menu must not contain {$key}");
        }

        $icons = collect(config('pharmacare_ui.module_icons'));
        foreach ([$coordination, $projectMenu, $siteMenu] as $menu) {
            $this->assertSame('dashboard', $menu->first());
            foreach ($menu as $key) {
                $iconKey = match ($key) {
                    'project_settings', 'site_settings' => 'settings',
                    'local_activity_logs' => 'activity_logs',
                    'standard-lists' => 'standard_lists',
                    'inventory-orders' => 'inventories',
                    default => $key,
                };
                $this->assertTrue($icons->has($iconKey), "Material icon is missing for {$key}");
            }
        }
    }

    public function test_sidebar_visibility_follows_effective_permissions_without_role_specific_markup(): void
    {
        [$organization] = $this->hierarchy('SIDEBAR-PERMISSION');
        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);
        $role = Role::where('code', 'coordination_admin')->firstOrFail();
        $usersPermission = $role->permissions()->where('code', 'users.view')->firstOrFail();
        $role->permissions()->detach($usersPermission->id);

        $keys = collect(app(ApplicationNavigationService::class)->items($coordination))->pluck('key');
        $this->assertFalse($keys->contains('users'));
        $this->assertFalse($keys->contains('receipts'));

        $response = $this->actingAs($coordination)->get('/dashboard')->assertOk();
        $response->assertSee('class="permission-navigation"', false)
            ->assertDontSee('href="'.route('users.index').'"', false)
            ->assertDontSee('href="'.route('modules.receipts').'"', false);

        $sidebar = file_get_contents(resource_path('views/components/app-sidebar.blade.php'));
        $this->assertStringNotContainsString('$roleCode', $sidebar);
    }

    public function test_direct_web_routes_enforce_the_same_permissions_as_navigation(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('DIRECT');

        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);
        $this->actingAs($coordination)->get('/configuration')->assertForbidden();
        $this->actingAs($coordination)->get('/receipts')->assertForbidden();

        $projectAdmin = $this->actor('project_admin', 'project', $project->id);
        $this->actingAs($projectAdmin)->get('/project-settings')->assertForbidden();
        $this->actingAs($projectAdmin)->get('/site-settings')->assertForbidden();

        $siteAdmin = $this->actor('site_admin', 'site', $site->id);
        $this->actingAs($siteAdmin)->get('/receipts')->assertOk();
        $this->actingAs($siteAdmin)->get('/organizations')->assertForbidden();
        $this->actingAs($siteAdmin)->get('/users')->assertForbidden();

        Sanctum::actingAs($siteAdmin);
        $this->postJson('/api/v1/users', [
            'name' => 'Compte interdit',
            'email' => 'forbidden.direct@example.org',
            'role_id' => Role::where('code', 'site_admin')->firstOrFail()->id,
            'scope_type' => 'site',
            'scope_id' => $site->id,
        ])->assertForbidden();
    }

    public function test_sensitive_user_and_organization_routes_are_guarded_before_controller_execution(): void
    {
        [$organization, , , $site] = $this->hierarchy('ROUTE-GUARDS');
        $siteAdmin = $this->actor('site_admin', 'site', $site->id);
        $target = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($siteAdmin)->get('/users/create')->assertForbidden();
        $this->actingAs($siteAdmin)->get('/users/'.$target->id)->assertForbidden();
        $this->actingAs($siteAdmin)->put('/users/'.$target->id, [])->assertForbidden();
        $this->actingAs($siteAdmin)->delete('/users/'.$target->id)->assertForbidden();
        $this->actingAs($siteAdmin)->post('/organizations', [])->assertForbidden();
        $this->actingAs($siteAdmin)->put('/organizations/'.$organization->id, [])->assertForbidden();

        Sanctum::actingAs($siteAdmin);
        $this->postJson('/api/v1/users', [])->assertForbidden();
    }

    public function test_shared_dashboard_is_dynamic_and_coordination_menu_keeps_required_order(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('DASH');
        $navigation = app(ApplicationNavigationService::class);

        $owner = $this->actor('sago_admin', 'platform', $organization->id);
        Sanctum::actingAs($owner);
        $ownerWidgets = collect($this->getJson('/api/v1/dashboard')->assertOk()->json('widgets'))->pluck('key');
        $this->assertTrue($ownerWidgets->contains('organizations'));
        $this->assertFalse($ownerWidgets->contains('users_active'));
        $this->assertFalse($ownerWidgets->contains('stock_quantity'));
        $this->assertTrue(collect($navigation->mobileItems($owner))->pluck('key')->contains('configuration'));

        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);
        $coordinationMenu = collect($navigation->mobileItems($coordination))->pluck('key')->values();
        $this->assertSame('dashboard', $coordinationMenu->get(0));
        $this->assertSame('missions', $coordinationMenu->get(1));
        Sanctum::actingAs($coordination);
        $coordinationWidgets = collect($this->getJson('/api/v1/dashboard')->assertOk()->json('widgets'))->pluck('key');
        $this->assertTrue($coordinationWidgets->contains('missions'));
        $this->assertTrue($coordinationWidgets->contains('projects'));
        $this->assertFalse($coordinationWidgets->contains('sites'));

        Sanctum::actingAs($this->actor('project_admin', 'project', $project->id));
        $projectWidgets = collect($this->getJson('/api/v1/dashboard')->assertOk()->json('widgets'))->pluck('key');
        $this->assertTrue($projectWidgets->contains('projects'));
        $this->assertFalse($projectWidgets->contains('facilities'));
        $this->assertFalse($projectWidgets->contains('sites'));

        Sanctum::actingAs($this->actor('site_admin', 'site', $site->id));
        $siteWidgets = collect($this->getJson('/api/v1/dashboard')->assertOk()->json('widgets'))->pluck('key');
        $this->assertTrue($siteWidgets->contains('products'));
        $this->assertTrue($siteWidgets->contains('stock_quantity'));
        $this->assertTrue($siteWidgets->contains('stockouts'));
        $this->assertFalse($siteWidgets->contains('organizations'));
        $this->assertFalse($siteWidgets->contains('users_active'));
    }

    public function test_fosa_account_api_is_available_only_to_project_admin_in_v1(): void
    {
        [$organization, $project, , $site] = $this->hierarchy('U');
        $projectRole = Role::where('code', 'project_admin')->firstOrFail();
        $siteRole = Role::where('code', 'site_admin')->firstOrFail();
        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);

        Sanctum::actingAs($coordination);
        $this->postJson('/api/v1/users', [
            'name' => 'Admin Projet Créé', 'email' => 'project.created@example.org',
            'role_id' => $projectRole->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ])->assertForbidden();
        $this->postJson('/api/v1/users', [
            'name' => 'Admin Site Créé', 'email' => 'site.by.coordination@example.org',
            'role_id' => $siteRole->id, 'scope_type' => 'site', 'scope_id' => $site->id,
        ])->assertForbidden();

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

    public function test_project_admin_can_open_fosa_user_workspace_in_v1(): void
    {
        [, $project] = $this->hierarchy('F');
        $projectAdmin = $this->actor('project_admin', 'project', $project->id);

        $this->actingAs($projectAdmin)->get('/users')->assertOk();
    }

    public function test_coordination_admin_cannot_use_hidden_user_sidebar_module_in_v1(): void
    {
        [$organization, $project, $facility, $site] = $this->hierarchy('WEB-COORD');
        $mission = $project->mission;
        $coordination = $this->actor('coordination_admin', 'organization', $organization->id);
        $projectRole = Role::where('code', 'project_admin')->firstOrFail();
        $siteRole = Role::where('code', 'site_admin')->firstOrFail();

        $this->actingAs($coordination)->get('/users')->assertForbidden();

        $this->actingAs($coordination)->post('/users', [
            'first_name' => 'Alice', 'last_name' => 'Projet', 'username' => 'alice_projet',
            'email' => 'alice.project@example.org', 'role_id' => $projectRole->id,
            'organization_id' => $organization->id, 'mission_id' => $mission->id,
            'project_id' => $project->id,
        ])->assertForbidden();

        $this->actingAs($coordination)->post('/users', [
            'first_name' => 'Brice', 'last_name' => 'Site', 'username' => 'brice_site',
            'email' => 'brice.site@example.org', 'role_id' => $siteRole->id,
            'organization_id' => $organization->id, 'mission_id' => $mission->id,
            'project_id' => $project->id, 'health_facility_id' => $facility->id,
            'dispensing_site_id' => $site->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'alice.project@example.org']);
        $this->assertDatabaseMissing('users', ['email' => 'brice.site@example.org']);
    }

    public function test_project_admin_can_create_site_account_through_fosa_user_module_in_v1(): void
    {
        [$organization, $project, $facility, $site] = $this->hierarchy('WEB-PROJECT');
        $projectAdmin = $this->actor('project_admin', 'project', $project->id);
        $siteRole = Role::where('code', 'site_admin')->firstOrFail();

        $this->actingAs($projectAdmin)->post('/users', [
            'first_name' => 'Claire', 'last_name' => 'Site', 'username' => 'claire_site',
            'email' => 'claire.site@example.org', 'role_id' => $siteRole->id,
            'organization_id' => $organization->id, 'mission_id' => $project->mission_id,
            'project_id' => $project->id, 'health_facility_id' => $facility->id,
            'dispensing_site_id' => $site->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'claire.site@example.org']);
    }

    public function test_fosa_user_module_hides_out_of_scope_site_account_creation_in_v1(): void
    {
        [$organizationA, $projectA] = $this->hierarchy('WEB-CHAIN-A');
        [$organizationB, $projectB, $facilityB, $siteB] = $this->hierarchy('WEB-CHAIN-B');
        $projectAdmin = $this->actor('project_admin', 'project', $projectA->id);
        $siteRole = Role::where('code', 'site_admin')->firstOrFail();

        $this->actingAs($projectAdmin)->post('/users', [
            'first_name' => 'Hors', 'last_name' => 'Périmètre', 'username' => 'hors_perimetre',
            'email' => 'outside.chain@example.org', 'role_id' => $siteRole->id,
            'organization_id' => $organizationB->id, 'mission_id' => $projectB->mission_id,
            'project_id' => $projectB->id, 'health_facility_id' => $facilityB->id,
            'dispensing_site_id' => $siteB->id,
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'outside.chain@example.org']);
        $this->assertNotSame($organizationA->id, $organizationB->id);
    }

    private function actor(string $roleCode, string $scopeType, string $scopeId): User
    {
        $organizationId = null;
        if ($roleCode === 'coordination_admin' && $scopeType === 'organization') {
            $organizationId = $scopeId;
            $scopeType = 'mission';
            $scopeId = Mission::where('organization_id', $organizationId)->value('id') ?? $scopeId;
        }
        $user = User::factory()->create(['is_active' => true, 'organization_id' => $organizationId]);
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
