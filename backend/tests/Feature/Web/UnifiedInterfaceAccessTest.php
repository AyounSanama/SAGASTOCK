<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationNavigationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedInterfaceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_official_roles_share_one_layout_but_receive_permission_filtered_menus(): void
    {
        $owner = $this->userWithRole('sago_admin', 'platform');
        $coordination = $this->userWithRole('coordination_admin', 'organization');
        $project = $this->userWithRole('project_admin', 'project');
        $site = $this->userWithRole('site_admin', 'site');
        $navigation = app(ApplicationNavigationService::class);

        $ownerKeys = collect($navigation->items($owner))->pluck('key');
        $coordinationKeys = collect($navigation->items($coordination))->pluck('key')->all();
        $projectKeys = collect($navigation->items($project))->pluck('key')->all();
        $siteKeys = collect($navigation->items($site))->pluck('key')->all();

        $this->assertContains('configuration', $ownerKeys);
        $this->assertNotContains('stocks', $ownerKeys);
        $this->assertNotContains('configuration', $coordinationKeys);
        $this->assertNotContains('organizations', $coordinationKeys);
        $this->assertNotContains('configuration', $projectKeys);
        $this->assertNotContains('stocks', $projectKeys);
        $this->assertNotContains('configuration', $siteKeys);
        $this->assertContains('stocks', $siteKeys);
        $this->assertContains('receipts', $siteKeys);
    }

    public function test_mission_route_remains_available_without_being_duplicated_in_navigation(): void
    {
        $user = $this->userWithRole('sago_admin', 'platform');
        $navigation = app(ApplicationNavigationService::class);

        $items = $navigation->items($user);

        $this->assertContains('dashboard', collect($items)->pluck('key')->all());
        $this->assertNotContains('missions', collect($items)->pluck('key')->all());
        $this->assertTrue(app('router')->has('modules.missions'));
    }

    public function test_direct_configuration_url_is_forbidden_to_site_admin(): void
    {
        $site = $this->userWithRole('site_admin', 'site');

        $this->actingAs($site)->get('/configuration')->assertForbidden();
    }

    public function test_logout_invalidates_session_and_browser_back_cannot_restore_it(): void
    {
        $user = $this->userWithRole('site_admin', 'site');

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    private function userWithRole(string $code, string $scope): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $role = Role::where('code', $code)->firstOrFail();
        $user->roles()->attach($role->id, [
            'scope_type' => $scope,
            'scope_id' => null,
        ]);

        return $user;
    }
}
