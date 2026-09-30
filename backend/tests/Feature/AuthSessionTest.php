<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_clears_previous_authenticated_session_state(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $response = $this->withSession([
            'currentUser' => ['name' => 'Old user'],
            'visibleModules' => ['old-module'],
            'configurationState' => 'draft',
        ])->post('/login', [
            'login' => 'alice@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->has('currentUser'));
        $this->assertFalse(session()->has('visibleModules'));
        $this->assertFalse(session()->has('configurationState'));
    }

    public function test_authenticated_user_is_redirected_to_dashboard_from_root_and_login_page(): void
    {
        $user = User::factory()->create([
            'email' => 'bob@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/login')->assertRedirect('/dashboard');
    }

    public function test_authenticated_user_without_dashboard_permission_can_still_access_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'carol@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('Vue d’ensemble opérationnelle')
            ->assertDontSee('Alertes de stock')
            ->assertDontSee('<button class="topbar-back"', false);
    }
}
