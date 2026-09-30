<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** AM-114 — Paramètres d'approvisionnement obligatoires (projet actif) et historisés. */
class ProjectSupplySettingsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->mission = Mission::create([
            'organization_id' => $this->organization->id,
            'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => 'MAROUA', 'name' => 'Coordination Maroua',
        ]);
        $this->coordination = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $this->coordination->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), [
            'scope_type' => 'mission', 'scope_id' => $this->mission->id,
        ]);
        Sanctum::actingAs($this->coordination);
    }

    private function url(?string $projectId = null): string
    {
        return "/api/v1/organizations/{$this->organization->id}/projects".($projectId ? "/$projectId" : '');
    }

    private function payload(array $overrides = []): array
    {
        return ['mission_id' => $this->mission->id, 'code' => 'NUT', 'name' => 'Projet Nutrition', ...$overrides];
    }

    public function test_an_active_project_requires_the_three_supply_settings_but_a_draft_does_not(): void
    {
        $this->postJson($this->url(), $this->payload(['status' => 'active']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order_period_months', 'delivery_lead_time_months', 'safety_stock_months'])
            ->assertJsonPath('errors.safety_stock_months.0', 'Le champ stock de sécurité est obligatoire pour un projet actif.');

        $id = $this->postJson($this->url(), $this->payload(['status' => 'draft']))->assertCreated()->json('project.id');
        $this->assertDatabaseCount('project_supply_settings_history', 0);

        // Activation bloquée tant que les paramètres manquent.
        $this->putJson($this->url($id), $this->payload(['status' => 'active']))->assertUnprocessable();
        $this->assertDatabaseHas('projects', ['id' => $id, 'status' => 'draft']);
    }

    public function test_each_supply_change_is_recorded_with_its_author_and_unchanged_saves_are_not(): void
    {
        $settings = ['order_period_months' => 1, 'delivery_lead_time_months' => 2, 'safety_stock_months' => 1];
        $id = $this->postJson($this->url(), $this->payload(['status' => 'active', ...$settings]))->assertCreated()->json('project.id');
        $this->assertDatabaseHas('project_supply_settings_history', ['project_id' => $id, 'delivery_lead_time_months' => 2, 'changed_by' => $this->coordination->id]);

        $this->putJson($this->url($id), $this->payload(['status' => 'active', 'name' => 'Renommé', ...$settings]))->assertOk();
        $this->assertDatabaseCount('project_supply_settings_history', 1);

        $this->putJson($this->url($id), $this->payload(['status' => 'active', ...$settings, 'safety_stock_months' => 3]))->assertOk();
        $this->assertDatabaseCount('project_supply_settings_history', 2);
        $this->assertDatabaseHas('project_supply_settings_history', ['project_id' => $id, 'safety_stock_months' => 3]);
    }
}
