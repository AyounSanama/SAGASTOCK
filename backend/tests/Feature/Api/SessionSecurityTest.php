<?php
namespace Tests\Feature\Api;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class SessionSecurityTest extends TestCase {use RefreshDatabase;
 private array $login=['device_name'=>'Android Test','device_id'=>'123e4567-e89b-12d3-a456-426614174000','platform'=>'android'];
 public function test_account_is_temporarily_locked_after_five_failures():void{$user=User::factory()->create(['password'=>'CorrectPassword@123','is_active'=>true]);for($i=0;$i<5;$i++){$this->postJson('/api/v1/auth/login',[...$this->login,'email'=>$user->email,'password'=>'incorrect'])->assertUnprocessable();}$this->assertNotNull($user->fresh()->locked_until);$this->postJson('/api/v1/auth/login',[...$this->login,'email'=>$user->email,'password'=>'CorrectPassword@123'])->assertUnprocessable();}
 public function test_expired_lock_resets_attempt_window_and_allows_valid_login():void{$user=User::factory()->create(['password'=>'CorrectPassword@123','is_active'=>true,'failed_login_attempts'=>5,'locked_until'=>now()->subMinute()]);$this->postJson('/api/v1/auth/login',[...$this->login,'email'=>$user->email,'password'=>'CorrectPassword@123'])->assertOk();$this->assertSame(0,$user->fresh()->failed_login_attempts);$this->assertNull($user->fresh()->locked_until);}
 public function test_user_can_list_and_revoke_only_own_device():void{$user=User::factory()->create(['is_active'=>true]);$other=User::factory()->create();$device=Device::create(['id'=>'123e4567-e89b-12d3-a456-426614174000','user_id'=>$user->id,'name'=>'Telephone','platform'=>'android','fingerprint'=>'123e4567-e89b-12d3-a456-426614174000']);$otherDevice=Device::create(['id'=>'223e4567-e89b-12d3-a456-426614174000','user_id'=>$other->id,'name'=>'Autre','platform'=>'android','fingerprint'=>'223e4567-e89b-12d3-a456-426614174000']);Sanctum::actingAs($user);$this->getJson('/api/v1/auth/devices')->assertOk()->assertJsonCount(1,'devices');$this->deleteJson('/api/v1/auth/devices/'.$device->id)->assertOk();$this->deleteJson('/api/v1/auth/devices/'.$otherDevice->id)->assertNotFound();}
}
