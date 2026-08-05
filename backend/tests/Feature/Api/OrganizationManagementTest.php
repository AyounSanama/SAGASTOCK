<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $view = Permission::create(['code' => 'organizations.view', 'name' => 'Consulter les organisations']);
        $manage = Permission::create(['code' => 'organizations.manage', 'name' => 'Gérer les organisations']);
        $role = Role::create(['code' => 'owner', 'name' => 'Propriétaire plateforme']);
        $role->permissions()->attach([$view->id, $manage->id]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id, ['scope_type' => 'platform']);
        return $user;
    }

    public function test_authorized_user_can_manage_an_organization(): void
    {
        Sanctum::actingAs($this->administrator());
        $created = $this->postJson('/api/v1/organizations', [
            'code' => 'ONG_CM', 'name' => 'ONG Cameroun', 'country_code' => 'CM', 'is_active' => true,
        ])->assertCreated()->assertJsonPath('organization.code', 'ONG_CM');

        $id = $created->json('organization.id');
        $this->getJson('/api/v1/organizations?search=Cameroun')->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/v1/organizations/{$id}", [
            'code' => 'ONG_CM', 'name' => 'ONG Cameroun mise à jour', 'is_active' => false,
        ])->assertOk()->assertJsonPath('organization.is_active', false);
        $this->deleteJson("/api/v1/organizations/{$id}")->assertNoContent();

        $this->assertSoftDeleted('organizations', ['id' => $id]);
        $this->getJson('/api/v1/organizations/archived')->assertOk()->assertJsonFragment(['id' => $id]);
        $this->postJson("/api/v1/organizations/archived/{$id}/restore")->assertOk()->assertJsonPath('organization.is_active', true);
        $this->assertNotSoftDeleted('organizations', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'organization.created', 'auditable_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'organization.restored', 'auditable_id' => $id]);
    }

    public function test_unique_code_and_permissions_are_enforced(): void
    {
        Sanctum::actingAs($this->administrator());
        $payload = ['code' => 'UNIQUE', 'name' => 'Première organisation'];
        $this->postJson('/api/v1/organizations', $payload)->assertCreated();
        $this->postJson('/api/v1/organizations', [...$payload, 'name' => 'Doublon'])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create(['is_active' => true]));
        $this->getJson('/api/v1/organizations')->assertForbidden();
    }
}
