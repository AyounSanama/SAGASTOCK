<?php

namespace Tests\Feature\Web;

use App\Models\Country;
use App\Models\Organization;
use App\Models\SetupProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationConfigurationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Support\OfficialConfigurationFixtures;

    public function test_authorized_user_can_open_organization_configuration(): void
    {
        $this->actingAs($this->owner())
            ->get(route('configuration.organization'))
            ->assertOk()
            ->assertSee('Organisations')
            ->assertSee('Ajouter une organisation');
    }

    public function test_organization_is_persisted_with_logo_and_provisions_coordination(): void
    {
        Storage::fake('public');
        Country::where('iso2', 'CM')->firstOrFail();

        $response = $this->actingAs($this->owner())->post(
            route('configuration.organization.save'),
            $this->organizationPayload([
                'name' => 'ONG Santé Cameroun',
                'organization_type' => 'ngo',
                'country_code' => 'CM',
                'code' => 'OSC-CM',
                'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
                'address' => 'Yaoundé',
                'phone' => '+237 600 000 000',
                'email' => 'contact@example.org',
                'default_language' => 'fr',
                'status' => 'active',
                'manager_name' => 'Serge Administrateur',
                'manager_title' => 'Coordinateur national',
                'action' => 'continue',
            ]),
        );

        $response->assertRedirect(route('configuration.organization'));
        $organization = Organization::firstOrFail();
        $this->assertSame('ONG Santé Cameroun', $organization->name);
        $this->assertSame('single_country', $organization->geographic_access_type);
        $this->assertSame('CM', $organization->country_code);
        $this->assertTrue($organization->is_active);
        Storage::disk('public')->assertExists($organization->logo_path);
        $this->assertContains(1, SetupProgress::current()->completed_steps);

        $this->assertSame(1, $organization->missions()->count());
        $admin = User::where('email', 'coordination@stabilisation.example')->firstOrFail();
        $this->assertDatabaseHas('role_user', ['user_id' => $admin->id, 'scope_type' => 'mission', 'scope_id' => $organization->missions()->value('id')]);
        $this->get(route('configuration.mission'))->assertForbidden();
    }

    public function test_required_fields_display_validation_errors(): void
    {
        $this->actingAs($this->owner())
            ->from(route('configuration.organization'))
            ->post(route('configuration.organization.save'), ['action' => 'save'])
            ->assertRedirect(route('configuration.organization'))
            ->assertSessionHasErrors([
                'name', 'geographic_access_type', 'country_ids', 'code',
                'status', 'admin_first_name', 'admin_last_name', 'admin_email', 'admin_username', 'activation_mode', 'admin_status',
            ]);

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_form_submitted_with_enter_does_not_require_a_button_action(): void
    {
        Country::where('iso2', 'CM')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('configuration.organization.save'), $this->organizationPayload([
                'name' => 'ONG Santé',
                'organization_type' => 'ngo',
                'country_code' => 'CM',
                'code' => 'ONG-CM',
                'default_language' => 'fr',
                'status' => 'active',
                'manager_name' => 'Responsable',
                'manager_title' => 'Coordination',
            ]))
            ->assertRedirect(route('configuration.organization'))
            ->assertSessionHasNoErrors();

        $this->assertContains(1, SetupProgress::current()->completed_steps);
    }

    public function test_mission_remains_locked_until_organization_is_validated(): void
    {
        $this->actingAs($this->owner())
            ->get(route('configuration.mission'))
            ->assertForbidden();
    }

    public function test_editing_updates_only_the_selected_organization(): void
    {
        Country::where('iso2', 'CM')->firstOrFail();
        $owner = $this->owner();
        $organization = Organization::create([
            'code' => 'ONG-CM',
            'name' => 'Ancien nom',
            'organization_type' => 'ngo',
            'country_code' => 'CM',
            'default_language' => 'fr',
            'manager_name' => 'Responsable',
            'manager_title' => 'Coordination',
        ]);

        $this->actingAs($owner)->put(route('configuration.organization.update', $organization), [
            'geographic_access_type' => 'single_country',
            'country_ids' => [Country::where('iso2', 'CM')->value('id')],
            'name' => 'Nouveau nom',
            'organization_type' => 'ngo',
            'country_code' => 'CM',
            'code' => 'ONG-CM',
            'default_language' => 'fr',
            'status' => 'active',
            'manager_name' => 'Nouvelle responsable',
            'manager_title' => 'Direction',
            'action' => 'save',
        ])->assertRedirect(route('configuration.organization'));

        $this->assertSame('Nouveau nom', $organization->fresh()->name);
    }

    public function test_user_without_configuration_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('configuration.organization'))
            ->assertForbidden();
    }

    public function test_other_official_modules_refuse_users_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('modules.stocks'))
            ->assertForbidden();
    }

    private function owner(): User
    {
        return $this->sago();
    }
}
