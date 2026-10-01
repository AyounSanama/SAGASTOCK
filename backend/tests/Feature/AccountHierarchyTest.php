<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Correction validée le 01/10 : seuls les rôles officiels gèrent des comptes,
 * uniquement des rôles placés sous le leur, et jamais un compte FOSA à la plateforme.
 */
class AccountHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private Project $project;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->value('id'), 'code' => 'M', 'name' => 'Mission', 'is_active' => true]);
        $this->project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'P', 'name' => 'Projet']);
        $facility = HealthFacility::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'F', 'name' => 'FOSA', 'facility_type' => 'health_center']);
        $facility->projects()->attach($this->project);
        $this->site = Site::create(['organization_id' => $this->organization->id, 'health_facility_id' => $facility->id, 'code' => 'S', 'name' => 'Site', 'site_type' => 'stock_and_dispensing']);
    }

    private function user(string $role, string $scopeType, ?string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function legacyPlatformAdmin(): User
    {
        $role = Role::create(['code' => 'admin', 'name' => 'Administrateur']);
        $role->permissions()->attach(Permission::whereIn('code', ['users.view', 'users.manage'])->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role, ['scope_type' => 'platform']);

        return $user;
    }

    private function roleId(string $code): int
    {
        return Role::where('code', $code)->value('id');
    }

    public function test_a_non_official_account_can_neither_create_nor_modify_accounts(): void
    {
        $legacy = $this->legacyPlatformAdmin();
        $target = $this->user('project_admin', 'project', $this->project->id);
        Sanctum::actingAs($legacy);

        foreach (['sago_admin', 'coordination_admin', 'site_user'] as $code) {
            $this->postJson('/api/v1/users', ['name' => 'X', 'email' => "$code@example.test", 'role_id' => $this->roleId($code), 'scope_type' => 'platform'])->assertForbidden();
        }
        $this->getJson('/api/v1/assignable-roles')->assertOk()->assertJsonCount(0, 'roles');
        $this->putJson("/api/v1/users/{$target->id}", ['name' => 'Modifié'])->assertForbidden();
        $this->postJson("/api/v1/users/{$target->id}/reset-password")->assertForbidden();
        $this->deleteJson("/api/v1/users/{$target->id}")->assertForbidden();
        $this->assertNotSoftDeleted('users', ['id' => $target->id]);
        $this->assertSame(0, User::whereIn('email', ['sago_admin@example.test', 'coordination_admin@example.test', 'site_user@example.test'])->count());

        // Web : même refus.
        $this->actingAs($legacy)->post('/users', [
            'first_name' => 'A', 'last_name' => 'B', 'username' => 'ab', 'email' => 'ab@example.test',
            'role_id' => $this->roleId('site_user'), 'scope' => 'platform',
        ])->assertStatus(404);
        $this->actingAs($legacy)->delete("/users/{$target->id}")->assertForbidden();
    }

    public function test_site_accounts_never_receive_a_platform_scope(): void
    {
        $governance = app(\App\Services\GovernanceService::class);
        $projectAdmin = $this->user('project_admin', 'project', $this->project->id);
        foreach (['site_admin', 'site_user'] as $code) {
            $role = Role::where('code', $code)->firstOrFail();
            $this->assertFalse($governance->canAssign($projectAdmin, $role, 'platform', null));
            $this->assertFalse($governance->canAssign($projectAdmin, $role, 'project', $this->project->id));
        }
        Sanctum::actingAs($projectAdmin);
        $this->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.test', 'role_id' => $this->roleId('site_user'), 'scope_type' => 'platform'])->assertForbidden();
    }

    public function test_nobody_assigns_a_role_above_their_own(): void
    {
        $cases = [
            ['project_admin', 'project', $this->project->id, ['coordination_admin', 'sago_admin', 'project_admin']],
            ['site_admin', 'site', $this->site->id, ['site_admin', 'project_admin', 'coordination_admin', 'sago_admin']],
            ['coordination_admin', 'mission', $this->mission->id, ['sago_admin']],
        ];
        foreach ($cases as [$role, $scopeType, $scopeId, $forbidden]) {
            $actor = $this->user($role, $scopeType, $scopeId);
            Sanctum::actingAs($actor);
            foreach ($forbidden as $code) {
                $this->postJson('/api/v1/users', [
                    'name' => 'X', 'email' => "$role-$code@example.test", 'role_id' => $this->roleId($code),
                    'scope_type' => $scopeType, 'scope_id' => $scopeId,
                ])->assertForbidden();
                // Ni en se l'attribuant à soi-même.
                $this->putJson("/api/v1/users/{$actor->id}", ['role_id' => $this->roleId($code), 'scope_type' => $scopeType, 'scope_id' => $scopeId])->assertForbidden();
            }
            $this->assertSame($role, $actor->fresh()->roles()->value('code'));
        }
    }

    public function test_an_account_cannot_modify_or_archive_an_account_above_its_own(): void
    {
        $coordination = $this->user('coordination_admin', 'mission', $this->mission->id);
        $projectAdmin = $this->user('project_admin', 'project', $this->project->id);
        $siteAdmin = $this->user('site_admin', 'site', $this->site->id);
        $governance = app(\App\Services\GovernanceService::class);

        $this->assertTrue($governance->canManageUser($coordination, $projectAdmin));
        $this->assertTrue($governance->canManageUser($projectAdmin, $siteAdmin));
        $this->assertFalse($governance->canManageUser($siteAdmin, $projectAdmin));
        $this->assertFalse($governance->canManageUser($projectAdmin, $coordination));
        $this->assertFalse($governance->canManageUser($projectAdmin, $projectAdmin));

        Sanctum::actingAs($siteAdmin);
        // Hors de sa vue (404) ou refusé (403) : jamais archivé.
        $this->assertContains($this->deleteJson("/api/v1/users/{$projectAdmin->id}")->status(), [403, 404]);
        $this->assertNotSoftDeleted('users', ['id' => $projectAdmin->id]);
    }

    public function test_read_only_coordination_manages_no_account(): void
    {
        $readOnly = $this->user('coordination_admin', 'mission', $this->mission->id);
        $readOnly->update(['read_only' => true]);
        $target = $this->user('project_admin', 'project', $this->project->id);

        $this->assertFalse(app(\App\Services\GovernanceService::class)->canManageUser($readOnly->fresh(), $target));
    }
}
