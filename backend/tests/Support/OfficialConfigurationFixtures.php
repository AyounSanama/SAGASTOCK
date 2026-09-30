<?php

namespace Tests\Support;

use App\Models\{Country, Organization, Role, User};
use Database\Seeders\DatabaseSeeder;

trait OfficialConfigurationFixtures
{
    private function sago(): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('code', 'sago_admin')->firstOrFail(), ['scope_type' => 'platform']);
        return $user;
    }

    private function organizationPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Organisation stabilisation', 'code' => 'STABLE', 'status' => 'active',
            'geographic_access_type' => 'single_country', 'country_ids' => [Country::where('iso2', 'CM')->value('id')],
            'admin_first_name' => 'Admin', 'admin_last_name' => 'Coordination',
            'admin_email' => 'coordination@stabilisation.example', 'admin_username' => 'coordination_stable',
            'activation_mode' => 'temporary_password', 'admin_password' => 'PharmaCare!2026',
            'admin_password_confirmation' => 'PharmaCare!2026', 'admin_status' => 'active',
        ], $overrides);
    }

    private function createOfficialOrganization(array $overrides = []): Organization
    {
        $payload = $this->organizationPayload($overrides);
        $this->post(route('configuration.organization.save'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        return Organization::where('code', $payload['code'])->firstOrFail();
    }
}
