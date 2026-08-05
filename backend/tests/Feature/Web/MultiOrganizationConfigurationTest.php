<?php

namespace Tests\Feature\Web;

use App\Enums\ConfigurationFlowType;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SetupProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MultiOrganizationConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_two_organizations_and_both_are_listed(): void
    {
        [$owner] = $this->context();
        $this->actingAs($owner);

        $this->post(route('configuration.organization.save'), $this->payload('ORG-A', 'Organisation A'))
            ->assertSessionHasNoErrors();
        $flow = $this->startFlow(ConfigurationFlowType::NewOrganization);
        $this->post(route('configuration.organization.save', ['_flow' => $flow]), $this->payload('ORG-B', 'Organisation B'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('organizations', 2);
        $this->get(route('configuration.organization', ['_flow' => $flow]))
            ->assertOk()
            ->assertSee('Organisation A')
            ->assertSee('Organisation B');
    }

    public function test_missions_are_isolated_by_organization_workflow(): void
    {
        [$owner, $country] = $this->context();
        $organizationA = $this->organization('ORG-A', 'Organisation A');
        $organizationB = $this->organization('ORG-B', 'Organisation B');
        Mission::create([
            'organization_id' => $organizationA->id, 'country_id' => $country->id,
            'code' => 'MISSION-A', 'name' => 'Mission Cameroun A', 'is_active' => true,
        ]);
        Mission::create([
            'organization_id' => $organizationB->id, 'country_id' => $country->id,
            'code' => 'MISSION-B', 'name' => 'Mission Cameroun B', 'is_active' => true,
        ]);
        $this->actingAs($owner);

        $flowA = $this->startFlow(ConfigurationFlowType::NewMission, $organizationA);
        $this->get(route('configuration.mission', ['_flow' => $flowA]))
            ->assertSee('Mission Cameroun A')
            ->assertDontSee('Mission Cameroun B');

        $flowB = $this->startFlow(ConfigurationFlowType::NewMission, $organizationB);
        $this->get(route('configuration.mission', ['_flow' => $flowB]))
            ->assertSee('Mission Cameroun B')
            ->assertDontSee('Mission Cameroun A');
    }

    public function test_coordination_admin_only_sees_the_assigned_organization(): void
    {
        [, , $viewPermission] = $this->context();
        $organizationA = $this->organization('ORG-A', 'Organisation A');
        $this->organization('ORG-B', 'Organisation B');
        $role = Role::create([
            'code' => 'coordination_admin', 'name' => 'Admin Coordination',
            'is_system' => true, 'scope_type' => 'organization',
        ]);
        $configuration = Permission::firstOrCreate(['code' => 'configuration.view'], ['name' => 'Accéder à la configuration']);
        $role->permissions()->attach([$viewPermission->id, $configuration->id]);
        $admin = User::factory()->create(['organization_id' => $organizationA->id]);
        $admin->roles()->attach($role, [
            'scope_type' => 'organization', 'scope_id' => $organizationA->id,
        ]);

        $this->actingAs($admin)->get(route('configuration.organization'))
            ->assertOk()
            ->assertSee('Organisation A')
            ->assertDontSee('Organisation B')
            ->assertDontSee('Ajouter une organisation');
    }

    public function test_new_organization_workflow_has_no_inherited_green_steps_and_empty_form(): void
    {
        [$owner] = $this->context();
        $this->organization('OLD', 'Ancienne organisation');
        SetupProgress::create([
            'workflow_id' => fake()->uuid(),
            'flow_type' => ConfigurationFlowType::InitialConfiguration,
            'start_step' => 1, 'current_step' => 12,
            'completed_steps' => range(1, 12),
            'step_states' => array_fill_keys(array_map('strval', range(1, 12)), 'valid'),
            'workflow_status' => 'completed', 'created_by' => $owner->id,
        ]);
        $this->actingAs($owner);

        $flow = $this->startFlow(ConfigurationFlowType::NewOrganization);
        $progress = SetupProgress::where('workflow_id', $flow)->firstOrFail();
        $this->assertSame([], $progress->completed_steps);
        $this->assertSame('in_progress', $progress->step_states['1']);
        foreach (range(2, 12) as $step) {
            $this->assertSame('not_started', $progress->step_states[(string) $step]);
        }
        $this->get(route('configuration.organization', ['_flow' => $flow, 'create' => 1]))
            ->assertOk()
            ->assertSee('data-organization-mode="createOrganization"', false)
            ->assertSee('name="name" value=""', false);
    }

    public function test_editing_one_organization_never_changes_the_other(): void
    {
        [$owner] = $this->context();
        $organizationA = $this->organization('ORG-A', 'Organisation A');
        $organizationB = $this->organization('ORG-B', 'Organisation B');

        $this->actingAs($owner)->put(
            route('configuration.organization.update', $organizationB),
            $this->payload('ORG-B', 'Organisation B modifiée'),
        )->assertSessionHasNoErrors();

        $this->assertSame('Organisation A', $organizationA->fresh()->name);
        $this->assertSame('Organisation B modifiée', $organizationB->fresh()->name);
    }

    public function test_coordination_admin_cannot_create_edit_or_archive_organization(): void
    {
        [, , $viewPermission] = $this->context();
        $organization = $this->organization('ORG-A', 'Organisation A');
        $role = Role::create([
            'code' => 'coordination_admin', 'name' => 'Admin Coordination',
            'is_system' => true, 'scope_type' => 'organization',
        ]);
        $configuration = Permission::firstOrCreate(['code' => 'configuration.view'], ['name' => 'Accéder à la configuration']);
        $role->permissions()->attach([$viewPermission->id, $configuration->id]);
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->roles()->attach($role, ['scope_type' => 'organization', 'scope_id' => $organization->id]);
        $this->actingAs($admin);

        $this->post(route('configuration.organization.save'), $this->payload('NOPE', 'Interdit'))->assertForbidden();
        $this->put(route('configuration.organization.update', $organization), $this->payload('ORG-A', 'Interdit'))->assertForbidden();
        $this->delete(route('configuration.organization.archive', $organization))->assertForbidden();
        $this->assertFalse($organization->fresh()->trashed());
    }

    public function test_required_multi_organization_foreign_keys_are_present(): void
    {
        $this->assertTrue(Schema::hasColumn('missions', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('projects', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('health_facilities', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('sites', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('users', 'organization_id'));
    }

    private function startFlow(ConfigurationFlowType $type, ?Organization $organization = null): string
    {
        $response = $this->get(route('configuration.workflow.start', array_filter([
            'flowType' => $type->value,
            'organization' => $organization?->id,
        ])));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

        return $query['_flow'];
    }

    private function payload(string $code, string $name): array
    {
        return [
            '_form_mode' => 'createOrganization',
            'name' => $name, 'organization_type' => 'ngo', 'country_code' => 'CM',
            'code' => $code, 'default_language' => 'fr', 'status' => 'active',
            'manager_name' => 'Responsable', 'manager_title' => 'Coordination',
            'description' => 'Organisation de test',
        ];
    }

    private function organization(string $code, string $name): Organization
    {
        return Organization::create([
            'code' => $code, 'name' => $name, 'organization_type' => 'ngo',
            'country_code' => 'CM', 'default_language' => 'fr',
            'manager_name' => 'Responsable', 'manager_title' => 'Coordination',
            'is_active' => true,
        ]);
    }

    private function context(): array
    {
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun', 'is_active' => true]);
        $manage = Permission::firstOrCreate(['code' => 'organizations.manage'], ['name' => 'Gérer les organisations']);
        $configuration = Permission::firstOrCreate(['code' => 'configuration.view'], ['name' => 'Accéder à la configuration']);
        $view = Permission::firstOrCreate(['code' => 'organizations.view'], ['name' => 'Voir les organisations']);
        $missions = Permission::firstOrCreate(['code' => 'missions.manage'], ['name' => 'Gérer les missions']);
        $role = Role::firstOrCreate(['code' => 'owner'], ['name' => 'Propriétaire', 'is_system' => true]);
        $role->permissions()->syncWithoutDetaching([$manage->id, $view->id, $missions->id, $configuration->id]);
        $owner = User::factory()->create(['is_active' => true]);
        $owner->roles()->attach($role, ['scope_type' => 'platform', 'scope_id' => null]);

        return [$owner, $country, $view];
    }
}
