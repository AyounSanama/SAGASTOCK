<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Niveau 4 : choix Clair / Sombre / Système actif ; le réglage permet encore de le masquer. */
class DarkModeSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'sago_admin')->firstOrFail(), ['scope_type' => 'platform', 'scope_id' => null]);

        return $user;
    }

    public function test_the_three_way_switch_is_shown(): void
    {
        $this->actingAs($this->user())->get('/profile')->assertOk()
            ->assertSee('data-theme-switch', false)
            ->assertSee('value="light"', false)->assertSee('value="dark"', false)->assertSee('value="system"', false)
            ->assertSee('window.PC_DARK_MODE = true', false);
    }

    public function test_topbar_follows_the_validated_layout(): void
    {
        $user = $this->user();
        $user->update(['name' => 'Serge AYOUNG']);
        $html = $this->actingAs($user)->get('/profile')->assertOk()
            // [FR | EN] · lune (mode clair) · cloche · avatar + nom.
            ->assertSeeInOrder(['topbar-locale-switch', '>FR<', '>EN<', 'data-theme-toggle', 'dark_mode', 'notifications', 'profile-avatar', 'SA', 'Serge AYOUNG'], false)
            ->assertSee('Passer en mode sombre')
            ->getContent();
        // L'e-mail n'apparaît plus à côté du nom, seulement dans le menu du profil.
        $this->assertMatchesRegularExpression('/<span class="topbar-profile-copy"><strong>Serge AYOUNG<\/strong><\/span>/', $html);

        $user->update(['theme_preference' => 'dark']);
        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('value="light" data-theme-toggle', false)->assertSee('Passer en mode clair');
    }

    public function test_the_switch_can_still_be_hidden_by_the_setting(): void
    {
        config(['pharmacare_v1.features.dark_mode' => false]);

        $this->actingAs($this->user())->get('/profile')->assertOk()
            ->assertDontSee('data-theme-switch', false)
            ->assertSee('window.PC_DARK_MODE = false', false);
    }
}
