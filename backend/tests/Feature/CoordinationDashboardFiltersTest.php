<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Donor;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Niveau 3 — Filtres « couple ONG/Bailleur » et « projet » du tableau de bord Coordination, badge de synchronisation. */
class CoordinationDashboardFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_restrict_projects_on_web_and_api(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id, 'code' => 'YDE', 'name' => 'Coordination Yaoundé', 'is_active' => true]);
        $donorA = Donor::create(['organization_id' => $organization->id, 'code' => 'BA', 'name' => 'Bailleur A', 'is_active' => true]);
        $donorB = Donor::create(['organization_id' => $organization->id, 'code' => 'BB', 'name' => 'Bailleur B', 'is_active' => true]);
        $gffo5 = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'GFFO5', 'name' => 'Projet A', 'status' => 'active']);
        $fh4 = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'FH4', 'name' => 'Projet B', 'status' => 'active']);
        $gffo5->donors()->sync([$donorA->id]);
        $fh4->donors()->sync([$donorB->id]);
        $coordination = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true, 'must_change_password' => false]);
        $coordination->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $mission->id]);

        $this->actingAs($coordination)->get('/dashboard')->assertOk()
            ->assertSee('Tous les couples ONG / Bailleur')->assertSee('ONG Santé / Bailleur A')->assertSee('Tous les projets')
            ->assertSee('GFFO5')->assertSee('FH4')
            ->assertSee('Aucune synchronisation');
        $this->actingAs($coordination)->get('/dashboard?donor_id='.$donorA->id)->assertOk()
            ->assertSee('<td><strong>GFFO5</strong>', false)->assertDontSee('<td><strong>FH4</strong>', false);

        Sanctum::actingAs($coordination);
        $this->getJson('/api/v1/coordination/dashboard?project_id='.$fh4->id)->assertOk()
            ->assertJsonCount(1, 'projects')->assertJsonPath('projects.0.code', 'FH4')
            ->assertJsonPath('filters.project_id', $fh4->id)
            ->assertJsonCount(2, 'filters.donors');
    }
}
