<?php
namespace Tests\Feature\Api;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;
class PasswordRecoveryTest extends TestCase {
 use RefreshDatabase;
 public function test_forgot_password_response_does_not_disclose_account_existence():void {Notification::fake();$user=User::factory()->create();$known=$this->postJson('/api/v1/auth/forgot-password',['email'=>$user->email])->assertOk()->json('message');$unknown=$this->postJson('/api/v1/auth/forgot-password',['email'=>'absent@example.org'])->assertOk()->json('message');$this->assertSame($known,$unknown);Notification::assertSentTo($user,ResetPassword::class);}
 public function test_valid_token_resets_password_and_revokes_tokens():void {$user=User::factory()->create(['password'=>'OldPassword@123']);$user->createToken('phone');$token=Password::createToken($user);$this->postJson('/api/v1/auth/reset-password',['email'=>$user->email,'token'=>$token,'password'=>'NewPassword@123','password_confirmation'=>'NewPassword@123'])->assertOk();$this->assertTrue(password_verify('NewPassword@123',$user->fresh()->password));$this->assertCount(0,$user->tokens()->get());}
 public function test_web_login_and_recovery_pages_are_available():void {$this->get('/login')->assertOk()->assertSee('Mot de passe oublié');$this->get('/forgot-password')->assertOk()->assertSee('Envoyer le lien');}
}
