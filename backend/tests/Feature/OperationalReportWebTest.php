<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Donor;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\HealthFacilityConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Rapports : la page Web affiche les mêmes chiffres que l'écran mobile. */
class OperationalReportWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_report_matches_the_mobile_report_of_the_site(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => Country::firstOrCreate(['iso2' => 'CM'], ['iso3' => 'CMR', 'name' => 'Cameroun'])->id,
            'code' => 'YDE', 'name' => 'Coordination YDE', 'is_active' => true]);
        $project = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'P-YDE', 'name' => 'Projet YDE', 'status' => 'active', 'is_active' => true]);
        $project->donors()->attach(Donor::create(['organization_id' => $organization->id, 'code' => 'D-YDE', 'name' => 'Bailleur', 'is_active' => true])->id);
        $facility = HealthFacility::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'CSI-YDE', 'name' => 'CSI YDE',
            'facility_type' => 'health_center', 'validation_status' => HealthFacility::STATUS_VALIDATED, 'is_active' => true]);
        $facility->projects()->sync([$project->id]);
        $site = app(HealthFacilityConfigurationService::class)->ensurePrimarySite($facility);
        $product = $organization->products()->create(['code' => 'PARA', 'name' => 'Paracétamol 500 mg', 'product_type' => 'medicine', 'is_active' => true]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'site_admin')->firstOrFail(), ['scope_type' => 'site', 'scope_id' => $site->id]);

        Sanctum::actingAs($user);
        $id = $this->postJson("/api/v1/organizations/{$organization->id}/receipts", [
            'site_id' => $site->id, 'reference' => 'REC-1', 'received_on' => now()->toDateString(),
            'origin_type' => 'project', 'origin_project_id' => $project->id,
            'items' => [['product_id' => $product->id, 'batch_number' => 'LOT-1', 'expires_on' => now()->addYear()->toDateString(),
                'quantity_ordered' => 48, 'quantity_received' => 48, 'quantity_accepted' => 48]],
        ])->assertCreated()->json('receipt.id');
        $this->postJson("/api/v1/organizations/{$organization->id}/receipts/$id/validate")->assertOk();

        $api = $this->getJson("/api/v1/organizations/{$organization->id}/operational-report")->assertOk()->json();
        $this->assertSame(48.0, (float) $api['stock']['available_quantity']);
        $this->assertSame(1, $api['receipts']['validated']);

        $this->actingAs($user)->get('/reports')->assertOk()
            ->assertSee('Rapports opérationnels')
            ->assertSee('Quantité disponible')
            ->assertSee('<strong>48</strong>', false)
            ->assertSee('Propositions de commande')
            ->assertDontSee('Module en cours de développement');
    }
}
