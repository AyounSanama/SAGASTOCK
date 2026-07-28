<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\HealthFacility;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $organization = Organization::create(['code' => 'STK', 'name' => 'Stock ONG']);
        $facility = HealthFacility::create(['organization_id' => $organization->id, 'code' => 'FOSA', 'name' => 'Hôpital', 'facility_type' => 'hospital']);
        $source = Site::create(['health_facility_id' => $facility->id, 'code' => 'CENTRAL', 'name' => 'Magasin central', 'site_type' => 'stock']);
        $destination = Site::create(['health_facility_id' => $facility->id, 'code' => 'PHARMA', 'name' => 'Pharmacie', 'site_type' => 'stock_and_dispensing']);
        $product = Product::create(['organization_id' => $organization->id, 'code' => 'PARA', 'name' => 'Paracétamol', 'product_type' => 'medicine']);
        $early = Batch::create(['organization_id' => $organization->id, 'product_id' => $product->id, 'batch_number' => 'EARLY', 'expires_on' => now()->addMonths(6), 'status' => 'available']);
        $late = Batch::create(['organization_id' => $organization->id, 'product_id' => $product->id, 'batch_number' => 'LATE', 'expires_on' => now()->addYear(), 'status' => 'available']);

        return compact('organization', 'facility', 'source', 'destination', 'product', 'early', 'late');
    }

    private function login(): User
    {
        $permissions = collect(['stocks.view', 'stocks.manage', 'stocks.adjust', 'transfers.manage', 'receipts.manage'])->map(fn ($code) => Permission::create(['code' => $code, 'name' => $code]));
        $role = Role::create(['code' => 'stock_admin', 'name' => 'Gestionnaire stock']);
        $role->permissions()->attach($permissions);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role, ['scope_type' => 'platform']);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_immutable_ledger_balances_fefo_and_compensation(): void
    {
        $d = $this->data();
        $this->login();
        foreach ([[$d['early'], 10], [$d['late'], 20]] as [$batch, $quantity]) {
            $this->postJson("/api/v1/organizations/{$d['organization']->id}/stocks/movements", [
                'site_id' => $d['source']->id, 'batch_id' => $batch->id, 'movement_type' => 'receipt', 'quantity' => $quantity,
            ])->assertCreated();
        }
        $issue = $this->postJson("/api/v1/organizations/{$d['organization']->id}/stocks/movements", [
            'site_id' => $d['source']->id, 'batch_id' => $d['early']->id, 'movement_type' => 'issue', 'quantity' => 3,
        ])->assertCreated()->json('movement');
        $this->getJson("/api/v1/organizations/{$d['organization']->id}/stocks/balances?available_only=1")
            ->assertOk()->assertJsonFragment(['theoretical_quantity' => '7.0000']);
        $this->getJson("/api/v1/organizations/{$d['organization']->id}/stocks/fefo?site_id={$d['source']->id}&product_id={$d['product']->id}&quantity=15")
            ->assertOk()->assertJsonPath('allocations.0.batch.batch_number', 'EARLY')->assertJsonPath('suggested_quantity', 15);
        $this->postJson("/api/v1/organizations/{$d['organization']->id}/stocks/movements/{$issue['id']}/compensate", ['reason' => 'Sortie enregistrée par erreur'])
            ->assertCreated()->assertJsonPath('movement.compensates_movement_id', $issue['id']);
        $movement = StockMovement::findOrFail($issue['id']);
        $this->expectException(\LogicException::class);
        $movement->update(['reason' => 'Altération interdite']);
    }

    public function test_transfer_dispatch_and_receipt_create_traceable_movements(): void
    {
        $d = $this->data();
        $this->login();
        $this->postJson("/api/v1/organizations/{$d['organization']->id}/stocks/movements", [
            'site_id' => $d['source']->id, 'batch_id' => $d['early']->id, 'movement_type' => 'opening', 'quantity' => 50,
        ])->assertCreated();
        $transfer = $this->postJson("/api/v1/organizations/{$d['organization']->id}/stocks/transfers", [
            'reference' => 'TR_001', 'source_site_id' => $d['source']->id, 'destination_site_id' => $d['destination']->id,
            'items' => [['batch_id' => $d['early']->id, 'quantity' => 12]],
        ])->assertCreated()->json('transfer');
        $this->postJson("/api/v1/organizations/{$d['organization']->id}/stocks/transfers/{$transfer['id']}/dispatch")
            ->assertOk()->assertJsonPath('transfer.status', 'in_transit');
        $this->postJson("/api/v1/organizations/{$d['organization']->id}/stocks/transfers/{$transfer['id']}/receive", [
            'items' => [['id' => $transfer['items'][0]['id'], 'quantity_received' => 11, 'discrepancy_reason' => 'Une unité endommagée']],
        ])->assertOk()->assertJsonPath('transfer.status', 'received');
        $this->assertDatabaseHas('stock_balances', ['site_id' => $d['source']->id, 'theoretical_quantity' => 38]);
        $this->assertDatabaseHas('stock_balances', ['site_id' => $d['destination']->id, 'theoretical_quantity' => 11]);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'transfer', 'movement_type' => 'transfer_out']);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'transfer', 'movement_type' => 'transfer_in']);
    }

    public function test_negative_stock_and_cross_organization_access_are_blocked(): void
    {
        $d = $this->data();
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Autre ONG']);
        $user = $this->login();
        $user->roles()->updateExistingPivot($user->roles()->first()->id, ['scope_type' => 'organization', 'scope_id' => $d['organization']->id]);
        $this->postJson("/api/v1/organizations/{$d['organization']->id}/stocks/movements", [
            'site_id' => $d['source']->id, 'batch_id' => $d['early']->id, 'movement_type' => 'issue', 'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->getJson("/api/v1/organizations/{$other->id}/stocks/balances")->assertNotFound();
    }

    public function test_web_stock_interface_is_connected_to_the_ledger(): void
    {
        $d = $this->data();
        $user = $this->login();
        $this->actingAs($user)->get("/organizations/{$d['organization']->id}/stocks")
            ->assertOk()->assertSee('Stocks et mouvements')->assertSee('registre immuable')->assertSee('pharmacare-logo.png');
        $this->actingAs($user)->post("/organizations/{$d['organization']->id}/stocks/movements", [
            'site_id' => $d['source']->id, 'batch_id' => $d['early']->id, 'movement_type' => 'receipt', 'quantity' => 25,
        ])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($user)->get("/organizations/{$d['organization']->id}/stocks")
            ->assertOk()->assertSee('Paracétamol')->assertSee('25.0000');
    }

    public function test_receipt_reports_discrepancies_and_credits_only_accepted_quantity(): void
    {
        $d = $this->data();
        $this->login();
        $receipt = $this->postJson("/api/v1/organizations/{$d['organization']->id}/receipts", [
            'site_id' => $d['source']->id, 'reference' => 'REC_001', 'order_reference' => 'CMD_001',
            'received_on' => now()->toDateString(), 'items' => [[
                'batch_id' => $d['early']->id, 'quantity_ordered' => 20, 'quantity_received' => 18,
                'quantity_accepted' => 17, 'quantity_rejected' => 1, 'discrepancy_reason' => 'Deux manquants et une boîte endommagée',
            ]],
        ])->assertCreated()->assertJsonPath('receipt.status', 'draft')->json('receipt');
        $this->postJson("/api/v1/organizations/{$d['organization']->id}/receipts/{$receipt['id']}/validate")
            ->assertOk()->assertJsonPath('receipt.status', 'validated');
        $this->assertDatabaseHas('stock_balances', ['site_id' => $d['source']->id, 'batch_id' => $d['early']->id, 'theoretical_quantity' => 17]);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'receipt', 'reference_id' => $receipt['id'], 'quantity' => 17]);
        $this->postJson("/api/v1/organizations/{$d['organization']->id}/receipts/{$receipt['id']}/validate")->assertUnprocessable();
    }
}
