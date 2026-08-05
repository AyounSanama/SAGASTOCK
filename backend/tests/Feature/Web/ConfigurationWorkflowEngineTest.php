<?php

namespace Tests\Feature\Web;

use App\Enums\ConfigurationFlowType;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\SetupProgress;
use App\Models\User;
use App\Services\ConfigurationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationWorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_flow_type_creates_an_independent_progression(): void
    {
        $this->actingAs($this->owner());
        Organization::create(['code' => 'FLOW-ORG', 'name' => 'Organisation workflow']);

        $expectations = [
            ConfigurationFlowType::InitialConfiguration->value => [],
            ConfigurationFlowType::NewMission->value => [1],
            ConfigurationFlowType::NewProject->value => [1, 2],
            ConfigurationFlowType::NewSite->value => range(1, 9),
            ConfigurationFlowType::NewUser->value => range(1, 10),
        ];

        foreach ($expectations as $type => $completed) {
            $this->get(route('configuration.workflow.start', $type))
                ->assertRedirect();

            $progress = SetupProgress::query()->where('flow_type', $type)->latest('id')->firstOrFail();
            $this->assertSame($completed, $progress->completed_steps);
            $this->assertSame(count($completed) + 1, $progress->start_step);
            $this->assertNotNull($progress->workflow_id);
            foreach ($completed as $step) {
                $this->assertSame('valid', $progress->step_states[(string) $step]);
            }
            $this->assertSame(
                'in_progress',
                $progress->step_states[(string) $progress->start_step],
            );
        }

        $this->assertDatabaseCount('setup_progress', 5);
        $this->assertSame(
            5,
            SetupProgress::query()->distinct()->count('workflow_id'),
        );
    }

    public function test_new_project_flow_creates_a_project_without_modifying_the_existing_one(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Test']);
        $country = Country::create(['iso2' => 'CM', 'name' => 'Cameroun']);
        $mission = Mission::create([
            'organization_id' => $organization->id,
            'country_id' => $country->id,
            'code' => 'MISSION',
            'name' => 'Mission existante',
            'is_active' => true,
        ]);
        $existing = Project::create([
            'organization_id' => $organization->id,
            'mission_id' => $mission->id,
            'code' => 'OLD',
            'name' => 'Projet existant',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'is_active' => true,
        ]);

        $response = $this->get(route(
            'configuration.workflow.start',
            ConfigurationFlowType::NewProject->value,
        ));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->post(route('configuration.step.save', [
            'step' => 'projects',
            '_flow' => $query['_flow'],
        ]), [
            'mission_id' => $mission->id,
            'name' => 'Nouveau projet',
            'code' => 'NEW',
            'description' => 'Projet créé dans un workflow isolé',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseCount('projects', 2);
        $this->assertDatabaseHas('projects', [
            'id' => $existing->id,
            'name' => 'Projet existant',
            'code' => 'OLD',
        ]);
        $this->assertDatabaseHas('projects', [
            'name' => 'Nouveau projet',
            'code' => 'NEW',
        ]);

        $progress = SetupProgress::where('workflow_id', $query['_flow'])->firstOrFail();
        $this->assertSame([1, 2, 3], $progress->completed_steps);
        $this->assertNotEmpty($progress->context['project_id']);
        $this->assertSame('valid', $progress->step_states['3']);
        $this->assertSame('in_progress', $progress->step_states['4']);
    }

    public function test_draft_is_persisted_without_password_and_marks_step_in_progress(): void
    {
        $this->actingAs($this->owner());
        Organization::create(['code' => 'DRAFT-ORG', 'name' => 'Organisation brouillon']);
        $response = $this->get(route(
            'configuration.workflow.start',
            ConfigurationFlowType::NewProject->value,
        ));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson(route('configuration.workflow.draft', $query['_flow']), [
            'step' => 3,
            'payload' => [
                'name' => 'Projet brouillon',
                'description' => 'À reprendre',
                'password' => 'NeDoitJamaisEtreStocke',
                'password_confirmation' => 'NeDoitJamaisEtreStocke',
            ],
        ])->assertOk()->assertJsonPath('saved', true);

        $progress = SetupProgress::where('workflow_id', $query['_flow'])->firstOrFail();
        $this->assertSame('Projet brouillon', $progress->drafts['3']['data']['name']);
        $this->assertArrayNotHasKey('password', $progress->drafts['3']['data']);
        $this->assertArrayNotHasKey('password_confirmation', $progress->drafts['3']['data']);
        $this->assertSame('in_progress', $progress->step_states['3']);
    }

    public function test_revalidating_an_earlier_step_invalidates_all_dependent_steps(): void
    {
        $owner = $this->owner();
        $progress = SetupProgress::create([
            'workflow_id' => fake()->uuid(),
            'flow_type' => ConfigurationFlowType::InitialConfiguration,
            'start_step' => 1,
            'current_step' => 6,
            'completed_steps' => [1, 2, 3, 4, 5],
            'step_states' => [
                '1' => 'valid', '2' => 'valid', '3' => 'valid',
                '4' => 'valid', '5' => 'valid', '6' => 'in_progress',
                '7' => 'not_started', '8' => 'not_started',
                '9' => 'not_started', '10' => 'not_started',
                '11' => 'not_started', '12' => 'not_started',
            ],
            'created_by' => $owner->id,
        ]);

        app(ConfigurationWorkflowService::class)->complete($progress, 3);
        $progress->refresh();

        $this->assertSame([1, 2, 3], $progress->completed_steps);
        $this->assertSame('valid', $progress->step_states['3']);
        $this->assertSame('in_progress', $progress->step_states['4']);
        $this->assertSame('not_started', $progress->step_states['5']);
    }

    public function test_saved_draft_is_restored_when_the_step_is_reopened(): void
    {
        $this->actingAs($this->owner());
        $response = $this->get(route(
            'configuration.workflow.start',
            ConfigurationFlowType::InitialConfiguration->value,
        ));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson(route('configuration.workflow.draft', $query['_flow']), [
            'step' => 1,
            'payload' => [
                'name' => 'Organisation reprise automatiquement',
                'code' => 'DRAFT-ORG',
                'manager_name' => 'Responsable brouillon',
            ],
        ])->assertOk();

        $this->get(route('configuration.organization', ['_flow' => $query['_flow']]))
            ->assertOk()
            ->assertSee('Organisation reprise automatiquement')
            ->assertSee('DRAFT-ORG')
            ->assertSee('Responsable brouillon');
    }

    private function owner(): User
    {
        $permissions = collect([
            'configuration.view',
            'organizations.manage',
            'missions.manage',
            'projects.manage',
            'funding.manage',
            'modules.manage',
            'catalog.manage',
            'structures.manage',
            'users.manage',
        ])->map(fn (string $code) => Permission::firstOrCreate([
            'code' => $code,
        ], [
            'name' => $code,
        ]));
        $role = Role::create([
            'code' => 'platform_owner',
            'name' => 'Propriétaire plateforme',
        ]);
        $role->permissions()->attach($permissions->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id, ['scope_type' => 'platform']);

        return $user;
    }
}
