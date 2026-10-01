<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationNavigationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1ModuleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_v1_navigation_is_exact_for_coordination_and_project_admin(): void
    {
        $navigation = app(ApplicationNavigationService::class);
        $coordination = $this->actor('coordination_admin', 'mission');
        $project = $this->actor('project_admin', 'project');

        $coordinationItems = collect($navigation->items($coordination));
        $this->assertSame(['dashboard', 'missions', 'projects', 'standard-lists', 'profile'], $coordinationItems->pluck('key')->all());
        $this->assertSame(['Tableau de bord', 'Ma Coordination', 'Configuration des projets', 'Liste standard du projet', 'Mon profil'], $coordinationItems->pluck('label')->all());
        $this->assertSame('/projects', $coordinationItems->firstWhere('key', 'projects')['path']);

        $projectItems = collect($navigation->items($project));
        $this->assertSame(['dashboard', 'projects', 'facilities', 'users', 'standard-lists', 'profile'], $projectItems->pluck('key')->all());
        $this->assertSame(['Tableau de bord', 'Projet & FOSA', 'FOSA', 'Équipe FOSA', 'Liste standard', 'Mon profil'], $projectItems->pluck('label')->all());
        // Menu V1 (Q-3) : 4 entrées ; FOSA et Comptes deviennent des onglets de « Projet & FOSA ».
        $this->assertSame(['dashboard', 'projects', 'standard-lists', 'profile'], $projectItems->where('menu', true)->pluck('key')->values()->all());
    }

    public function test_hidden_v1_modules_are_refused_without_being_removed(): void
    {
        $coordination = $this->actor('coordination_admin', 'mission');
        foreach (['/health-facilities', '/dispensing-sites', '/users', '/products', '/stocks', '/receipts', '/dispensations', '/inventories', '/orders', '/reports', '/synchronization'] as $path) {
            $this->actingAs($coordination)->get($path)->assertForbidden();
        }

        $project = $this->actor('project_admin', 'project');
        foreach (['/missions', '/products', '/stocks', '/receipts', '/dispensations', '/inventories', '/orders', '/reports', '/synchronization'] as $path) {
            $this->actingAs($project)->get($path)->assertForbidden();
        }

        $this->assertTrue(app('router')->has('modules.stocks'));
        $this->assertTrue(app('router')->has('modules.reports'));
    }

    public function test_hidden_v1_api_modules_are_refused_even_when_rbac_permission_exists(): void
    {
        $coordination = $this->actor('coordination_admin', 'mission');
        Sanctum::actingAs($coordination);
        foreach (['/api/v1/users', '/api/v1/organizations/'.$coordination->id.'/stocks'] as $path) {
            $this->getJson($path)
                ->assertForbidden()
                ->assertJsonPath('code', 'module_not_available_v1');
        }
        // Formulaire « Créer un compte » de Ma Coordination : rôles proposés seulement.
        $codes = collect($this->getJson('/api/v1/assignable-roles')->assertOk()->json('roles'))->pluck('code')->sort()->values()->all();
        $this->assertSame(['coordination_admin', 'project_admin'], $codes);

        $project = $this->actor('project_admin', 'project');
        Sanctum::actingAs($project);
        $this->getJson('/api/v1/missions')
            ->assertForbidden()
            ->assertJsonPath('code', 'module_not_available_v1');
    }

    private function actor(string $role, string $scope): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scope, 'scope_id' => $user->id]);
        return $user;
    }
}
