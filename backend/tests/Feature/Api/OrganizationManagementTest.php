<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Admin Sago (rôle officiel qui crée les organisations). */
    private function administrator(): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'sago_admin')->firstOrFail(), ['scope_type' => 'platform']);

        return $user;
    }

    private function creationPayload(string $code, string $name): array
    {
        $country = Country::firstOrCreate(['iso2' => 'CM'], ['name' => 'Cameroun', 'is_active' => true]);
        return [
            'code' => $code, 'name' => $name,
            'geographic_access_type' => 'single_country', 'country_ids' => [$country->id],
            'admin_first_name' => 'Admin', 'admin_last_name' => $code,
            'admin_email' => strtolower($code).'@example.test',
            'admin_username' => strtolower($code).'_admin',
        ];
    }

    public function test_authorized_user_can_manage_an_organization(): void
    {
        Sanctum::actingAs($this->administrator());
        $created = $this->postJson('/api/v1/organizations', $this->creationPayload('ONG_CM', 'ONG Cameroun'))
            ->assertCreated()->assertJsonPath('organization.code', 'ONG_CM');

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
        $payload = $this->creationPayload('UNIQUE', 'Première organisation');
        $this->postJson('/api/v1/organizations', $payload)->assertCreated();
        $this->postJson('/api/v1/organizations', [...$payload, 'name' => 'Doublon'])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create(['is_active' => true]));
        $this->getJson('/api/v1/organizations')->assertForbidden();
    }
}
