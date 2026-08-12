<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\HealthFacility;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockModuleCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $organization = Organization::create(['code' => 'STOCK_FINAL', 'name' => 'Stock final']);
        $facility = HealthFacility::create([
            'organization_id' => $organization->id, 'code' => 'FOSA',
            'name' => 'Hôpital', 'facility_type' => 'hospital',
        ]);
        $site = Site::create([
            'health_facility_id' => $facility->id, 'code' => 'MAG',
            'name' => 'Magasin', 'site_type' => 'stock',
        ]);
        $product = Product::create([
            'organization_id' => $organization->id, 'code' => 'AMOX',
            'name' => 'Amoxicilline', 'product_type' => 'medicine',
        ]);
        $batch = Batch::create([
            'organization_id' => $organization->id, 'product_id' => $product->id,
            'batch_number' => 'LOT-001', 'expires_on' => now()->addYear(),
            'status' => 'available',
        ]);
        $permissions = collect([
            'stocks.view', 'stocks.manage', 'stocks.adjust', 'catalog.view', 'batches.view', 'batches.manage',
        ])->map(fn (string $code) => Permission::firstOrCreate(['code' => $code], ['name' => $code]));
        $role = Role::create(['code' => 'stock_completion', 'name' => 'Gestionnaire stock']);
        $role->permissions()->attach($permissions->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role, ['scope_type' => 'platform']);

        return compact('organization', 'site', 'product', 'batch', 'user');
    }

    public function test_mobile_movement_replay_is_idempotent(): void
    {
        $data = $this->context();
        Sanctum::actingAs($data['user']);
        $payload = [
            'client_reference' => '123e4567-e89b-12d3-a456-426614174111',
            'site_id' => $data['site']->id,
            'batch_id' => $data['batch']->id,
            'movement_type' => 'opening',
            'quantity' => 25,
        ];

        $this->postJson("/api/v1/organizations/{$data['organization']->id}/stocks/movements", $payload)
            ->assertCreated();
        $this->postJson("/api/v1/organizations/{$data['organization']->id}/stocks/movements", $payload)
            ->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_balances', [
            'site_id' => $data['site']->id,
            'batch_id' => $data['batch']->id,
            'theoretical_quantity' => 25,
        ]);
    }

    public function test_archived_batches_are_filterable_and_restorable(): void
    {
        $data = $this->context();
        Sanctum::actingAs($data['user']);

        $this->deleteJson("/api/v1/organizations/{$data['organization']->id}/catalog/batches/{$data['batch']->id}")
            ->assertNoContent();
        $this->getJson("/api/v1/organizations/{$data['organization']->id}/catalog/batches?status=archived")
            ->assertOk()->assertJsonFragment(['batch_number' => 'LOT-001']);
        $this->postJson("/api/v1/organizations/{$data['organization']->id}/catalog/batches/archived/{$data['batch']->id}/restore")
            ->assertOk();
    }

    public function test_general_stock_route_opens_the_real_workspace(): void
    {
        $data = $this->context();

        $this->actingAs($data['user'])->get('/stocks')
            ->assertRedirect(route('organizations.stocks.index', $data['organization']));
    }
}
