<?php

namespace Tests\Feature\Api;

use App\Models\Country;
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
        Sanctum::actingAs($coordination);
        $this->postJson('/api/v1/organizations', [])->assertForbidden();
    }

    public function test_mobile_contract_persists_logo_and_languages(): void
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
            ->assertJsonPath('organization.additional_languages.0', 'en');
        $organization = \App\Models\Organization::where('code', 'MOBILE_ORG')->firstOrFail();
        Storage::disk('public')->assertExists($organization->logo_path);
        $this->assertSame(['en'], $organization->additional_languages);
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
