<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\HealthFacilityConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mot de passe généré à la création ou à la réinitialisation d'un compte :
 * montré une seule fois à l'administrateur (sinon le compte était
 * inutilisable), jamais quand il a lui-même saisi le mot de passe.
 */
class TemporaryPasswordDisclosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_password_is_shown_once_to_the_creator(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id, 'code' => 'YDE', 'name' => 'Coordination Yaoundé', 'is_active' => true]);
        $project = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'P1', 'name' => 'Projet']);
        $facility = HealthFacility::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'F1', 'name' => 'CSI',
            'facility_type' => 'health_center', 'validation_status' => HealthFacility::STATUS_VALIDATED, 'is_active' => true]);
        $facility->projects()->sync([$project->id]);
        $site = app(HealthFacilityConfigurationService::class)->ensurePrimarySite($facility);
        $admin = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'project_admin')->firstOrFail(), ['scope_type' => 'project', 'scope_id' => $project->id]);
        $siteUserRole = Role::where('code', 'site_user')->firstOrFail()->id;
        Sanctum::actingAs($admin);

        // Sans mot de passe saisi : généré, renvoyé une fois, et valable.
        $response = $this->postJson('/api/v1/users', ['name' => 'Agent Un', 'email' => 'agent1@example.test', 'role_id' => $siteUserRole, 'scope_type' => 'site', 'scope_id' => $site->id])
            ->assertCreated();
        $temporary = $response->json('temporary_password');
        $created = User::where('email', 'agent1@example.test')->firstOrFail();
        $this->assertNotEmpty($temporary);
        $this->assertTrue(Hash::check($temporary, $created->password));
        $this->assertTrue($created->must_change_password);
        $this->assertDatabaseMissing('audit_logs', ['new_values' => json_encode(['temporary_password' => $temporary])]);

        // Mot de passe saisi par l'administrateur : jamais renvoyé.
        $this->postJson('/api/v1/users', ['name' => 'Agent Deux', 'email' => 'agent2@example.test', 'role_id' => $siteUserRole, 'scope_type' => 'site', 'scope_id' => $site->id,
            'password' => 'Agent-Deux2026!', 'password_confirmation' => 'Agent-Deux2026!'])
            ->assertCreated()->assertJsonMissingPath('temporary_password');

        // Réinitialisation : nouveau mot de passe renvoyé une fois.
        $reset = $this->postJson("/api/v1/users/{$created->id}/reset-password")->assertOk()->json('temporary_password');
        $this->assertTrue(Hash::check($reset, $created->fresh()->password));
        $this->assertNotSame($temporary, $reset);
    }
}
