<?php
namespace Tests\Feature\Api;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class SecurityAdministrationTest extends TestCase {use RefreshDatabase;
 private function admin():User{$manage=Permission::create(['code'=>'roles.manage','name'=>'Gérer les rôles']);$audit=Permission::create(['code'=>'audit.view','name'=>'Voir audit']);$role=Role::create(['code'=>'security_admin','name'=>'Sécurité']);$role->permissions()->attach([$manage->id,$audit->id]);$user=User::factory()->create();$user->roles()->attach($role->id,['scope_type'=>'platform']);return $user;}
 public function test_authorized_admin_can_manage_custom_roles():void{Sanctum::actingAs($this->admin());$permission=Permission::create(['code'=>'stocks.view','name'=>'Voir stocks']);$created=$this->postJson('/api/v1/security/roles',['code'=>'stock_reader','name'=>'Lecteur stock','permission_ids'=>[$permission->id]])->assertCreated();$id=$created->json('role.id');$this->putJson('/api/v1/security/roles/'.$id,['name'=>'Consultation stock','permission_ids'=>[$permission->id]])->assertOk();$this->deleteJson('/api/v1/security/roles/'.$id)->assertNoContent();}
 public function test_system_role_cannot_be_deleted():void{Sanctum::actingAs($this->admin());$system=Role::create(['code'=>'platform_owner','name'=>'Propriétaire','is_system'=>true]);$this->deleteJson('/api/v1/security/roles/'.$system->id)->assertUnprocessable();}
 public function test_audit_requires_permission_and_can_be_filtered():void{$admin=$this->admin();AuditLog::create(['user_id'=>$admin->id,'event'=>'role.created']);Sanctum::actingAs($admin);$this->getJson('/api/v1/security/audits?event=role')->assertOk()->assertJsonCount(1,'data');Sanctum::actingAs(User::factory()->create());$this->getJson('/api/v1/security/audits')->assertForbidden();}
}
