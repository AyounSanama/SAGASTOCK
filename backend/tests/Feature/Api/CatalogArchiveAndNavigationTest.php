<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogArchiveAndNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $permissions = collect([
            'catalog.view', 'catalog.manage', 'products.view', 'products.manage',
            'standard_lists.view', 'standard_lists.manage',
        ])->map(fn (string $code) => Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code],
        ));
        $role = Role::create(['code' => 'catalog_navigation_admin', 'name' => 'Administrateur catalogue']);
        $role->permissions()->attach($permissions->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role, ['scope_type' => 'platform']);

        return $user;
    }

    public function test_archived_records_are_listed_and_can_be_restored(): void
    {
        $organization = Organization::create(['code' => 'ARCH', 'name' => 'Catalogue archives']);
        Sanctum::actingAs($this->administrator());
        $reference = $organization->catalogReferences()->create([
            'reference_type' => 'category', 'code' => 'MED', 'name' => 'Medicaments',
        ]);
        $product = $organization->products()->create([
            'code' => 'PARA', 'name' => 'Paracetamol', 'product_type' => 'medicine',
        ]);
        $list = $organization->standardLists()->create([
            'code' => 'ESS', 'name' => 'Liste essentielle', 'scope_type' => 'organization',
            'scope_id' => $organization->id,
        ]);

        $this->deleteJson("/api/v1/organizations/{$organization->id}/catalog/references/{$reference->id}")->assertNoContent();
        $this->deleteJson("/api/v1/organizations/{$organization->id}/catalog/products/{$product->id}")->assertNoContent();
        $this->deleteJson("/api/v1/organizations/{$organization->id}/catalog/lists/{$list->id}")->assertNoContent();

        $this->getJson("/api/v1/organizations/{$organization->id}/catalog/references?status=archived")->assertOk()->assertJsonFragment(['code' => 'MED']);
        $this->getJson("/api/v1/organizations/{$organization->id}/catalog/products?status=archived")->assertOk()->assertJsonFragment(['code' => 'PARA']);
        $this->getJson("/api/v1/organizations/{$organization->id}/catalog/lists?status=archived")->assertOk()->assertJsonFragment(['code' => 'ESS']);

        $this->postJson("/api/v1/organizations/{$organization->id}/catalog/references/archived/{$reference->id}/restore")->assertOk();
        $this->postJson("/api/v1/organizations/{$organization->id}/catalog/products/archived/{$product->id}/restore")->assertOk();
        $this->postJson("/api/v1/organizations/{$organization->id}/catalog/lists/archived/{$list->id}/restore")->assertOk();
    }

    public function test_general_routes_open_the_real_catalog(): void
    {
        $organization = Organization::create(['code' => 'HOME', 'name' => 'Catalogue principal']);
        $user = $this->administrator();

        $this->actingAs($user)->get('/products')
            ->assertRedirect(route('organizations.catalog.index', [$organization, 'section' => 'products']));
        $this->actingAs($user)->get('/standard-lists')
            ->assertRedirect(route('organizations.catalog.index', [$organization, 'section' => 'lists']));
    }
}
