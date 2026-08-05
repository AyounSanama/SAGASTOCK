<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\HealthFacility;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DispensationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_prescription_validation_and_fefo_dispensation_cycle(): void
    {
        $organization = Organization::create(['code' => 'CARE', 'name' => 'PharmaCare ONG']);
        $facility = HealthFacility::create(['organization_id' => $organization->id, 'code' => 'FOSA', 'name' => 'Centre de santé', 'facility_type' => 'clinic']);
        $site = Site::create(['health_facility_id' => $facility->id, 'code' => 'PHARMA', 'name' => 'Pharmacie', 'site_type' => 'stock_and_dispensing']);
        $product = Product::create(['organization_id' => $organization->id, 'code' => 'PARA', 'name' => 'Paracétamol', 'product_type' => 'medicine']);
        $early = Batch::create(['organization_id' => $organization->id, 'product_id' => $product->id, 'batch_number' => 'EARLY', 'expires_on' => now()->addMonths(3), 'status' => 'available']);
        $late = Batch::create(['organization_id' => $organization->id, 'product_id' => $product->id, 'batch_number' => 'LATE', 'expires_on' => now()->addMonths(8), 'status' => 'available']);
        foreach ([[$early, 5], [$late, 10]] as [$batch, $quantity]) StockBalance::create(['organization_id' => $organization->id, 'site_id' => $site->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'theoretical_quantity' => $quantity, 'reserved_quantity' => 0]);
        $permissions = collect(['patients.view','patients.manage','prescriptions.view','prescriptions.manage','prescriptions.validate','dispensations.view','dispensations.manage'])->map(fn ($code) => Permission::firstOrCreate(['code' => $code], ['name' => $code]));
        $role = Role::create(['code' => 'care_test', 'name' => 'Soins']); $role->permissions()->attach($permissions);
        $user = User::factory()->create(['is_active' => true]); $user->roles()->attach($role, ['scope_type' => 'organization', 'scope_id' => $organization->id]); Sanctum::actingAs($user);

        $patient = $this->postJson("/api/v1/organizations/{$organization->id}/patients", ['code' => 'P001', 'first_name' => 'Marie', 'last_name' => 'Test', 'sex' => 'female'])->assertCreated()->json('patient');
        $prescription = $this->postJson("/api/v1/organizations/{$organization->id}/prescriptions", ['patient_id' => $patient['id'], 'site_id' => $site->id, 'reference' => 'ORD001', 'prescribed_on' => today()->toDateString(), 'prescriber_name' => 'Dr Test', 'items' => [['product_id' => $product->id, 'quantity_prescribed' => 8, 'dosage' => '1 comprimé matin et soir']]])->assertCreated()->json('prescription');
        $this->postJson("/api/v1/organizations/{$organization->id}/prescriptions/{$prescription['id']}/validate")->assertOk()->assertJsonPath('prescription.status', 'validated');
        $this->postJson("/api/v1/organizations/{$organization->id}/dispensations", ['offline_uuid' => fake()->uuid(), 'reference' => 'DIS001', 'patient_id' => $patient['id'], 'prescription_id' => $prescription['id'], 'site_id' => $site->id, 'dispensed_at' => now()->toISOString(), 'items' => [['prescription_item_id' => $prescription['items'][0]['id'], 'product_id' => $product->id, 'quantity' => 8]]])->assertCreated()->assertJsonPath('dispensation.items.0.batch.batch_number', 'EARLY');
        $this->assertDatabaseHas('stock_balances', ['batch_id' => $early->id, 'theoretical_quantity' => 0]);
        $this->assertDatabaseHas('stock_balances', ['batch_id' => $late->id, 'theoretical_quantity' => 7]);
        $this->assertDatabaseHas('prescriptions', ['id' => $prescription['id'], 'status' => 'dispensed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'dispensation.validated']);
    }
}
