<?php

namespace Tests\Feature\Web;

use App\Http\Middleware\ApplyUserLocale;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le slogan de la page de connexion suit la langue choisie (FR/EN). */
class LoginTaglineLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_french_by_default(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Placer le patient au cœur de chaque approvisionnement.')
            ->assertSee('Se connecter')
            ->assertDontSee('Putting Patients at the Heart of Every Supply.');
    }

    public function test_login_page_follows_the_last_chosen_language(): void
    {
        $this->withCookie(ApplyUserLocale::COOKIE, 'en')->get('/login')->assertOk()
            ->assertSee('Putting Patients at the Heart of Every Supply.')
            ->assertSee('Sign in')
            ->assertSee('<html lang="en">', false);
    }

    public function test_changing_the_language_remembers_it_for_the_login_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false, 'preferred_locale' => 'fr']);

        $this->actingAs($user)->from('/profile')->post('/profile/locale', ['locale' => 'en'])
            ->assertRedirect('/profile')
            ->assertCookie(ApplyUserLocale::COOKIE, 'en');
    }
}
