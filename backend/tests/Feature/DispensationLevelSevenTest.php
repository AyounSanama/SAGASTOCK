<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Country;
use App\Models\Donor;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Niveau 7 — Dispensation : couple ONG/Bailleur, destinations de sortie, date d'ordonnance du jour. */
class DispensationLevelSevenTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Site $site;

    private Project $gffo5;

    private Project $fh4;

    private Product $product;

    private array $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        $mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::firstOrCreate(['iso2' => 'CM'], ['iso3' => 'CMR', 'name' => 'Cameroun'])->id,
            'code' => 'YDE', 'name' => 'Coordination Yaoundé', 'is_active' => true]);
        foreach (['gffo5' => ['GFFO5', 'Bailleur A'], 'fh4' => ['FH4', 'Bailleur B']] as $property => [$code, $donorName]) {
            $this->$property = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $mission->id, 'code' => $code, 'name' => 'Projet '.$code, 'status' => 'active', 'is_active' => true]);
            $this->$property->donors()->attach(Donor::create(['organization_id' => $this->organization->id, 'code' => $code.'-D', 'name' => $donorName, 'is_active' => true])->id);
        }
        $facility = HealthFacility::create(['organization_id' => $this->organization->id, 'code' => 'FOSA-001', 'name' => 'CSI de Nkolndongo', 'facility_type' => 'health_center']);
        $facility->projects()->attach([$this->gffo5->id, $this->fh4->id]);
        $this->site = Site::create(['organization_id' => $this->organization->id, 'health_facility_id' => $facility->id, 'code' => 'PHA', 'name' => 'Pharmacie', 'site_type' => 'stock_and_dispensing']);
        $this->product = Product::create(['organization_id' => $this->organization->id, 'code' => 'PARA', 'name' => 'Paracétamol', 'product_type' => 'medicine', 'is_active' => true]);

        $role = Role::create(['code' => 'care_test', 'name' => 'Soins']);
        $role->permissions()->attach(collect(['patients.view', 'patients.manage', 'prescriptions.view', 'prescriptions.manage', 'dispensations.view', 'dispensations.manage', 'dispensing.view'])
            ->map(fn ($code) => Permission::firstOrCreate(['code' => $code], ['name' => $code])->id));
        $user = User::factory()->create(['is_active' => true, 'organization_id' => $this->organization->id]);
        $user->roles()->attach($role, ['scope_type' => 'organization', 'scope_id' => $this->organization->id]);
        Sanctum::actingAs($user);
        $this->patient = $this->postJson($this->url('patients'), ['code' => 'P001', 'first_name' => 'Marie', 'last_name' => 'Test', 'sex' => 'female'])->assertCreated()->json('patient');
    }

    public function test_dispensation_uses_only_the_stock_of_the_chosen_couple(): void
    {
        // Le lot de FH4 périme plus tôt : sans couple, le FEFO le prendrait.
        $gffo5Lot = $this->lot('LOT-A', $this->gffo5, 6, 10);
        $this->lot('LOT-B', $this->fh4, 2, 10);

        $this->postJson($this->url('dispensations'), $this->dispensation(['origin_project_id' => null, 'origin_type' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('origin_type');

        $this->postJson($this->url('dispensations'), $this->dispensation(['quantity' => 4]))->assertCreated()
            ->assertJsonPath('dispensation.items.0.batch.batch_number', 'LOT-A')
            ->assertJsonPath('dispensation.origin_project_id', $this->gffo5->id);
        $this->assertSame(6.0, (float) StockBalance::where('batch_id', $gffo5Lot->id)->value('theoretical_quantity'));

        // Plus que le stock du couple : refusé, même si l'autre couple en a.
        $this->postJson($this->url('dispensations'), $this->dispensation(['reference' => 'DIS-2', 'quantity' => 12]))
            ->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_other_origin_uses_only_third_party_stock(): void
    {
        $this->lot('LOT-A', $this->gffo5, 2, 10);
        $third = $this->lot('LOT-T', null, 6, 5);

        $this->postJson($this->url('dispensations'), $this->dispensation(['origin_type' => 'other', 'origin_project_id' => null, 'quantity' => 3]))->assertCreated()
            ->assertJsonPath('dispensation.items.0.batch.batch_number', 'LOT-T')
            ->assertJsonPath('dispensation.origin_type', 'other');
        $this->assertSame(2.0, (float) StockBalance::where('batch_id', $third->id)->value('theoretical_quantity'));
        // Le stock du couple ne complète jamais celui du tiers.
        $this->postJson($this->url('dispensations'), $this->dispensation(['reference' => 'DIS-2', 'origin_type' => 'other', 'origin_project_id' => null, 'quantity' => 4]))
            ->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_service_return_and_expired_destinations(): void
    {
        $lot = $this->lot('LOT-A', $this->gffo5, 6, 10);
        $expired = $this->lot('LOT-OLD', $this->gffo5, -1, 3);

        // Service hospitalier : nom du service obligatoire, sans patient.
        $this->postJson($this->url('dispensations'), $this->dispensation(['destination_type' => 'hospital_service', 'patient_id' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('destination_name');
        $this->postJson($this->url('dispensations'), $this->dispensation(['destination_type' => 'hospital_service', 'destination_name' => 'Maternité', 'patient_id' => null, 'quantity' => 2]))
            ->assertCreated()->assertJsonPath('dispensation.patient_id', null);

        // Retour pharmacie ONG : mouvement « return_out ».
        $this->postJson($this->url('dispensations'), $this->dispensation(['reference' => 'RET-1', 'destination_type' => 'ngo_return', 'patient_id' => null, 'quantity' => 1]))->assertCreated();
        $this->assertDatabaseHas('stock_movements', ['batch_id' => $lot->id, 'movement_type' => 'return_out']);

        // Périmés/détériorés : lot choisi obligatoire, le lot périmé peut sortir.
        $this->postJson($this->url('dispensations'), $this->dispensation(['reference' => 'PER-1', 'destination_type' => 'expired_damaged', 'patient_id' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.batch_id');
        $this->postJson($this->url('dispensations'), $this->dispensation(['reference' => 'PER-2', 'destination_type' => 'expired_damaged', 'patient_id' => null, 'quantity' => 3, 'batch_id' => $expired->id]))
            ->assertCreated();
        $this->assertDatabaseHas('stock_movements', ['batch_id' => $expired->id, 'movement_type' => 'expiry']);

        // Jamais de lot périmé pour un patient.
        $this->postJson($this->url('dispensations'), $this->dispensation(['reference' => 'DIS-OLD', 'batch_id' => $expired->id, 'quantity' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_options_offer_couples_destinations_and_lots(): void
    {
        $this->lot('LOT-A', $this->gffo5, 6, 10);
        $options = $this->getJson($this->url('dispensations/options'))->assertOk();
        $this->assertSame(['Patient', 'Service hospitalier', 'Périmés / détériorés', 'Retour pharmacie ONG'], collect($options->json('destinations'))->pluck('label')->all());
        $this->assertCount(2, $options->json('sites.0.origins'));
        $this->assertSame('LOT-A', $options->json('batches.0.batch_number'));
    }

    public function test_prescription_date_is_today(): void
    {
        $prescription = fn (string $date, ?string $clientReference = null) => $this->postJson($this->url('prescriptions'), array_filter([
            'patient_id' => $this->patient['id'], 'site_id' => $this->site->id, 'reference' => 'ORD-'.$date.$clientReference, 'client_reference' => $clientReference,
            'prescribed_on' => $date, 'prescriber_name' => 'Dr Test',
            'items' => [['product_id' => $this->product->id, 'quantity_prescribed' => 2, 'dosage' => '1 cp', 'frequency' => '2/j', 'duration' => '3 j']],
        ]));
        $prescription(today()->subDay()->toDateString())->assertUnprocessable()->assertJsonValidationErrors('prescribed_on');
        $prescription(today()->toDateString())->assertCreated();
        // Saisie hors ligne : date de saisie sur le téléphone (7 jours au plus).
        $prescription(today()->subDays(2)->toDateString(), fake()->uuid())->assertCreated();
    }

    /** Lot d'un couple, ou d'un tiers (« Autre ») sans projet. */
    private function lot(string $number, ?Project $project, int $months, float $quantity): Batch
    {
        $batch = Batch::create(['organization_id' => $this->organization->id, 'product_id' => $this->product->id, 'batch_number' => $number,
            'expires_on' => $months < 0 ? now()->subMonths(-$months) : now()->addMonths($months), 'status' => 'available',
            'origin_type' => $project ? 'project' : 'other', 'origin_project_id' => $project?->id,
            'origin_key' => $project ? 'project:'.$project->id : 'other:tiers']);
        StockBalance::create(['organization_id' => $this->organization->id, 'site_id' => $this->site->id, 'product_id' => $this->product->id,
            'batch_id' => $batch->id, 'theoretical_quantity' => $quantity, 'reserved_quantity' => 0]);

        return $batch;
    }

    private function dispensation(array $overrides): array
    {
        $item = ['product_id' => $this->product->id, 'quantity' => $overrides['quantity'] ?? 4];
        if (isset($overrides['batch_id'])) {
            $item['batch_id'] = $overrides['batch_id'];
        }

        return array_filter([
            'reference' => $overrides['reference'] ?? 'DIS-1', 'site_id' => $this->site->id, 'dispensed_at' => now()->toISOString(),
            'patient_id' => array_key_exists('patient_id', $overrides) ? $overrides['patient_id'] : $this->patient['id'],
            'destination_type' => $overrides['destination_type'] ?? 'patient', 'destination_name' => $overrides['destination_name'] ?? null,
            'origin_type' => array_key_exists('origin_type', $overrides) ? $overrides['origin_type'] : 'project',
            'origin_project_id' => array_key_exists('origin_project_id', $overrides) ? $overrides['origin_project_id'] : $this->gffo5->id,
            'items' => [$item],
        ], fn ($value) => $value !== null);
    }

    private function url(string $path): string
    {
        return "/api/v1/organizations/{$this->organization->id}/$path";
    }
}
