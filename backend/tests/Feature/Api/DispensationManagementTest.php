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

    private function context(): array
    {
        $organization = Organization::create(['code' => 'CARE', 'name' => 'PharmaCare ONG']);
        $facility = HealthFacility::create(['organization_id' => $organization->id, 'code' => 'FOSA', 'name' => 'Centre de santé', 'facility_type' => 'clinic']);
        $site = Site::create(['organization_id' => $organization->id, 'health_facility_id' => $facility->id, 'code' => 'PHARMA', 'name' => 'Pharmacie', 'site_type' => 'stock_and_dispensing']);
        $product = Product::create(['organization_id' => $organization->id, 'code' => 'PARA', 'name' => 'Paracétamol', 'product_type' => 'medicine']);
        $early = Batch::create(['organization_id' => $organization->id, 'product_id' => $product->id, 'batch_number' => 'EARLY', 'expires_on' => now()->addMonths(3), 'status' => 'available']);
        $late = Batch::create(['organization_id' => $organization->id, 'product_id' => $product->id, 'batch_number' => 'LATE', 'expires_on' => now()->addMonths(8), 'status' => 'available']);
        foreach ([[$early, 5], [$late, 5]] as [$batch, $quantity]) StockBalance::create(['organization_id' => $organization->id, 'site_id' => $site->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'theoretical_quantity' => $quantity, 'reserved_quantity' => 0]);
        $permissions = collect(['patients.view','patients.manage','prescriptions.view','prescriptions.manage','prescriptions.validate','dispensations.view','dispensations.manage','dispensing.view'])->map(fn ($code) => Permission::firstOrCreate(['code' => $code], ['name' => $code]));
        $role = Role::create(['code' => 'care_test', 'name' => 'Soins']); $role->permissions()->attach($permissions);
        $user = User::factory()->create(['is_active' => true, 'organization_id' => $organization->id]);
        $user->roles()->attach($role, ['scope_type' => 'organization', 'scope_id' => $organization->id]); Sanctum::actingAs($user);
        return compact('organization', 'facility', 'site', 'product', 'early', 'late', 'user', 'role');
    }

    private function patient(array $c): array
    {
        return $this->postJson("/api/v1/organizations/{$c['organization']->id}/patients", ['code' => 'P001', 'first_name' => 'Marie', 'last_name' => 'Test', 'sex' => 'female', 'allergies' => 'Pénicilline'])->assertCreated()->json('patient');
    }

    private function prescription(array $c, array $patient, string $reference = 'ORD001', float $quantity = 8): array
    {
        return $this->postJson("/api/v1/organizations/{$c['organization']->id}/prescriptions", [
            'patient_id' => $patient['id'], 'site_id' => $c['site']->id, 'reference' => $reference,
            'prescribed_on' => today()->toDateString(), 'prescriber_name' => 'Dr Test', 'service_origin' => 'Médecine générale',
            'items' => [['product_id' => $c['product']->id, 'quantity_prescribed' => $quantity, 'dosage' => '1 comprimé', 'frequency' => 'Deux fois par jour', 'duration' => '4 jours']],
        ])->assertCreated()->json('prescription');
    }

    private function approve(array $c, array $rx): void
    {
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/prescriptions/{$rx['id']}/validate", [
            'decision' => 'approve', 'protocol_confirmed' => true, 'dosage_confirmed' => true,
            'contraindications_checked' => true, 'clinical_validation_notes' => 'Prescription conforme.',
        ])->assertOk()->assertJsonPath('prescription.status', 'validated');
    }

    public function test_clinical_validation_and_total_fefo_dispensation(): void
    {
        $c = $this->context(); $patient = $this->patient($c); $rx = $this->prescription($c, $patient); $this->approve($c, $rx);
        $uuid = fake()->uuid();
        $payload = ['offline_uuid' => $uuid, 'reference' => 'DIS001', 'patient_id' => $patient['id'], 'prescription_id' => $rx['id'], 'site_id' => $c['site']->id, 'dispensed_at' => now()->toISOString(), 'items' => [['prescription_item_id' => $rx['items'][0]['id'], 'product_id' => $c['product']->id, 'quantity' => 8]]];
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/dispensations", $payload)->assertCreated()->assertJsonPath('dispensation.status', 'validated')->assertJsonPath('dispensation.items.0.batch.batch_number', 'EARLY');
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/dispensations", $payload)->assertOk()->assertJsonPath('dispensation.offline_uuid', $uuid);
        $this->assertDatabaseHas('stock_balances', ['batch_id' => $c['early']->id, 'theoretical_quantity' => 0]);
        $this->assertDatabaseHas('stock_balances', ['batch_id' => $c['late']->id, 'theoretical_quantity' => 2]);
        $this->assertDatabaseHas('prescriptions', ['id' => $rx['id'], 'status' => 'dispensed']);
    }

    public function test_clinical_rejection_requires_reason_and_all_checks(): void
    {
        $c = $this->context(); $patient = $this->patient($c); $rx = $this->prescription($c, $patient);
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/prescriptions/{$rx['id']}/validate", ['decision' => 'approve', 'protocol_confirmed' => true, 'dosage_confirmed' => false, 'contraindications_checked' => true])->assertUnprocessable();
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/prescriptions/{$rx['id']}/validate", ['decision' => 'reject', 'rejection_reason' => 'Posologie incompatible avec le dossier clinique'])->assertOk()->assertJsonPath('prescription.status', 'rejected');
        $this->assertDatabaseHas('audit_logs', ['event' => 'prescription.rejected']);
    }

    public function test_partial_stockout_return_and_longitudinal_history(): void
    {
        $c = $this->context(); $patient = $this->patient($c); $rx = $this->prescription($c, $patient, 'ORD-PART', 15); $this->approve($c, $rx);
        $disp = $this->postJson("/api/v1/organizations/{$c['organization']->id}/dispensations", ['offline_uuid' => fake()->uuid(), 'reference' => 'DIS-PART', 'patient_id' => $patient['id'], 'prescription_id' => $rx['id'], 'site_id' => $c['site']->id, 'allow_partial' => true, 'dispensed_at' => now()->toISOString(), 'items' => [['prescription_item_id' => $rx['items'][0]['id'], 'product_id' => $c['product']->id, 'quantity' => 15]]])->assertCreated()->assertJsonPath('dispensation.status', 'partial')->json('dispensation');
        $this->assertDatabaseHas('prescriptions', ['id' => $rx['id'], 'status' => 'partially_dispensed']);
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/dispensations", ['offline_uuid' => fake()->uuid(), 'reference' => 'DIS-RUPTURE', 'patient_id' => $patient['id'], 'prescription_id' => $rx['id'], 'site_id' => $c['site']->id, 'allow_partial' => true, 'dispensed_at' => now()->toISOString(), 'items' => [['prescription_item_id' => $rx['items'][0]['id'], 'product_id' => $c['product']->id, 'quantity' => 5]]])->assertCreated()->assertJsonPath('dispensation.status', 'stockout');
        $this->assertDatabaseHas('prescriptions', ['id' => $rx['id'], 'status' => 'waiting_stock']);
        $line = collect($disp['items'])->firstWhere('batch.batch_number', 'EARLY');
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/dispensations/{$disp['id']}/return", ['reason' => 'Traitement interrompu sur décision clinique', 'items' => [['dispensation_item_id' => $line['id'], 'quantity' => 2]]])->assertOk()->assertJsonPath('dispensation.status', 'partially_returned');
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'return_in', 'reference_type' => 'dispensation_return']);
        $this->getJson("/api/v1/organizations/{$c['organization']->id}/patients/{$patient['id']}/history")->assertOk()->assertJsonCount(1, 'prescriptions')->assertJsonCount(2, 'dispensations');
        $this->actingAs($c['user'])->get('/dispensations')->assertOk()->assertSee('Ordonnances et validation clinique')->assertSee('DIS-PART');
    }

    public function test_fosa_account_cannot_access_patients_from_another_site(): void
    {
        $c = $this->context();
        $otherFacility = HealthFacility::create([
            'organization_id' => $c['organization']->id,
            'code' => 'FOSA-OTHER',
            'name' => 'Centre hors périmètre',
            'facility_type' => 'clinic',
        ]);
        $otherSite = Site::create([
            'organization_id' => $c['organization']->id,
            'health_facility_id' => $otherFacility->id,
            'code' => 'PHARMA-OTHER',
            'name' => 'Pharmacie hors périmètre',
            'site_type' => 'stock_and_dispensing',
        ]);
        $fosaUser = User::factory()->create([
            'is_active' => true,
            'organization_id' => $c['organization']->id,
        ]);
        $siteRole = Role::create(['code' => 'site_admin', 'name' => 'Gérant FOSA']);
        $siteRole->permissions()->attach($c['role']->permissions()->pluck('permissions.id'));
        $fosaUser->roles()->attach($siteRole, [
            'scope_type' => 'site',
            'scope_id' => $c['site']->id,
        ]);
        Sanctum::actingAs($fosaUser);

        $this->getJson("/api/v1/organizations/{$c['organization']->id}/dispensations/options")
            ->assertOk()
            ->assertJsonCount(1, 'sites')
            ->assertJsonPath('sites.0.id', $c['site']->id);

        $this->postJson("/api/v1/organizations/{$c['organization']->id}/patients", [
            'code' => 'P-HORS-SCOPE',
            'first_name' => 'Patient',
            'last_name' => 'Interdit',
            'site_id' => $otherSite->id,
        ])->assertNotFound();
    }

    public function test_offline_patient_identifier_is_preserved_for_queued_dependencies(): void
    {
        $c = $this->context();
        $offlineId = fake()->uuid();

        $this->postJson("/api/v1/organizations/{$c['organization']->id}/patients", [
            'id' => $offlineId,
            'client_reference' => $offlineId,
            'site_id' => $c['site']->id,
            'code' => 'P-OFFLINE',
            'first_name' => 'Patient',
            'last_name' => 'Hors Ligne',
        ])->assertCreated()->assertJsonPath('patient.id', $offlineId);

        $this->assertDatabaseHas('patients', [
            'id' => $offlineId,
            'site_id' => $c['site']->id,
        ]);
    }

    public function test_offline_replay_cannot_read_another_fosa_prescription_or_dispensation(): void
    {
        $c = $this->context();
        $patient = $this->patient($c);
        $clientReference = fake()->uuid();
        $prescription = $this->postJson("/api/v1/organizations/{$c['organization']->id}/prescriptions", [
            'client_reference' => $clientReference,
            'patient_id' => $patient['id'],
            'site_id' => $c['site']->id,
            'reference' => 'ORD-REPLAY',
            'prescribed_on' => today()->toDateString(),
            'prescriber_name' => 'Dr Scope',
            'items' => [[
                'product_id' => $c['product']->id,
                'quantity_prescribed' => 1,
                'dosage' => '1 comprimé',
                'frequency' => 'Une fois par jour',
                'duration' => '1 jour',
            ]],
        ])->assertCreated()->json('prescription');
        $this->approve($c, $prescription);

        $offlineUuid = fake()->uuid();
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/dispensations", [
            'offline_uuid' => $offlineUuid,
            'reference' => 'DIS-REPLAY',
            'patient_id' => $patient['id'],
            'prescription_id' => $prescription['id'],
            'site_id' => $c['site']->id,
            'dispensed_at' => now()->toISOString(),
            'items' => [[
                'prescription_item_id' => $prescription['items'][0]['id'],
                'product_id' => $c['product']->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $otherFacility = HealthFacility::create([
            'organization_id' => $c['organization']->id,
            'code' => 'FOSA-REPLAY',
            'name' => 'Autre FOSA',
            'facility_type' => 'clinic',
        ]);
        $otherSite = Site::create([
            'organization_id' => $c['organization']->id,
            'health_facility_id' => $otherFacility->id,
            'code' => 'SITE-REPLAY',
            'name' => 'Autre pharmacie',
            'site_type' => 'dispensing',
        ]);
        $otherUser = User::factory()->create([
            'is_active' => true,
            'organization_id' => $c['organization']->id,
        ]);
        $siteRole = Role::firstOrCreate(
            ['code' => 'site_admin'],
            ['name' => 'Gérant FOSA'],
        );
        $siteRole->permissions()->syncWithoutDetaching(
            $c['role']->permissions()->pluck('permissions.id'),
        );
        $otherUser->roles()->attach($siteRole, [
            'scope_type' => 'site',
            'scope_id' => $otherSite->id,
        ]);
        Sanctum::actingAs($otherUser);

        $this->postJson("/api/v1/organizations/{$c['organization']->id}/prescriptions", [
            'client_reference' => $clientReference,
        ])->assertNotFound();
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/dispensations", [
            'offline_uuid' => $offlineUuid,
        ])->assertNotFound();
    }
}
