<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LanguagePreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_organization_form_no_longer_exposes_language(): void
    {
        $sago = User::factory()->create(['is_active' => true]);
        $role = \App\Models\Role::where('code', 'sago_admin')->firstOrFail();
        $role->permissions()->syncWithoutDetaching(
            \App\Models\Permission::whereIn('code', ['configuration.view', 'organizations.view', 'organizations.manage'])
                ->pluck('id'),
        );
        $sago->roles()->attach($role, ['scope_type' => 'platform']);

        $this->actingAs($sago)->get(route('configuration.organization'))
            ->assertOk()
            ->assertDontSee('Langue principale');
    }

    public function test_locale_is_personal_and_isolated_between_users(): void
    {
        $organization = Organization::create(['code' => 'LANG', 'name' => 'Organisation langue']);
        $first = User::factory()->create(['organization_id' => $organization->id, 'preferred_locale' => 'fr']);
        $second = User::factory()->create(['organization_id' => $organization->id, 'preferred_locale' => 'fr']);

        Sanctum::actingAs($first);
        $this->putJson('/api/v1/auth/locale', ['preferred_locale' => 'fr'])
            ->assertOk()
            ->assertJsonPath('user.preferred_locale', 'fr');

        $this->assertSame('fr', $first->fresh()->preferred_locale);
        $this->assertSame('fr', $second->fresh()->preferred_locale);
    }

    public function test_english_locale_is_accepted_for_an_authenticated_user(): void
    {
        $user = User::factory()->create(['preferred_locale' => 'fr']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/locale', ['preferred_locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('user.preferred_locale', 'en');
        $this->assertSame('en', $user->fresh()->preferred_locale);
    }

    public function test_web_language_choice_persists_across_page_requests(): void
    {
        $user = User::factory()->create(['preferred_locale' => 'fr']);

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->post(route('profile.locale'), ['locale' => 'en'])
            ->assertRedirect(route('profile.show'));

        $this->assertSame('en', $user->fresh()->preferred_locale);
        $this->get(route('profile.show'))
            ->assertOk()
            ->assertSee('<html lang="en">', false);
    }
}
