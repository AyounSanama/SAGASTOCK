<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Country;
use App\Models\Donor;
use App\Models\Mission;
use App\Models\HealthFacility;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Niveau 7 — Origine des entrées en stock : couple ONG/Bailleur (projet de la FOSA) ou « Autre ». */
class StockOriginTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Site $site;

    private Project $gffo5;

    private Project $fh4;

    private Project $foreign;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        $this->mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::firstOrCreate(['iso2' => 'CM'], ['iso3' => 'CMR', 'name' => 'Cameroun'])->id, 'code' => 'YDE', 'name' => 'Coordination Yaoundé', 'is_active' => true]);
        $donorA = Donor::create(['organization_id' => $this->organization->id, 'code' => 'BA', 'name' => 'Bailleur A', 'is_active' => true]);
        $donorB = Donor::create(['organization_id' => $this->organization->id, 'code' => 'BB', 'name' => 'Bailleur B', 'is_active' => true]);
        $this->gffo5 = $this->project('GFFO5', $donorA);
        $this->fh4 = $this->project('FH4', $donorB);
        $this->foreign = $this->project('AUTRE', $donorA);
        $facility = HealthFacility::create(['organization_id' => $this->organization->id, 'code' => 'FOSA-001', 'name' => 'CSI de Nkolndongo', 'facility_type' => 'health_center']);
        $facility->projects()->attach([$this->gffo5->id, $this->fh4->id]);
        $this->site = Site::create(['health_facility_id' => $facility->id, 'code' => 'PHA', 'name' => 'Pharmacie', 'site_type' => 'pharmacy']);
        $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $role = Role::create(['code' => 'origin_tester', 'name' => 'Testeur', 'scope_type' => 'organization', 'is_system' => false, 'is_active' => true]);
        foreach (['stocks.view', 'stocks.manage', 'receipts.view', 'receipts.manage'] as $code) {
            $role->permissions()->attach(Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'stocks'])->id);
        }
        $this->user->roles()->attach($role, ['scope_type' => 'organization', 'scope_id' => $this->organization->id]);
        $this->product = $this->organization->products()->create(['code' => 'PARA', 'name' => 'Paracétamol 500 mg', 'product_type' => 'medicine', 'is_active' => true]);
    }

    private $product;

    private $mission;

    public function test_receipt_options_list_the_couples_of_the_facility(): void
    {
        Sanctum::actingAs($this->user);
        $origins = collect($this->getJson("/api/v1/organizations/{$this->organization->id}/receipts/options")->assertOk()->json('sites.0.origins'));
        $this->assertSame(['ONG Santé / Bailleur B · FH4', 'ONG Santé / Bailleur A · GFFO5'], $origins->pluck('label')->all());
        $this->assertNotContains($this->foreign->id, $origins->pluck('project_id'), 'Projet hors de la FOSA non proposé');
    }

    public function test_origin_is_required_and_checked(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson($this->url(), $this->payload([]))->assertUnprocessable()->assertJsonValidationErrors('origin_type');
        $this->postJson($this->url(), $this->payload(['origin_type' => 'other']))->assertUnprocessable()->assertJsonValidationErrors('origin_label');
        $this->postJson($this->url(), $this->payload(['origin_type' => 'project', 'origin_project_id' => $this->foreign->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('origin_project_id');
    }

    public function test_same_batch_number_from_two_couples_gives_two_separate_stocks(): void
    {
        Sanctum::actingAs($this->user);
        foreach ([[$this->gffo5, 'REC-A', 10], [$this->fh4, 'REC-B', 4]] as [$project, $reference, $quantity]) {
            $id = $this->postJson($this->url(), $this->payload(['origin_type' => 'project', 'origin_project_id' => $project->id], $reference, $quantity))
                ->assertCreated()->json('receipt.id');
            $this->postJson($this->url()."/$id/validate")->assertOk();
        }
        $batches = Batch::where('batch_number', 'LOT-1')->get()->keyBy('origin_project_id');
        $this->assertCount(2, $batches, 'Un lot par couple ONG/Bailleur');
        $this->assertDatabaseHas('stock_balances', ['site_id' => $this->site->id, 'batch_id' => $batches[$this->gffo5->id]->id, 'theoretical_quantity' => 10]);
        $this->assertDatabaseHas('stock_balances', ['site_id' => $this->site->id, 'batch_id' => $batches[$this->fh4->id]->id, 'theoretical_quantity' => 4]);
        $this->assertSame('project', Receipt::where('reference', 'REC-A')->value('origin_type'));

        // Une entrée « Autre » garde le nom du tiers et son propre lot.
        $this->postJson($this->url(), $this->payload(['origin_type' => 'other', 'origin_label' => 'Pharmacie régionale'], 'REC-C', 2))->assertCreated();
        $other = Batch::where('batch_number', 'LOT-1')->where('origin_type', 'other')->firstOrFail();
        $this->assertSame('Pharmacie régionale', $other->origin);
        $this->getJson("/api/v1/organizations/{$this->organization->id}/receipts")->assertOk()
            ->assertJsonFragment(['origin_display' => 'Autre · Pharmacie régionale'])
            ->assertJsonFragment(['origin_display' => 'ONG Santé / Bailleur A · GFFO5']);
    }

    public function test_web_form_offers_the_origin_per_site(): void
    {
        $this->actingAs($this->user)->get("/organizations/{$this->organization->id}/receipts")->assertOk()
            ->assertSee('Origine (couple ONG/Bailleur) *')->assertSee('ONG Santé / Bailleur A · GFFO5')->assertSee('Autre (fournisseur tiers)');
        $this->actingAs($this->user)->post("/organizations/{$this->organization->id}/receipts", [
            ...$this->payload([], 'REC-WEB'), 'origin_choice' => $this->gffo5->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->gffo5->id, Receipt::where('reference', 'REC-WEB')->value('origin_project_id'));
    }

    public function test_existing_stock_origins_are_listed_then_applied_only_on_request(): void
    {
        // FOSA à un seul projet : origine sûre. FOSA à deux projets : à décider.
        $single = HealthFacility::create(['organization_id' => $this->organization->id, 'code' => 'FOSA-002', 'name' => 'CMA', 'facility_type' => 'health_center']);
        $single->projects()->attach($this->gffo5->id);
        $singleSite = Site::create(['health_facility_id' => $single->id, 'code' => 'PHB', 'name' => 'Pharmacie B', 'site_type' => 'pharmacy']);
        $safe = Batch::create(['organization_id' => $this->organization->id, 'product_id' => $this->product->id, 'batch_number' => 'OLD-1', 'expires_on' => now()->addYear()]);
        $unsure = Batch::create(['organization_id' => $this->organization->id, 'product_id' => $this->product->id, 'batch_number' => 'OLD-2', 'expires_on' => now()->addYear()]);
        foreach ([[$singleSite, $safe], [$this->site, $unsure]] as [$site, $batch]) {
            \DB::table('stock_balances')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'organization_id' => $this->organization->id, 'site_id' => $site->id,
                'product_id' => $this->product->id, 'batch_id' => $batch->id, 'theoretical_quantity' => 5, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->artisan('pharmacare:stock-origins')->expectsOutputToContain('Simulation')->assertSuccessful();
        $this->assertSame('', $safe->fresh()->origin_key, 'La simulation ne modifie rien');

        $this->artisan('pharmacare:stock-origins --apply')->assertSuccessful();
        $this->assertSame($this->gffo5->id, $safe->fresh()->origin_project_id);
        $this->assertNull($unsure->fresh()->origin_project_id, 'Cas ambigu jamais deviné');
    }

    private function project(string $code, Donor $donor): Project
    {
        $project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => $code, 'name' => 'Projet '.$code, 'status' => 'active', 'is_active' => true]);
        $project->donors()->attach($donor->id);

        return $project;
    }

    private function url(): string
    {
        return "/api/v1/organizations/{$this->organization->id}/receipts";
    }

    private function payload(array $origin, string $reference = 'REC-1', float $quantity = 10): array
    {
        return [
            'site_id' => $this->site->id, 'reference' => $reference, 'received_on' => now()->toDateString(), ...$origin,
            'items' => [['product_id' => $this->product->id, 'batch_number' => 'LOT-1', 'expires_on' => now()->addYear()->toDateString(),
                'quantity_ordered' => $quantity, 'quantity_received' => $quantity, 'quantity_accepted' => $quantity]],
        ];
    }
}
