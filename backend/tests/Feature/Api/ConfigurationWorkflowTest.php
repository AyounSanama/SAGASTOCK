<?php

namespace Tests\Feature\Api;

use App\Enums\ConfigurationFlowType;
use App\Models\SetupProgress;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_start_and_list_independent_workflows(): void
    {
        $user = $this->configurationUser();

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/configuration/workflows', [
            'flow_type' => ConfigurationFlowType::NewProject->value,
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('workflow.flow_type', 'new-project')
            ->assertJsonPath('workflow.current_step', 3)
            ->assertJsonPath('workflow.steps.0.state', 'valid')
            ->assertJsonPath('workflow.steps.2.state', 'in_progress');

        $workflowId = $created->json('workflow.id');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/configuration/workflows')
            ->assertOk()
            ->assertJsonPath('active_workflow.id', $workflowId)
            ->assertJsonCount(5, 'flow_types');
    }

    public function test_user_cannot_read_another_users_workflow(): void
    {
        $owner = $this->configurationUser();
        $other = $this->configurationUser();
        $progress = SetupProgress::create([
            'workflow_id' => fake()->uuid(),
            'flow_type' => ConfigurationFlowType::InitialConfiguration,
            'start_step' => 1,
            'current_step' => 1,
            'completed_steps' => [],
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/configuration/workflows/{$progress->workflow_id}")
            ->assertNotFound();
    }

    public function test_draft_is_saved_without_sensitive_fields(): void
    {
        $user = $this->configurationUser();
        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/configuration/workflows', [
            'flow_type' => ConfigurationFlowType::NewUser->value,
        ]);

        $workflowId = $created->json('workflow.id');
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/configuration/workflows/{$workflowId}/draft", [
                'step' => 11,
                'data' => [
                    'first_name' => 'Serge',
                    'password' => 'Secret-123!',
                    'password_confirmation' => 'Secret-123!',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('workflow.drafts.11.data.first_name', 'Serge')
            ->assertJsonMissingPath('workflow.drafts.11.data.password');
    }

    public function test_configuration_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/configuration/workflows')->assertUnauthorized();
    }

    private function configurationUser(): User
    {
        $permission = Permission::firstOrCreate(
            ['code' => 'configuration.view'],
            ['name' => 'Accéder à la configuration'],
        );
        $role = Role::firstOrCreate(
            ['code' => 'coordination_admin'],
            ['name' => 'Admin Coordination', 'is_system' => true],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, [
            'scope_type' => 'organization',
            'scope_id' => null,
        ]);

        return $user;
    }
}
