<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Mon profil (mobile) : mêmes règles que le Web, autorisé même en lecture seule. */
class ProfileUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_updates_own_profile_with_web_rules(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::factory()->create(['email' => 'pris@example.test', 'username' => 'pris']);
        $reader = User::factory()->create(['is_active' => true, 'must_change_password' => false, 'read_only' => true]);
        $reader->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'platform', 'scope_id' => null]);
        Sanctum::actingAs($reader);

        $this->putJson('/api/v1/auth/profile', [
            'first_name' => 'Awa', 'last_name' => 'Ndiaye', 'username' => 'A_Ndiaye', 'email' => 'awa@example.test', 'phone' => '+237600000000',
        ])->assertOk()->assertJsonPath('user.name', 'Awa Ndiaye')->assertJsonPath('user.username', 'a_ndiaye');
        $this->assertSame('+237600000000', $reader->fresh()->phone);
        $this->assertDatabaseHas('audit_logs', ['event' => 'profile.updated', 'user_id' => $reader->id]);

        // Ancien identifiant avec un point : conservé tel quel.
        $reader->update(['username' => 'a.ndiaye']);
        $this->putJson('/api/v1/auth/profile', ['first_name' => 'Awa', 'last_name' => 'Ndiaye', 'username' => 'a.ndiaye', 'email' => 'awa@example.test'])->assertOk();

        // Champs obligatoires et unicité, comme sur le Web.
        $this->putJson('/api/v1/auth/profile', ['first_name' => '', 'last_name' => 'N', 'username' => 'pris', 'email' => 'pris@example.test'])
            ->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'username', 'email']);
    }
}
