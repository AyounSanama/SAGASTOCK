<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mode sombre hors périmètre V1 : bouton masqué, code conservé derrière un réglage. */
class DarkModeHiddenInV1Test extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'sago_admin')->firstOrFail(), ['scope_type' => 'platform', 'scope_id' => null]);

        return $user;
    }

    public function test_the_theme_toggle_is_hidden_in_v1(): void
    {
        $this->actingAs($this->user())->get('/profile')->assertOk()
            ->assertDontSee('data-theme-toggle', false)
            ->assertSee('window.PC_DARK_MODE = false', false);
    }

    public function test_the_toggle_comes_back_when_the_feature_is_enabled(): void
    {
        config(['pharmacare_v1.features.dark_mode' => true]);

        $this->actingAs($this->user())->get('/profile')->assertOk()->assertSee('data-theme-toggle', false);
    }
}
