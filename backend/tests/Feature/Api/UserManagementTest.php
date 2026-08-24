<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $permission = Permission::create(['code' => 'users.manage', 'name' => 'Gérer les utilisateurs']);
        $view = Permission::create(['code' => 'users.view', 'name' => 'Consulter les utilisateurs']);
        $role = Role::create(['code' => 'admin', 'name' => 'Administrateur']);
        $role->permissions()->attach([$permission->id, $view->id]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id, ['scope_type' => 'platform']);

        return $user;
    }

    public function test_authorized_user_can_create_and_update_a_user(): void
    {
        Sanctum::actingAs($this->administrator());
        $role = Role::create(['code' => 'api_member', 'name' => 'Membre API']);
        $created = $this->postJson('/api/v1/users', [
            'name' => 'Marie Test',
            'email' => 'marie@example.org',
            'role_id' => $role->id,
            'scope_type' => 'platform',
        ])->assertCreated()->assertJsonStructure(['user', 'temporary_password']);
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
        $role = Role::create(['code' => 'pharmacist', 'name' => 'Pharmacien']);
        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('Création d’un utilisateur');
        $this->actingAs($admin)->post('/users', [
            'first_name' => 'Pharmacienne', 'last_name' => 'Test', 'username' => 'pharmacienne_test',
            'email' => 'pharmacienne@example.org', 'role_id' => $role->id,
            'scope' => 'platform', 'password' => 'SecurePassword@2026', 'password_confirmation' => 'SecurePassword@2026',
        ])->assertRedirect('/users')->assertSessionHas('success');
        $user = User::where('email', 'pharmacienne@example.org')->firstOrFail();
        $this->assertSame('pharmacienne_test', $user->username);
        $this->assertSame('Pharmacienne', $user->first_name);
        $this->assertTrue(Hash::check('SecurePassword@2026', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertSame('pharmacist', $user->roles()->firstOrFail()->code);
    }

    public function test_dedicated_creation_page_displays_validation_errors(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)->from('/users/create')->post('/users', [
            'first_name' => '', 'last_name' => '', 'username' => 'invalid username', 'email' => 'incorrect',
            'role_id' => '', 'scope' => 'platform',
        ])->assertRedirect('/users/create')->assertSessionHasErrors(['first_name', 'last_name', 'username', 'email', 'role_id']);
    }

    public function test_user_creation_uses_professional_form_sheet_from_user_list(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->get('/users')
            ->assertOk()
            ->assertSee('user-create-sheet')
            ->assertSee('Créer un utilisateur')
            ->assertSee('Rôle et niveau d’accès')
            ->assertSee('data-password-toggle', false);

        $this->actingAs($admin)->from('/users')->post('/users', [])
            ->assertRedirect('/users')
            ->assertSessionHasErrors(['first_name', 'last_name', 'username', 'email', 'role_id']);
    }

    public function test_admin_can_view_edit_and_archive_another_user_but_not_self(): void
    {
        $admin = $this->administrator();
        $role = Role::create(['code' => 'clinician', 'name' => 'Clinicien']);
        $target = User::factory()->create(['is_active' => true]);
        $target->roles()->attach($role->id, ['scope_type' => 'platform']);
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
        $role = Role::create(['code' => 'supervisor', 'name' => 'Superviseur']);
        $target = User::factory()->create(['name' => 'Utilisateur archivé', 'is_active' => true]);
        $target->roles()->attach($role->id, ['scope_type' => 'platform']);

        $this->actingAs($admin)->delete('/users/'.$target->id)->assertRedirect('/users');
        $this->actingAs($admin)->get('/users/archived')->assertOk()->assertSee($target->email);
        $this->actingAs($admin)->get('/users/archived/'.$target->id)->assertOk()->assertSee($target->email);
        $this->actingAs($admin)->post('/users/archived/'.$target->id.'/restore')->assertRedirect('/users');

        $restored = User::findOrFail($target->id);
        $this->assertTrue($restored->is_active);
        $this->assertSame('supervisor', $restored->roles()->firstOrFail()->code);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.restored', 'auditable_id' => (string) $target->id]);
        $this->actingAs($admin)->get('/users')->assertOk()->assertSee($target->email);
    }

    public function test_api_archive_and_restore_preserve_user_and_history(): void
    {
        $admin = $this->administrator();
        $target = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/users/'.$target->id)->assertNoContent();
        $this->getJson('/api/v1/users/archived')->assertOk()->assertJsonFragment(['email' => $target->email]);
        $this->getJson('/api/v1/users/archived/'.$target->id)->assertOk()->assertJsonPath('user.email', $target->email);
        $this->postJson('/api/v1/users/archived/'.$target->id.'/restore')->assertOk()->assertJsonPath('user.is_active', true);

        $this->assertNotSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.archived', 'auditable_id' => (string) $target->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.restored', 'auditable_id' => (string) $target->id]);
    }

    public function test_organization_admin_cannot_access_users_outside_its_scope(): void
    {
        $manage = Permission::create(['code' => 'users.manage', 'name' => 'Gérer les utilisateurs']);
        $view = Permission::create(['code' => 'users.view', 'name' => 'Consulter les utilisateurs']);
        $adminRole = Role::create(['code' => 'organization_admin', 'name' => 'Administrateur organisation', 'is_system' => true]);
        $adminRole->permissions()->attach([$manage->id, $view->id]);
        $memberRole = Role::create(['code' => 'clinician', 'name' => 'Clinicien', 'is_system' => true]);
        $organizationA = Organization::create(['code' => 'ORG-A', 'name' => 'Organisation A']);
        $organizationB = Organization::create(['code' => 'ORG-B', 'name' => 'Organisation B']);
        $admin = User::factory()->create(['is_active' => true]);
        $inside = User::factory()->create(['is_active' => true]);
        $outside = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach($adminRole->id, ['scope_type' => 'organization', 'scope_id' => $organizationA->id]);
        $inside->roles()->attach($memberRole->id, ['scope_type' => 'organization', 'scope_id' => $organizationA->id]);
        $outside->roles()->attach($memberRole->id, ['scope_type' => 'organization', 'scope_id' => $organizationB->id]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users')->assertOk()->assertJsonFragment(['email' => $inside->email])->assertJsonMissing(['email' => $outside->email]);
        $this->getJson('/api/v1/users/'.$outside->id)->assertNotFound();
        $this->deleteJson('/api/v1/users/'.$outside->id)->assertNotFound();
        $this->assertNotSoftDeleted('users', ['id' => $outside->id]);
    }
}
