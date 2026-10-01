<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\UserScopeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Configuration Mission, rubrique Comptes : la Coordination crée des Admins
 * Projet et des Admins Coordination en lecture seule (appliquée côté backend).
 */
class ReadOnlyCoordinationAccountTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private Project $project;

    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->mission = Mission::create([
            'organization_id' => $this->organization->id,
            'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => 'MAROUA', 'name' => 'Coordination Maroua', 'is_active' => true,
        ]);
        $this->project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'VIH', 'name' => 'Projet VIH']);
        $this->coordination = $this->coordinationUser();
    }

    private function coordinationUser(bool $readOnly = false): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false, 'read_only' => $readOnly]);
        $user->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $this->mission->id]);

        return $user;
    }

    private function account(string $roleCode, array $extra = []): array
    {
        return [
            'first_name' => 'Awa', 'last_name' => 'Ndiaye', 'username' => 'andiaye', 'email' => 'awa@example.test',
            'role_id' => Role::where('code', $roleCode)->value('id'),
            'organization_id' => $this->organization->id, 'mission_id' => $this->mission->id,
            'form_context' => 'mission-project-admin',
            ...$extra,
        ];
    }

    public function test_coordination_is_offered_project_admin_and_read_only_coordination_but_not_facility_roles(): void
    {
        $codes = app(UserScopeService::class)->assignableRoles($this->coordination)->pluck('code')->sort()->values()->all();

        $this->assertSame(['coordination_admin', 'project_admin'], $codes);
    }

    public function test_coordination_creates_a_read_only_coordination_account_for_its_own_coordination(): void
    {
        $this->actingAs($this->coordination)->post('/users', $this->account('coordination_admin'))->assertRedirect();

        $created = User::where('email', 'awa@example.test')->firstOrFail();
        $this->assertTrue($created->read_only);
        $this->assertTrue($created->must_change_password);
        $this->assertDatabaseHas('role_user', ['user_id' => $created->id, 'scope_type' => 'mission', 'scope_id' => $this->mission->id]);
    }

    public function test_project_admin_account_requires_a_project_of_the_coordination(): void
    {
        $this->actingAs($this->coordination)->post('/users', $this->account('project_admin'))->assertSessionHasErrors('project_id');
        $this->post('/users', $this->account('project_admin', ['project_id' => $this->project->id]))->assertRedirect();

        $created = User::where('email', 'awa@example.test')->firstOrFail();
        $this->assertFalse($created->read_only);
        $this->assertDatabaseHas('role_user', ['user_id' => $created->id, 'scope_type' => 'project', 'scope_id' => $this->project->id]);
    }

    public function test_coordination_cannot_create_a_facility_account(): void
    {
        $response = $this->actingAs($this->coordination)->post('/users', $this->account('site_admin', ['project_id' => $this->project->id]));

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseMissing('users', ['email' => 'awa@example.test']);
    }

    public function test_account_form_lists_the_roles_and_the_coordination_projects(): void
    {
        $other = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'PALU', 'name' => 'Projet Paludisme']);

        $this->actingAs($this->coordination)->get(route('organizations.missions.show', [$this->organization, $this->mission]))
            ->assertOk()
            ->assertSee('Créer un compte')
            ->assertSee('Admin Coordination (lecture seule)')
            ->assertSee('Projet VIH (VIH)')
            ->assertSee('Projet Paludisme (PALU)')
            ->assertDontSee('Admin Site de Dispensation');
        $this->assertNotNull($other);
    }

    public function test_read_only_account_sees_but_cannot_change_anything(): void
    {
        $reader = $this->coordinationUser(readOnly: true);

        $this->assertTrue($reader->hasPermission('projects.view'));
        $this->assertFalse($reader->hasPermission('projects.manage'));
        $this->assertFalse($reader->hasPermission('users.manage'));
        $this->assertSame([], app(\App\Services\GovernanceService::class)->assignableCodes($reader));

        // Web : lecture autorisée, toute écriture refusée, même sur une route existante.
        $this->actingAs($reader)->get(route('organizations.missions.show', [$this->organization, $this->mission]))
            ->assertOk()->assertDontSee('Créer un compte');
        $this->put(route('organizations.projects.update', [$this->organization, $this->project]), ['name' => 'Renommé', 'code' => 'VIH', 'mission_id' => $this->mission->id])
            ->assertForbidden();
        $this->post('/users', $this->account('project_admin', ['project_id' => $this->project->id]))->assertForbidden();
        $this->assertSame('Projet VIH', $this->project->fresh()->name);

        // Gestion de son propre compte : autorisée.
        $this->post('/profile/locale', ['locale' => 'en'])->assertRedirect();
    }

    public function test_read_only_account_is_refused_on_the_api_and_exposes_its_flag(): void
    {
        $reader = $this->coordinationUser(readOnly: true);
        Sanctum::actingAs($reader);

        $this->getJson("/api/v1/projects/{$this->project->id}")->assertOk();
        $this->putJson("/api/v1/projects/{$this->project->id}/medical-configuration", ['care_level_ids' => []])->assertForbidden()
            ->assertJsonPath('message', 'Compte en lecture seule : cette action n’est pas autorisée.');
        $this->postJson('/api/v1/users', ['name' => 'Awa', 'email' => 'awa@example.test'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'awa@example.test']);

        $me = $this->getJson('/api/v1/auth/me')->assertOk();
        $this->assertTrue($me->json('read_only') ?? $me->json('user.read_only'));
        $permissions = collect($me->json('permissions') ?? $me->json('user.permissions'));
        $this->assertTrue($permissions->contains('projects.view'));
        $this->assertFalse($permissions->contains(fn ($code) => ! str_ends_with($code, '.view')));
    }
}
