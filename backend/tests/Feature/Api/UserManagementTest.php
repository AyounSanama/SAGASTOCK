<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnforceV1ModuleAvailability;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gestion des comptes par un rôle officiel : l'Admin Coordination gère les
 * Admin Projet (l'Admin Sago n'a aucune permission « users », il crée les
 * Coordinations par la configuration des organisations). Depuis le 01/10,
 * les anciens rôles personnalisés ne créent ni ne modifient plus de comptes
 * (voir AccountHierarchyTest).
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        // Écrans « Utilisateurs » génériques : masqués en V1 (code conservé, remplacés
        // par « Ma Coordination » et l'onglet Comptes). Le masquage est testé ailleurs
        // (V1AdminProjectStructureAndMaskingTest, RealTokenRoleMatrixTest) ; ici on
        // vérifie la logique conservée avec des rôles officiels.
        $this->withoutMiddleware(EnforceV1ModuleAvailability::class);
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ORG-UI', 'name' => 'Médecins du Monde']);
        $this->mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->value('id'), 'code' => 'MISSION-UI', 'name' => 'Mission Cameroun', 'is_active' => true]);
        $this->project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'PROJ-UI', 'name' => 'Projet Santé Mère-Enfant']);
    }

    private function administrator(): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $this->mission->id]);

        return $user;
    }

    private function projectAdmin(array $attributes = []): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, ...$attributes]);
        $user->roles()->attach(Role::where('code', 'project_admin')->firstOrFail(), ['scope_type' => 'project', 'scope_id' => $this->project->id]);

        return $user;
    }

    public function test_authorized_user_can_create_and_update_a_user(): void
    {
        Sanctum::actingAs($this->administrator());
        $this->postJson('/api/v1/users', [
            'name' => 'Marie Test',
            'email' => 'marie@example.org',
            'role_id' => Role::where('code', 'project_admin')->value('id'),
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ])->assertCreated()
            ->assertJsonStructure(['user', 'message'])
            ->assertJsonMissingPath('temporary_password')
            ->assertJsonMissingPath('password');
        $user = User::where('email', 'marie@example.org')->firstOrFail();
        $this->putJson('/api/v1/users/'.$user->id, ['is_active' => false])->assertOk()->assertJsonPath('user.is_active', false);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created', 'auditable_id' => (string) $user->id]);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true]));
        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword@123', 'is_active' => true, 'must_change_password' => true]);
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/auth/password', ['current_password' => 'OldPassword@123', 'password' => 'NewPassword@123', 'password_confirmation' => 'NewPassword@123'])->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_web_admin_can_create_user_with_selected_role_and_password(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('Création d’un utilisateur');
        $this->actingAs($admin)->post('/users', [
            'first_name' => 'Pharmacienne', 'last_name' => 'Test', 'username' => 'pharmacienne_test',
            'email' => 'pharmacienne@example.org', 'role_id' => Role::where('code', 'project_admin')->value('id'),
            'organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'project_id' => $this->project->id,
            'password' => 'SecurePassword@2026', 'password_confirmation' => 'SecurePassword@2026',
        ])->assertRedirect('/users')->assertSessionHas('success');
        $user = User::where('email', 'pharmacienne@example.org')->firstOrFail();
        $this->assertSame('pharmacienne_test', $user->username);
        $this->assertSame('Pharmacienne', $user->first_name);
        $this->assertTrue(Hash::check('SecurePassword@2026', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertSame('project_admin', $user->roles()->firstOrFail()->code);
    }

    public function test_dedicated_creation_page_displays_validation_errors(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)->from('/users/create')->post('/users', [
            'first_name' => '', 'last_name' => '', 'username' => 'invalid username', 'email' => 'incorrect',
            'role_id' => '',
        ])->assertRedirect('/users/create')->assertSessionHasErrors(['first_name', 'last_name', 'username', 'email', 'role_id']);
    }

    public function test_user_creation_uses_professional_form_sheet_from_user_list(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->get('/users')
            ->assertOk()
            ->assertSee('user-create-sheet')
            ->assertSee('data-password-toggle', false);

        $this->actingAs($admin)->from('/users')->post('/users', [])
            ->assertRedirect('/users')
            ->assertSessionHasErrors(['first_name', 'last_name', 'username', 'email', 'role_id']);
    }

    public function test_admin_can_view_edit_and_archive_another_user_but_not_self(): void
    {
        $admin = $this->administrator();
        $target = $this->projectAdmin();
        $this->actingAs($admin)->get('/users/'.$target->id)->assertOk()->assertSee($target->email);
        $this->actingAs($admin)->get('/users/'.$target->id.'/edit')
            ->assertOk()
            ->assertSee('Modifier')
            ->assertDontSee('data-global-back', false)
            ->assertSee('Logo PharmaCare');
        $this->actingAs($admin)->delete('/users/'.$target->id)->assertRedirect('/users');
        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.archived', 'auditable_id' => (string) $target->id]);
        $this->actingAs($admin)->delete('/users/'.$admin->id)->assertUnprocessable();
    }

    public function test_archived_user_can_be_listed_viewed_and_restored_without_data_loss(): void
    {
        $admin = $this->administrator();
        $target = $this->projectAdmin(['name' => 'Utilisateur archivé']);

        $this->actingAs($admin)->delete('/users/'.$target->id)->assertRedirect('/users');
        $this->actingAs($admin)->get('/users/archived')->assertOk()->assertSee($target->email);
        $this->actingAs($admin)->get('/users/archived/'.$target->id)->assertOk()->assertSee($target->email);
        $this->actingAs($admin)->post('/users/archived/'.$target->id.'/restore')->assertRedirect('/users');

        $restored = User::findOrFail($target->id);
        $this->assertTrue($restored->is_active);
        $this->assertSame('project_admin', $restored->roles()->firstOrFail()->code);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.restored', 'auditable_id' => (string) $target->id]);
        $this->actingAs($admin)->get('/users')->assertOk()->assertSee($target->email);
    }

    public function test_api_archive_and_restore_preserve_user_and_history(): void
    {
        $admin = $this->administrator();
        $target = $this->projectAdmin();
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/users/'.$target->id)->assertNoContent();
        $this->getJson('/api/v1/users/archived')->assertOk()->assertJsonFragment(['email' => $target->email]);
        $this->getJson('/api/v1/users/archived/'.$target->id)->assertOk()->assertJsonPath('user.email', $target->email);
        $this->postJson('/api/v1/users/archived/'.$target->id.'/restore')->assertOk()->assertJsonPath('user.is_active', true);

        $this->assertNotSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.archived', 'auditable_id' => (string) $target->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.restored', 'auditable_id' => (string) $target->id]);
    }

    public function test_project_admin_cannot_access_users_of_another_organization(): void
    {
        $inside = $this->projectAdmin();
        $other = Organization::create(['code' => 'ORG-B', 'name' => 'Organisation B']);
        $otherMission = Mission::create(['organization_id' => $other->id, 'country_id' => Country::where('iso2', 'CM')->value('id'), 'code' => 'MB', 'name' => 'Mission B']);
        $otherProject = Project::create(['organization_id' => $other->id, 'mission_id' => $otherMission->id, 'code' => 'PB', 'name' => 'Projet B']);
        $outside = User::factory()->create(['organization_id' => $other->id, 'is_active' => true]);
        $outside->roles()->attach(Role::where('code', 'project_admin')->firstOrFail(), ['scope_type' => 'project', 'scope_id' => $otherProject->id]);
        Sanctum::actingAs($this->projectAdmin());

        $this->getJson('/api/v1/users')->assertOk()->assertJsonFragment(['email' => $inside->email])->assertJsonMissing(['email' => $outside->email]);
        $this->getJson('/api/v1/users/'.$outside->id)->assertNotFound();
        $this->deleteJson('/api/v1/users/'.$outside->id)->assertNotFound();
        $this->assertNotSoftDeleted('users', ['id' => $outside->id]);
    }

    public function test_user_sheet_groups_real_permissions_and_hides_technical_scope_id(): void
    {
        $admin = $this->administrator();
        $target = $this->projectAdmin();

        $this->actingAs($admin)->get('/users/'.$target->id)->assertOk()
            ->assertSee('Admin Projet')
            ->assertSee('Projet Santé Mère-Enfant')
            ->assertSee('Médecins du Monde')
            ->assertSee('Utilisateurs & accès')
            ->assertSee('permissions')
            ->assertDontSee($this->project->id);
    }
}
