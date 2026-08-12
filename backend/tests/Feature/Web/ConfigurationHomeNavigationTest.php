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

    public function test_home_only_displays_the_three_authorized_actions(): void
    {
        [$owner] = $this->context();

        $this->actingAs($owner)->get(route('configuration.index'))
            ->assertOk()
            ->assertSee('Ajouter une organisation')
            ->assertSee('Ajouter une mission')
            ->assertSee('Entrer dans l’application')
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('Ajouter un projet')
            ->assertDontSee('Ajouter un site')
            ->assertDontSee('Ajouter un utilisateur');
    }

    public function test_organization_and_mission_actions_start_expected_workflows(): void
    {
        [$owner, $organization] = $this->context();
        $this->actingAs($owner);

        $this->get(route('configuration.workflow.start', ConfigurationFlowType::NewOrganization->value))
            ->assertRedirectContains('/configuration/organization')
            ->assertRedirectContains('create=1');

        $mission = $this->get(route('configuration.workflow.start', [
            'flowType' => ConfigurationFlowType::NewMission->value,
            'organization' => $organization->id,
        ]));
        $mission->assertRedirectContains('/configuration/mission');
        parse_str(parse_url($mission->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

        $this->get(route('configuration.mission', ['_flow' => $query['_flow']]))
            ->assertOk()
            ->assertSee('mission-create-sheet')
            ->assertSee('Ajouter une mission');
    }

    public function test_return_link_preserves_workflow_progress(): void
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
            ->assertOk()
            ->assertSee('Retour à l’accueil Configuration')
            ->assertSee(route('configuration.index', ['_flow' => $progress->workflow_id]), false);

        $this->get(route('configuration.index', ['_flow' => $progress->workflow_id]))->assertOk();
        $this->assertSame([1], $progress->fresh()->completed_steps);
        $this->assertSame(2, $progress->fresh()->current_step);
    }

    private function context(): array
    {
        Country::create(['iso2' => 'CM', 'name' => 'Cameroun', 'is_active' => true]);
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
