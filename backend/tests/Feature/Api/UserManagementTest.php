<?php
namespace Tests\Feature\Api;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class UserManagementTest extends TestCase {
    use RefreshDatabase;
    private function administrator(): User {
        $permission=Permission::create(['code'=>'users.manage','name'=>'Gérer les utilisateurs']);
        $view=Permission::create(['code'=>'users.view','name'=>'Consulter les utilisateurs']);
        $role=Role::create(['code'=>'admin','name'=>'Administrateur']); $role->permissions()->attach([$permission->id,$view->id]);
        $user=User::factory()->create(['is_active'=>true]); $user->roles()->attach($role->id,['scope_type'=>'platform']);
        return $user;
    }
    public function test_authorized_user_can_create_and_update_a_user(): void {
        Sanctum::actingAs($this->administrator());
        $created=$this->postJson('/api/v1/users',['name'=>'Marie Test','email'=>'marie@example.org'])->assertCreated()->assertJsonStructure(['user','temporary_password']);
        $user=User::where('email','marie@example.org')->firstOrFail();
        $this->putJson('/api/v1/users/'.$user->id,['is_active'=>false])->assertOk()->assertJsonPath('user.is_active',false);
        $this->assertDatabaseHas('audit_logs',['event'=>'user.created','auditable_id'=>(string)$user->id]);
    }
    public function test_user_without_permission_is_forbidden(): void {
        Sanctum::actingAs(User::factory()->create(['is_active'=>true]));
        $this->getJson('/api/v1/users')->assertForbidden();
    }
    public function test_user_can_change_password(): void {
        $user=User::factory()->create(['password'=>'OldPassword@123','is_active'=>true,'must_change_password'=>true]); Sanctum::actingAs($user);
        $this->putJson('/api/v1/auth/password',['current_password'=>'OldPassword@123','password'=>'NewPassword@123','password_confirmation'=>'NewPassword@123'])->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
