<?php

namespace Tests\Feature\Web;

use App\Models\Country;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SetupProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_organization_configuration(): void
    {
        $this->actingAs($this->owner())
            ->get(route('configuration.organization'))
            ->assertOk()
            ->assertSee('Configuration Initiale')
            ->assertSee('Organisations')
            ->assertSee('Ajouter une organisation');
    }

    public function test_organization_is_validated_persisted_and_unlocks_mission(): void
    {
        Storage::fake('public');
        Country::create(['iso2' => 'CM', 'name' => 'Cameroun', 'is_active' => true]);

        $response = $this->actingAs($this->owner())->post(
            route('configuration.organization.save'),
            [
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
            ],
        );

        $response->assertRedirect(route('configuration.organization'));
        $organization = Organization::firstOrFail();
        $this->assertSame('ONG Santé Cameroun', $organization->name);
        $this->assertSame('ngo', $organization->organization_type);
        $this->assertSame('CM', $organization->country_code);
        $this->assertTrue($organization->is_active);
        Storage::disk('public')->assertExists($organization->logo_path);
        $this->assertContains(1, SetupProgress::current()->completed_steps);

        $flow = SetupProgress::current()->workflow_id;
        $this->get(route('configuration.mission', ['_flow' => $flow]))
            ->assertOk()
            ->assertSee('Mission')
            ->assertSee('Définissez la mission nationale');
    }

    public function test_required_fields_display_validation_errors(): void
    {
        $this->actingAs($this->owner())
            ->from(route('configuration.organization'))
            ->post(route('configuration.organization.save'), ['action' => 'save'])
            ->assertRedirect(route('configuration.organization'))
            ->assertSessionHasErrors([
                'name', 'organization_type', 'country_code', 'code',
                'default_language', 'status', 'manager_name', 'manager_title',
            ]);

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_form_submitted_with_enter_does_not_require_a_button_action(): void
    {
        Country::create(['iso2' => 'CM', 'name' => 'Cameroun', 'is_active' => true]);

        $this->actingAs($this->owner())
            ->post(route('configuration.organization.save'), [
                'name' => 'ONG Santé',
                'organization_type' => 'ngo',
                'country_code' => 'CM',
                'code' => 'ONG-CM',
                'default_language' => 'fr',
                'status' => 'active',
                'manager_name' => 'Responsable',
                'manager_title' => 'Coordination',
            ])
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
        Country::create(['iso2' => 'CM', 'name' => 'Cameroun', 'is_active' => true]);
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
        $permission = Permission::create([
            'code' => 'organizations.manage',
            'name' => 'Gérer les organisations',
        ]);
        $configurationPermission = Permission::firstOrCreate([
            'code' => 'configuration.view',
        ], [
            'name' => 'Accéder à la configuration',
        ]);
        $missionPermission = Permission::create([
            'code' => 'missions.manage',
            'name' => 'Gérer les missions',
        ]);
        $role = Role::create([
            'code' => 'owner',
            'name' => 'Propriétaire',
            'is_system' => true,
        ]);
        $role->permissions()->attach([$permission->id, $missionPermission->id, $configurationPermission->id]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role, [
            'scope_type' => 'platform',
            'scope_id' => null,
        ]);

        return $user;
    }
}
