<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SagoAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sago_login_exposes_contract_and_exact_navigation(): void
    {
        $user = $this->sago();
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Secret@123',
            'device_name' => 'Android test', 'platform' => 'android',
            'device_id' => '123e4567-e89b-12d3-a456-426614174321',
        ])->assertOk()->assertJsonPath('role', 'SAGO_ADMIN')
            ->assertJsonStructure(['user', 'token', 'role', 'permissions', 'scope']);

        $this->assertSame(['dashboard', 'configuration', 'profile'], collect($response->json('user.navigation'))->pluck('key')->all());
        $this->post('/login', ['login' => $user->email, 'password' => 'Secret@123'])
            ->assertRedirect('/sago/dashboard');
    }

    public function test_only_sago_creates_organization_and_coordination_admin_transactionally(): void
    {
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun', 'is_active' => true]);
        Sanctum::actingAs($this->sago());
        $this->postJson('/api/v1/organizations', [
            'code' => 'SAGO_CM', 'name' => 'Organisation Santé', 'organization_type' => 'ngo',
            'geographic_access_type' => 'single_country', 'country_ids' => [$country->id],
            'admin_first_name' => 'Ada', 'admin_last_name' => 'Coordination',
            'admin_email' => 'ada@example.test', 'admin_username' => 'ada_coord',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('organization.code', 'SAGO_CM')
            ->assertJsonPath('coordination_admin.email', 'ada@example.test');

        $this->assertDatabaseHas('organizations', ['code' => 'SAGO_CM', 'country_code' => 'CM']);
        $this->assertDatabaseHas('users', ['email' => 'ada@example.test']);
        $this->assertDatabaseCount('country_organization', 1);

        $coordination = User::where('email', 'ada@example.test')->firstOrFail();
        $mission = Mission::where('organization_id', $coordination->organization_id)->firstOrFail();
        $this->assertDatabaseHas('role_user', [
            'user_id' => $coordination->id,
            'scope_type' => 'mission',
            'scope_id' => $mission->id,
        ]);
        Sanctum::actingAs($coordination);
        $this->postJson('/api/v1/organizations', [])->assertForbidden();
    }

    public function test_mobile_contract_persists_logo_without_assigning_user_language_to_organization(): void
    {
        Storage::fake('public');
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun', 'is_active' => true]);
        Sanctum::actingAs($this->sago());

        $response = $this->post('/api/v1/organizations', [
            'code' => 'MOBILE_ORG', 'name' => 'Organisation Mobile',
            'default_language' => 'fr', 'additional_languages' => ['en'],
            'status' => 'active', 'geographic_access_type' => 'single_country',
            'country_ids' => [$country->id],
            'admin_first_name' => 'Mobile', 'admin_last_name' => 'Coordination',
            'admin_email' => 'mobile@example.test', 'admin_username' => 'mobile_coord',
            'admin_status' => 'active', 'activation_mode' => 'temporary_password',
            'admin_password' => 'Temporary@123',
            'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('organization.default_language', 'fr')
            ->assertJsonPath('organization.additional_languages', null);
        $organization = Organization::where('code', 'MOBILE_ORG')->firstOrFail();
        Storage::disk('public')->assertExists($organization->logo_path);
        $this->assertNull($organization->additional_languages);
    }

    public function test_android_unipays_regression_contract_creates_the_complete_scope(): void
    {
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun', 'is_active' => true]);
        Sanctum::actingAs($this->sago());

        $this->postJson('/api/v1/organizations', [
            'name' => 'ONG SANTE TEST CAMEROUN',
            'code' => 'OSTC01',
            'geographic_access_type' => 'single_country',
            'country_ids' => [$country->id],
            'admin_first_name' => 'Jean',
            'admin_last_name' => 'COORDINATION',
            'admin_email' => 'jean.coordination.test@gmail.com',
            'admin_username' => 'jean_coordination',
            'activation_mode' => 'temporary_password',
            'admin_password' => 'Test@123',
        ])->assertCreated()
            ->assertJsonPath('organization.code', 'OSTC01')
            ->assertJsonPath('coordination_admin.username', 'jean_coordination');

        $organization = Organization::where('code', 'OSTC01')->firstOrFail();
        $admin = User::where('username', 'jean_coordination')->firstOrFail();
        $mission = Mission::where('organization_id', $organization->id)
            ->where('country_id', $country->id)->firstOrFail();
        $this->assertTrue($organization->countries()->whereKey($country->id)->exists());
        $this->assertDatabaseHas('role_user', [
            'user_id' => $admin->id,
            'scope_type' => 'mission',
            'scope_id' => $mission->id,
        ]);
    }

    public function test_api_rejects_an_admin_username_containing_spaces(): void
    {
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun', 'is_active' => true]);
        Sanctum::actingAs($this->sago());

        $this->postJson('/api/v1/organizations', [
            'name' => 'Organisation invalide', 'code' => 'INVALID_USERNAME',
            'geographic_access_type' => 'single_country', 'country_ids' => [$country->id],
            'admin_first_name' => 'Jean', 'admin_last_name' => 'Coordination',
            'admin_email' => 'invalid.username@example.test',
            'admin_username' => 'jean coordination',
        ])->assertUnprocessable()->assertJsonValidationErrors('admin_username');

        $this->assertDatabaseMissing('organizations', ['code' => 'INVALID_USERNAME']);
    }

    public function test_sago_mobile_can_load_the_shared_country_reference(): void
    {
        Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun', 'is_active' => true]);
        Country::firstOrCreate(['iso2' => 'TD'], ['name' => 'Tchad', 'is_active' => true]);
        Sanctum::actingAs($this->sago());

        $this->getJson('/api/v1/countries')
            ->assertOk()
            ->assertJsonFragment(['iso2' => 'CM', 'name' => 'Cameroun'])
            ->assertJsonFragment(['iso2' => 'TD', 'name' => 'Tchad']);
    }

    private function sago(): User
    {
        $view = Permission::firstOrCreate(['code' => 'organizations.view'], ['name' => 'Voir les organisations']);
        $manage = Permission::firstOrCreate(['code' => 'organizations.manage'], ['name' => 'Gérer les organisations']);
        $configuration = Permission::firstOrCreate(['code' => 'configuration.view'], ['name' => 'Voir la configuration']);
        $sago = Role::firstOrCreate(['code' => 'sago_admin'], ['name' => 'Admin Sago', 'is_active' => true, 'is_system' => true]);
        $sago->permissions()->syncWithoutDetaching([$view->id, $manage->id, $configuration->id]);
        Role::firstOrCreate(['code' => 'coordination_admin'], ['name' => 'Admin Coordination', 'is_active' => true, 'is_system' => true]);
        $user = User::factory()->create(['password' => 'Secret@123', 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach($sago, ['scope_type' => 'platform']);

        return $user;
    }
}
