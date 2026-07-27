<?php

namespace Tests\Feature\Api;

use App\Models\User;
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
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles']]);
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
}
