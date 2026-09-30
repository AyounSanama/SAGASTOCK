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

    public function test_coordination_cannot_start_or_list_legacy_workflows(): void
    {
        $user = $this->configurationUser();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/configuration/workflows', [
            'flow_type' => ConfigurationFlowType::NewProject->value,
        ])->assertForbidden();
        $this->getJson('/api/v1/configuration/workflows')->assertForbidden();
        $this->assertDatabaseCount('setup_progress', 0);
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
            ->assertForbidden();
    }

    public function test_draft_is_saved_without_sensitive_fields(): void
    {
        $user = $this->configurationUser();
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $user);
        $service = app(\App\Services\ConfigurationWorkflowService::class);
        $progress = $service->start($request, ConfigurationFlowType::NewUser);
        // Draft sanitization is still a service contract. Official V1 roles cannot call the legacy API.
        $service->saveDraft($progress, 11, ['first_name' => 'Serge', 'password' => 'Secret-123!', 'password_confirmation' => 'Secret-123!']);
        $data = $progress->fresh()->drafts['11']['data'];
        $this->assertSame('Serge', $data['first_name']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('password_confirmation', $data);
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/configuration/workflows/{$progress->workflow_id}/draft", ['step' => 11, 'data' => ['first_name' => 'Intrus']])->assertForbidden();
        $this->assertSame($data, $progress->fresh()->drafts['11']['data']);
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
