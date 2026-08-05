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
        $owner = $this->userWithRole('owner', 'platform');
        $coordination = $this->userWithRole('coordination_admin', 'organization');
        $project = $this->userWithRole('project_admin', 'project');
        $site = $this->userWithRole('site_admin', 'site');
        $navigation = app(ApplicationNavigationService::class);

        $ownerKeys = collect($navigation->items($owner))->pluck('key');
        $coordinationKeys = collect($navigation->items($coordination))->pluck('key')->all();
        $projectKeys = collect($navigation->items($project))->pluck('key')->all();
        $siteKeys = collect($navigation->items($site))->pluck('key')->all();

        $this->assertNotContains('configuration', $ownerKeys);
        $this->assertNotContains('stocks', $ownerKeys);
        $this->assertContains('configuration', $coordinationKeys);
        $this->assertContains('organizations', $coordinationKeys);
        $this->assertLessThan(array_search('dashboard', $coordinationKeys, true), array_search('configuration', $coordinationKeys, true));
        $this->assertNotContains('configuration', $projectKeys);
        $this->assertContains('stocks', $projectKeys);
        $this->assertNotContains('configuration', $siteKeys);
        $this->assertContains('stocks', $siteKeys);
        $this->assertContains('receipts', $siteKeys);
    }

    public function test_navigation_items_with_required_parameters_do_not_break_menu_rendering(): void
    {
        $user = $this->userWithRole('owner', 'platform');
        $navigation = app(ApplicationNavigationService::class);

        $items = $navigation->items($user);

        $this->assertContains('dashboard', collect($items)->pluck('key')->all());
        $this->assertTrue(collect($items)->contains(
            fn(array $item) => $item['key'] === 'missions'
                && $item['url'] === route('modules.missions')
        ));
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
