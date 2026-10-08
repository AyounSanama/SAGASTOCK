<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Donor;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\HealthFacilityConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Niveau 7 (cahier §3) — L'Utilisateur Site saisit les entrées en stock et les inventaires, sans valider l'inventaire. */
class SiteUserStockEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_user_records_receipts_and_inventories_of_its_site_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        [$site, $project] = $this->facility($organization, 'YDE');
        [$otherSite] = $this->facility($organization, 'DLA');
        $product = $organization->products()->create(['code' => 'PARA', 'name' => 'Paracétamol 500 mg', 'product_type' => 'medicine', 'is_active' => true]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'site_user')->firstOrFail(), ['scope_type' => 'site', 'scope_id' => $site->id]);
        Sanctum::actingAs($user);
        $api = "/api/v1/organizations/{$organization->id}";

        $receipt = fn (Site $target, string $reference) => $this->postJson("$api/receipts", [
            'site_id' => $target->id, 'reference' => $reference, 'received_on' => now()->toDateString(),
            'origin_type' => 'project', 'origin_project_id' => $project->id,
            'items' => [['product_id' => $product->id, 'batch_number' => 'LOT-1', 'expires_on' => now()->addYear()->toDateString(),
                'quantity_ordered' => 10, 'quantity_received' => 10, 'quantity_accepted' => 10]],
        ]);
        $id = $receipt($site, 'REC-1')->assertCreated()->json('receipt.id');
        $this->postJson("$api/receipts/$id/validate")->assertOk();
        $this->assertSame(404, $receipt($otherSite, 'REC-2')->status(), 'Site hors de son périmètre');

        $inventory = $this->postJson("$api/inventories", ['site_id' => $site->id, 'reference' => 'INV-1', 'inventory_type' => 'monthly', 'period_date' => now()->toDateString()])
            ->assertCreated()->json('inventory.id');
        $this->postJson("$api/inventories/$inventory/validate")->assertForbidden();
    }

    /** @return array{0: Site, 1: Project} */
    private function facility(Organization $organization, string $code): array
    {
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => Country::firstOrCreate(['iso2' => 'CM'], ['iso3' => 'CMR', 'name' => 'Cameroun'])->id,
            'code' => $code, 'name' => "Coordination $code", 'is_active' => true]);
        $project = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => "P-$code", 'name' => "Projet $code", 'status' => 'active', 'is_active' => true]);
        $project->donors()->attach(Donor::create(['organization_id' => $organization->id, 'code' => "D-$code", 'name' => "Bailleur $code", 'is_active' => true])->id);
        $facility = HealthFacility::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => "CSI-$code", 'name' => "CSI $code",
            'facility_type' => 'health_center', 'validation_status' => HealthFacility::STATUS_VALIDATED, 'is_active' => true]);
        $facility->projects()->sync([$project->id]);

        return [app(HealthFacilityConfigurationService::class)->ensurePrimarySite($facility), $project];
    }
}
