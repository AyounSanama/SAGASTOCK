<?php

namespace Tests\Feature\Web;

use App\Enums\ConfigurationFlowType;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SetupProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationHomeNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_displays_only_current_platform_configuration_sections(): void
    {
        [$owner] = $this->context();
        $this->actingAs($owner)->get(route('configuration.index'))->assertOk()
            ->assertSee('Organisations')->assertSee('Standards &amp; Référentiels', false)
            ->assertSee(route('configuration.organization'), false)
            ->assertSee(route('configuration.platform-standards.index'), false)
            ->assertDontSee('Missions / Pays')->assertDontSee('Projet actif');
    }

    public function test_standalone_organization_form_replaces_legacy_workflow_actions(): void
    {
        [$owner] = $this->context();
        $this->actingAs($owner);
        foreach ([ConfigurationFlowType::NewOrganization, ConfigurationFlowType::NewMission] as $type) {
            $this->get(route('configuration.workflow.start', $type->value))->assertForbidden();
        }
        $this->get(route('configuration.organization', ['create' => 1]))->assertOk()
            ->assertSee('data-organization-mode="createOrganization"', false)
            ->assertSee('name="name" value=""', false);
        $this->assertDatabaseCount('missions', 0);
    }

    public function test_forbidden_legacy_step_and_configuration_return_preserve_progress(): void
    {
        [$owner, $organization] = $this->context();
        $states = array_fill_keys(array_map('strval', range(1, 12)), 'not_started');
        $states['1'] = 'valid';
        $states['2'] = 'in_progress';
        $progress = SetupProgress::create([
            'workflow_id' => fake()->uuid(),
            'flow_type' => ConfigurationFlowType::NewMission,
            'start_step' => 2, 'current_step' => 2,
            'completed_steps' => [1], 'step_states' => $states,
            'scope_type' => 'organization', 'scope_id' => $organization->id,
            'workflow_status' => 'active', 'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('configuration.mission', ['_flow' => $progress->workflow_id]))
            ->assertForbidden();

        $this->get(route('configuration.index', ['_flow' => $progress->workflow_id]))->assertOk();
        $this->assertSame([1], $progress->fresh()->completed_steps);
        $this->assertSame(2, $progress->fresh()->current_step);
    }

    private function context(): array
    {
        Country::where('iso2', 'CM')->firstOrFail();
        $organization = Organization::create([
            'code' => 'ORG', 'name' => 'Organisation Test',
            'organization_type' => 'ngo', 'country_code' => 'CM',
            'default_language' => 'fr', 'manager_name' => 'Responsable',
            'manager_title' => 'Coordination', 'is_active' => true,
        ]);
        $permissions = collect([
            ['configuration.view', 'Accéder à la configuration'],
            ['organizations.manage', 'Gérer les organisations'],
            ['organizations.view', 'Voir les organisations'],
            ['missions.manage', 'Gérer les missions'],
        ])->map(fn ($item) => Permission::firstOrCreate(['code' => $item[0]], ['name' => $item[1]]));
        $role = Role::create(['code' => 'owner', 'name' => 'Propriétaire', 'is_system' => true]);
        $role->permissions()->attach($permissions->pluck('id'));
        $owner = User::factory()->create(['is_active' => true]);
        $owner->roles()->attach($role, ['scope_type' => 'platform', 'scope_id' => null]);

        return [$owner, $organization];
    }
}
