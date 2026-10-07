<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\OrganizationEffectiveConfiguration;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Les langues sont choisies par chaque Coordination, plus par l'Admin Sago. */
class CoordinationLanguagesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé', 'default_language' => 'fr']);
        $this->mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => 'YDE', 'name' => 'Coordination Yaoundé', 'is_active' => true, 'default_language' => 'fr']);
        $this->coordination = $this->user('coordination_admin', 'mission', $this->mission->id);
    }

    public function test_coordination_chooses_its_own_languages_on_the_web(): void
    {
        $this->actingAs($this->coordination)->followingRedirects()->get('/missions')->assertOk()
            ->assertSee('Langues : Français')->assertSee('Langues de la coordination')->assertSee('Wollof (wolof)');

        $this->actingAs($this->coordination)->put(route('coordination.languages.update', $this->mission), [
            'default_language' => 'fr', 'additional_languages' => ['en', 'wo', 'fr'],
        ])->assertRedirect()->assertSessionHas('status', fn ($status) => str_contains($status, 'Français, English (anglais), Wollof (wolof)'));

        $this->mission->refresh();
        $this->assertSame('fr', $this->mission->default_language);
        $this->assertSame(['en', 'wo'], $this->mission->additional_languages, 'La langue principale n’est pas répétée');
        $this->assertDatabaseHas('audit_logs', ['event' => 'mission.languages.updated']);

        $this->actingAs($this->coordination)->put(route('coordination.languages.update', $this->mission), ['default_language' => 'xx'])
            ->assertSessionHasErrors('default_language');
    }

    public function test_mobile_api_and_access_rules(): void
    {
        Sanctum::actingAs($this->coordination);
        $this->getJson('/api/v1/coordination/overview')->assertOk()->assertJsonPath('mission.default_language', 'fr');
        $this->putJson("/api/v1/coordination/missions/{$this->mission->id}/languages", ['default_language' => 'en', 'additional_languages' => ['fr', 'ln']])
            ->assertOk()->assertJsonPath('mission.default_language', 'en')->assertJsonPath('mission.additional_languages', ['fr', 'ln']);

        // Une autre coordination ne modifie pas ces langues.
        $other = Mission::create(['organization_id' => $this->organization->id, 'country_id' => $this->mission->country_id, 'code' => 'DLA', 'name' => 'Coordination Douala', 'is_active' => true]);
        Sanctum::actingAs($this->user('coordination_admin', 'mission', $other->id));
        $this->putJson("/api/v1/coordination/missions/{$this->mission->id}/languages", ['default_language' => 'es'])->assertNotFound();

        // Un compte en lecture seule non plus.
        $readOnly = $this->user('coordination_admin', 'mission', $this->mission->id);
        $readOnly->update(['read_only' => true]);
        Sanctum::actingAs($readOnly);
        $this->putJson("/api/v1/coordination/missions/{$this->mission->id}/languages", ['default_language' => 'es'])->assertForbidden();
        $this->assertSame('en', $this->mission->fresh()->default_language);
    }

    public function test_sago_no_longer_sets_languages(): void
    {
        $sago = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $sago->roles()->attach(Role::where('code', 'sago_admin')->firstOrFail(), ['scope_type' => 'platform', 'scope_id' => null]);
        $this->actingAs($sago)->get(route('configuration.platform-standards.organizations.assist', $this->organization))->assertOk()
            ->assertDontSee('settings[default_language]', false)->assertDontSee('settings[additional_languages]', false);

        $this->actingAs($sago)->post('/configuration/platform-standards/manual/preview', [
            'organization_id' => $this->organization->id, 'category' => 'general',
            'settings' => ['default_language' => 'es', 'additional_languages' => ['pt'], 'timezone' => 'Africa/Douala', 'locale' => 'fr_CM', 'date_format' => 'd/m/Y', 'time_format' => 'H:i'],
        ])->assertRedirect();
        $this->post('/configuration/platform-standards/manual/publish', ['organization_id' => $this->organization->id, 'category' => 'general'])->assertRedirect();
        $definition = OrganizationEffectiveConfiguration::where('organization_id', $this->organization->id)->latest('effective_at')->firstOrFail()->configuration['definition'];
        $this->assertArrayNotHasKey('default_language', $definition);
        $this->assertArrayNotHasKey('additional_languages', $definition);
        $this->assertSame('fr_CM', $definition['locale']);
        $this->assertSame('fr', $this->mission->fresh()->default_language);
    }

    private function user(string $role, string $scopeType, ?string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }
}
