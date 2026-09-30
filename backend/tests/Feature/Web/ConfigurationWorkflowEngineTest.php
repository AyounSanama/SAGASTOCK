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

    public function test_workflow_service_creates_independent_progressions_while_legacy_route_is_forbidden(): void
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
            $this->get(route('configuration.workflow.start', $type))->assertForbidden();
            $request = \Illuminate\Http\Request::create('/');
            $request->setUserResolver(fn () => auth()->user());
            app(ConfigurationWorkflowService::class)->start($request, ConfigurationFlowType::from($type));

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

    public function test_current_project_provisioning_preserves_existing_project(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'Organisation']);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => Country::where('iso2', 'CM')->value('id'), 'code' => 'CM', 'name' => 'Coordination']);
        $existing = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'OLD', 'name' => 'Projet existant']);
        $actor = User::factory()->create(['organization_id' => $organization->id]);
        $actor->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $mission->id]);
        $this->actingAs($actor)->post(route('organizations.projects.store', $organization), [
            'mission_id' => $mission->id, 'code' => 'NEW', 'name' => 'Nouveau projet',
            'admin' => ['first_name' => 'Admin', 'last_name' => 'Projet', 'email' => 'new-project@example.test', 'password' => 'PharmaCare!2026', 'password_confirmation' => 'PharmaCare!2026'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('projects', 2);
        $this->assertSame('Projet existant', $existing->fresh()->name);
        $new = Project::where('code', 'NEW')->firstOrFail();
        $this->assertSame($mission->id, $new->mission_id);
        $this->assertDatabaseHas('role_user', ['user_id' => User::where('email', 'new-project@example.test')->value('id'), 'scope_type' => 'project', 'scope_id' => $new->id]);
    }

    public function test_draft_is_persisted_without_password_and_marks_step_in_progress(): void
    {
        $owner = $this->owner();
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $owner);
        $service = app(ConfigurationWorkflowService::class);
        $progress = $service->start($request, ConfigurationFlowType::NewProject);
        $service->saveDraft($progress, 3, ['name' => 'Projet brouillon', 'password' => 'Secret', 'password_confirmation' => 'Secret', '_token' => 'csrf']);
        $progress->refresh();
        $this->assertSame('Projet brouillon', $progress->drafts['3']['data']['name']);
        foreach (['password', 'password_confirmation', '_token'] as $key) $this->assertArrayNotHasKey($key, $progress->drafts['3']['data']);
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

    public function test_workflow_service_restores_draft_without_overwriting_current_input(): void
    {
        $owner = $this->owner();
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $owner);
        $request->setLaravelSession(app('session.store'));
        $service = app(ConfigurationWorkflowService::class);
        $progress = $service->start($request, ConfigurationFlowType::InitialConfiguration);
        $service->saveDraft($progress, 1, ['name' => 'Organisation reprise', 'code' => 'DRAFT-ORG']);
        $service->restoreDraft($request, $progress->fresh(), 1);
        $this->assertSame('Organisation reprise', $request->old('name'));
        $this->assertSame('DRAFT-ORG', $request->old('code'));
        $request->session()->flashInput(['name' => 'Saisie prioritaire']);
        $service->restoreDraft($request, $progress, 1);
        $this->assertSame('Saisie prioritaire', $request->old('name'));
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
