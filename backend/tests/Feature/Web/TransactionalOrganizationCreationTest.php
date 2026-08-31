<?php

namespace Tests\Feature\Web;

use App\Models\Country;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionalOrganizationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unipays_creation_also_creates_scoped_coordination_admin(): void
    {
        [$owner, $cameroon] = $this->context();

        $this->actingAs($owner)->post(route('configuration.organization.save'), $this->payload([
            'name' => 'Santé Cameroun', 'code' => 'SANTE-CM',
            'geographic_access_type' => 'single_country', 'country_ids' => [$cameroon->id],
        ]))->assertSessionHasNoErrors();

        $organization = Organization::where('code', 'SANTE-CM')->firstOrFail();
        $admin = User::where('email', 'admin@sante.example')->firstOrFail();
        $this->assertSame('CM', $organization->country_code);
        $this->assertTrue($organization->countries()->whereKey($cameroon->id)->exists());
        $this->assertSame($organization->id, $admin->organization_id);
        $mission = $organization->missions()->where('country_id', $cameroon->id)->firstOrFail();
        $this->assertDatabaseHas('role_user', [
            'user_id' => $admin->id, 'scope_type' => 'mission', 'scope_id' => $mission->id,
        ]);
        $this->assertTrue($admin->roles()->where('code', 'coordination_admin')->exists());
    }

    public function test_multipays_creation_persists_normalized_country_relations(): void
    {
        [$owner, $cameroon, $chad] = $this->context();

        $this->actingAs($owner)->post(route('configuration.organization.save'), $this->payload([
            'name' => 'Santé Monde', 'code' => 'SANTE-WORLD',
            'geographic_access_type' => 'multi_country', 'country_ids' => [$cameroon->id, $chad->id],
            'admin_country_id' => $chad->id,
        ]))->assertSessionHasNoErrors();

        $organization = Organization::where('code', 'SANTE-WORLD')->firstOrFail();
        $this->assertNull($organization->country_code);
        $this->assertEqualsCanonicalizing([$cameroon->id, $chad->id], $organization->countries()->pluck('countries.id')->all());
        $this->assertSame(2, $organization->missions()->count());
        $admin = User::where('email', 'admin@sante.example')->firstOrFail();
        $this->assertSame(
            $organization->missions()->where('country_id', $chad->id)->value('id'),
            $admin->roles()->firstOrFail()->pivot->scope_id,
        );
    }

    public function test_invalid_admin_rolls_back_entire_organization_creation(): void
    {
        [$owner, $cameroon] = $this->context();
        User::factory()->create(['email' => 'admin@sante.example']);

        $this->actingAs($owner)->post(route('configuration.organization.save'), $this->payload([
            'name' => 'Organisation rollback', 'code' => 'ROLLBACK',
            'geographic_access_type' => 'single_country', 'country_ids' => [$cameroon->id],
        ]))->assertSessionHasErrors('admin_email');

        $this->assertDatabaseMissing('organizations', ['code' => 'ROLLBACK']);
    }

    public function test_adding_a_country_to_an_existing_organization_initializes_its_coordination(): void
    {
        [$owner, $cameroon, $chad] = $this->context();
        $organization = Organization::create([
            'name' => 'Organisation évolutive',
            'code' => 'EVOLUTIVE',
            'geographic_access_type' => 'single_country',
            'default_language' => 'fr',
            'is_active' => true,
        ]);
        $organization->countries()->sync([$cameroon->id]);

        $this->actingAs($owner)->put(
            route('configuration.organization.update', $organization),
            $this->payload([
                '_form_mode' => 'editOrganization',
                'name' => $organization->name,
                'code' => $organization->code,
                'geographic_access_type' => 'multi_country',
                'country_ids' => [$cameroon->id, $chad->id],
            ]),
        )->assertSessionHasNoErrors();

        $this->assertDatabaseHas('missions', [
            'organization_id' => $organization->id,
            'country_id' => $cameroon->id,
        ]);
        $this->assertDatabaseHas('missions', [
            'organization_id' => $organization->id,
            'country_id' => $chad->id,
        ]);
    }

    public function test_country_with_an_active_coordination_cannot_be_removed_from_organization_scope(): void
    {
        [$owner, $cameroon, $chad] = $this->context();
        $organization = Organization::create([
            'name' => 'Organisation multipays',
            'code' => 'MULTI-GUARD',
            'geographic_access_type' => 'multi_country',
            'default_language' => 'fr',
            'is_active' => true,
        ]);
        $organization->countries()->sync([$cameroon->id, $chad->id]);
        $organization->missions()->create([
            'country_id' => $chad->id,
            'code' => 'COORD-TD',
            'name' => 'Coordination Tchad',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->from(route('configuration.organization'))->put(
            route('configuration.organization.update', $organization),
            $this->payload([
                '_form_mode' => 'editOrganization',
                'name' => $organization->name,
                'code' => $organization->code,
                'geographic_access_type' => 'single_country',
                'country_ids' => [$cameroon->id],
            ]),
        )->assertRedirect(route('configuration.organization'))
            ->assertSessionHasErrors('country_ids');

        $this->assertTrue($organization->countries()->whereKey($chad->id)->exists());
        $this->assertDatabaseHas('missions', [
            'organization_id' => $organization->id,
            'country_id' => $chad->id,
            'is_active' => true,
        ]);
    }

    private function payload(array $overrides): array
    {
        return array_merge([
            '_form_mode' => 'createOrganization', 'organization_type' => 'ngo',
            'default_language' => 'fr', 'status' => 'active',
            'manager_name' => 'Direction', 'manager_title' => 'Directeur',
            'admin_first_name' => 'Admin', 'admin_last_name' => 'Coordination',
            'admin_email' => 'admin@sante.example', 'admin_username' => 'admin_sante',
            'activation_mode' => 'temporary_password', 'admin_password' => 'Secret123!AB',
            'admin_password_confirmation' => 'Secret123!AB', 'admin_status' => 'active',
        ], $overrides);
    }

    private function context(): array
    {
        $cameroon = Country::where('iso2', 'CM')->firstOrFail();
        $chad = Country::where('iso2', 'TD')->firstOrFail();
        $manage = Permission::firstOrCreate(['code' => 'organizations.manage'], ['name' => 'Gérer les organisations']);
        $configuration = Permission::firstOrCreate(['code' => 'configuration.view'], ['name' => 'Voir la configuration']);
        $ownerRole = Role::create(['code' => 'owner', 'name' => 'Admin Sago', 'is_system' => true, 'is_active' => true]);
        $ownerRole->permissions()->attach([$manage->id, $configuration->id]);
        Role::create(['code' => 'coordination_admin', 'name' => 'Admin Coordination', 'is_system' => true, 'is_active' => true]);
        $owner = User::factory()->create(['is_active' => true]);
        $owner->roles()->attach($ownerRole, ['scope_type' => 'platform', 'scope_id' => null]);

        return [$owner, $cameroon, $chad];
    }
}
