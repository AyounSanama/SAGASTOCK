<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Niveau 4 — Choix Clair / Sombre / Système enregistré dans le profil (Web et mobile). */
class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_switch_saves_the_preference_and_applies_it(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('data-theme-switch', false)->assertSee('data-theme-preference="light"', false);
        $this->actingAs($user)->postJson(route('profile.theme'), ['theme' => 'dark'])->assertOk()->assertJsonPath('theme', 'dark');
        $this->assertSame('dark', $user->fresh()->theme_preference);
        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('data-theme-preference="dark"', false)->assertSee('window.PC_THEME = "dark"', false);
        $this->actingAs($user)->post(route('profile.theme'), ['theme' => 'violet'])->assertSessionHasErrors('theme');
    }

    public function test_mobile_api_and_read_only_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $reader = User::factory()->create(['is_active' => true, 'must_change_password' => false, 'read_only' => true]);
        $reader->roles()->attach(Role::where('code', 'sago_admin')->firstOrFail(), ['scope_type' => 'platform', 'scope_id' => null]);
        Sanctum::actingAs($reader);

        // Préférence d'affichage : autorisée même en lecture seule (comme la langue).
        $this->putJson('/api/v1/auth/theme', ['theme_preference' => 'system'])->assertOk()
            ->assertJsonPath('user.theme_preference', 'system');
        $this->putJson('/api/v1/auth/theme', ['theme_preference' => 'sepia'])->assertUnprocessable();
    }
}
