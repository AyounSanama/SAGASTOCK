<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\SetupProgress;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Organization;
use App\Models\HealthFacility;
use App\Models\Site;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_read_profile_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'Secret@123', 'is_active' => true]);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Secret@123', 'device_name' => 'Test Android',
            'device_id' => '123e4567-e89b-12d3-a456-426614174000', 'platform' => 'android',
        ])->assertOk()->assertJsonStructure([
            'token',
            'user' => [
                'id', 'name', 'email', 'roles', 'role', 'permissions',
                'navigation', 'access_scope' => [
                    'platform', 'organization_ids', 'project_ids',
                    'facility_ids', 'site_ids',
                ],
            ],
        ]);
        $token = $login->json('token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.email', $user->email);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => 'Secret@123', 'is_active' => false]);
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Secret@123', 'device_name' => 'Test Android',
            'device_id' => '123e4567-e89b-12d3-a456-426614174000', 'platform' => 'android',
        ])->assertUnprocessable();
    }

    public function test_web_login_redirects_every_active_user_to_independent_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'Secret@123', 'is_active' => true, 'must_change_password' => false]);
        $this->post('/login', ['login' => $user->email, 'password' => 'Secret@123'])
            ->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('Vue d’ensemble')
            ->assertSee('PharmaCare');
    }

    public function test_web_login_still_redirects_to_shared_dashboard_after_configuration_completion(): void
    {
        $user = User::factory()->create([
            'password' => 'Secret@123',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        SetupProgress::current()->update([
            'completed_steps' => range(1, 11),
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);

        $this->post('/login', ['login' => $user->email, 'password' => 'Secret@123'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_coordination_admin_enters_dashboard_after_web_login(): void
    {
        $permission = Permission::firstOrCreate([
            'code' => 'configuration.view',
        ], [
            'name' => 'Voir la configuration',
            'module' => 'configuration',
        ]);
        $role = Role::firstOrCreate([
            'code' => 'coordination_admin',
        ], [
            'name' => 'Admin Coordination',
            'is_system' => true,
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create([
            'password' => 'Secret@123',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->roles()->attach($role, ['scope_type' => 'platform']);

        $this->post('/login', ['login' => $user->email, 'password' => 'Secret@123'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_switching_accounts_on_same_device_revokes_previous_identity_and_returns_fresh_navigation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::create(['code' => 'SESSION', 'name' => 'Organisation session']);
        $facility = HealthFacility::create(['organization_id' => $organization->id, 'code' => 'F', 'name' => 'Centre', 'facility_type' => 'clinic']);
        $site = Site::create(['organization_id' => $organization->id, 'health_facility_id' => $facility->id, 'code' => 'S', 'name' => 'Site', 'site_type' => 'dispensing']);
        $coordination = User::factory()->create(['password' => 'Secret@123', 'is_active' => true, 'organization_id' => $organization->id]);
        $coordination->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'organization', 'scope_id' => $organization->id]);
        $siteAdmin = User::factory()->create(['password' => 'Secret@123', 'is_active' => true, 'organization_id' => $organization->id]);
        $siteAdmin->roles()->attach(Role::where('code', 'site_admin')->firstOrFail(), ['scope_type' => 'site', 'scope_id' => $site->id]);
        $deviceId = '123e4567-e89b-12d3-a456-426614174999';

        $firstToken = $this->postJson('/api/v1/auth/login', [
            'email' => $coordination->email, 'password' => 'Secret@123', 'device_name' => 'Téléphone partagé',
            'device_id' => $deviceId, 'platform' => 'android',
        ])->assertOk()->assertJsonPath('user.role', 'coordination_admin')
            ->assertJsonFragment(['key' => 'users'])->json('token');

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $siteAdmin->email, 'password' => 'Secret@123', 'device_name' => 'Téléphone partagé',
            'device_id' => $deviceId, 'platform' => 'android',
        ])->assertOk()->assertJsonPath('user.role', 'site_admin')
            ->assertJsonMissing(['key' => 'users']);

        $this->withToken($firstToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken($second->json('token'))->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('user.role', 'site_admin')
            ->assertJsonMissing(['key' => 'users']);
        $this->assertDatabaseHas('devices', ['fingerprint' => $deviceId, 'user_id' => $siteAdmin->id]);
        $this->assertDatabaseMissing('devices', ['fingerprint' => $deviceId, 'user_id' => $coordination->id]);
    }
}
