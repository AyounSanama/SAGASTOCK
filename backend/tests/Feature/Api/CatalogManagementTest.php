<?php

namespace Tests\Feature\Api;

use App\Models\HealthFacility;
use App\Models\ModuleActivation;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(string $scope = 'platform', ?string $scopeId = null): User
    {
        $permissions = collect([
            'catalog.view' => 'Consulter le catalogue', 'catalog.manage' => 'Gérer le catalogue',
            'catalog.publish' => 'Publier les listes', 'batches.manage' => 'Gérer les lots',
        ])->map(fn ($name, $code) => Permission::create(compact('code', 'name')));
        $role = Role::create(['code' => 'catalog_admin_'.uniqid(), 'name' => 'Administrateur catalogue']);
        $role->permissions()->attach($permissions->pluck('id'));
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id, ['scope_type' => $scope, 'scope_id' => $scopeId]);

        return $user;
    }

    public function test_complete_catalog_product_batch_kit_and_standard_list_cycle(): void
    {
        $organization = Organization::create(['code' => 'CAT', 'name' => 'Catalogue ONG']);
        Sanctum::actingAs($this->administrator());
        $unit = $this->postJson("/api/v1/organizations/{$organization->id}/catalog/references", [
            'reference_type' => 'unit', 'code' => 'TAB', 'name' => 'Comprimé',
        ])->assertCreated()->json('reference');
        $category = $this->postJson("/api/v1/organizations/{$organization->id}/catalog/references", [
            'reference_type' => 'category', 'code' => 'MED', 'name' => 'Médicaments',
        ])->assertCreated()->json('reference');
        $supplier = $this->postJson("/api/v1/organizations/{$organization->id}/catalog/suppliers", [
            'code' => 'SUP_1', 'name' => 'Fournisseur central', 'supplier_type' => 'supplier',
        ])->assertCreated()->json('supplier');
        $product = $this->postJson("/api/v1/organizations/{$organization->id}/catalog/products", [
            'category_id' => $category['id'], 'base_unit_id' => $unit['id'], 'code' => 'PARA500',
            'name' => 'Paracétamol 500 mg', 'generic_name' => 'Paracétamol', 'product_type' => 'medicine', 'strength' => '500 mg',
            'codes' => [['code_type' => 'barcode', 'value' => '1234567890123', 'is_primary' => true]],
        ])->assertCreated()->assertJsonPath('product.codes.0.value', '1234567890123')->json('product');
        $batch = $this->postJson("/api/v1/organizations/{$organization->id}/catalog/batches", [
            'product_id' => $product['id'], 'supplier_id' => $supplier['id'], 'batch_number' => 'LOT-2026-01',
            'manufactured_on' => '2026-01-01', 'expires_on' => '2028-01-01', 'unit_cost' => 12.5, 'currency' => 'eur', 'status' => 'available',
        ])->assertCreated()->assertJsonPath('batch.currency', 'EUR')->json('batch');
        $kit = $this->postJson("/api/v1/organizations/{$organization->id}/catalog/kits", [
            'code' => 'KIT_URGENCE', 'name' => 'Kit urgence', 'items' => [['product_id' => $product['id'], 'quantity' => 20]],
        ])->assertCreated()->json('kit');
        $list = $this->postJson("/api/v1/organizations/{$organization->id}/catalog/lists", [
            'code' => 'LISTE_ESS', 'name' => 'Liste essentielle', 'scope_type' => 'organization', 'scope_id' => $organization->id,
            'allow_outside_list' => false, 'items' => [['product_id' => $product['id'], 'minimum_quantity' => 10, 'maximum_quantity' => 100]],
        ])->assertCreated()->json('list');
        $versionId = $list['versions'][0]['id'];
        $this->postJson("/api/v1/organizations/{$organization->id}/catalog/lists/{$list['id']}/versions/{$versionId}/publish", [
            'effective_from' => '2026-08-01',
        ])->assertOk()->assertJsonPath('version.status', 'published');

        $this->getJson("/api/v1/organizations/{$organization->id}/catalog/products?search=123456")
            ->assertOk()->assertJsonFragment(['code' => 'PARA500']);
        $this->getJson("/api/v1/organizations/{$organization->id}/catalog/batches")
            ->assertOk()->assertJsonFragment(['batch_number' => 'LOT-2026-01']);
        $this->deleteJson("/api/v1/organizations/{$organization->id}/catalog/batches/{$batch['id']}")->assertNoContent();
        $this->postJson("/api/v1/organizations/{$organization->id}/catalog/batches/archived/{$batch['id']}/restore")->assertOk();
        $this->putJson("/api/v1/organizations/{$organization->id}/catalog/kits/{$kit['id']}", [
            'code' => 'KIT_URGENCE', 'name' => 'Kit urgence actualisé', 'items' => [['product_id' => $product['id'], 'quantity' => 25]],
        ])->assertOk()->assertJsonPath('kit.name', 'Kit urgence actualisé');
        $this->deleteJson("/api/v1/organizations/{$organization->id}/catalog/kits/{$kit['id']}")->assertNoContent();
        $this->postJson("/api/v1/organizations/{$organization->id}/catalog/kits/archived/{$kit['id']}/restore")->assertOk();
        $this->putJson("/api/v1/organizations/{$organization->id}/catalog/lists/{$list['id']}", [
            'code' => 'LISTE_ESS', 'name' => 'Liste essentielle actualisée', 'allow_outside_list' => true, 'is_active' => true,
        ])->assertOk();
        $this->deleteJson("/api/v1/organizations/{$organization->id}/catalog/lists/{$list['id']}")->assertNoContent();
        $this->postJson("/api/v1/organizations/{$organization->id}/catalog/lists/archived/{$list['id']}/restore")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'standard_list.published', 'auditable_id' => $versionId]);
    }

    public function test_catalog_is_isolated_and_cross_organization_references_are_rejected(): void
    {
        $inside = Organization::create(['code' => 'IN', 'name' => 'Interne']);
        $outside = Organization::create(['code' => 'OUT', 'name' => 'Externe']);
        Sanctum::actingAs($this->administrator('organization', $inside->id));
        $this->getJson("/api/v1/organizations/{$outside->id}/catalog/products")->assertNotFound();
        $outsideReference = $outside->catalogReferences()->create(['reference_type' => 'unit', 'code' => 'BOX', 'name' => 'Boîte']);
        $this->postJson("/api/v1/organizations/{$inside->id}/catalog/products", [
            'base_unit_id' => $outsideReference->id, 'code' => 'BAD', 'name' => 'Produit invalide', 'product_type' => 'medicine',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('products', ['code' => 'BAD']);
    }

    public function test_standard_list_rejects_a_scope_from_another_organization(): void
    {
        $inside = Organization::create(['code' => 'IN', 'name' => 'Interne']);
        $outside = Organization::create(['code' => 'OUT', 'name' => 'Externe']);
        $facility = HealthFacility::create(['organization_id' => $outside->id, 'code' => 'FOSA', 'name' => 'FOSA externe', 'facility_type' => 'clinic']);
        Sanctum::actingAs($this->administrator());
        $this->postJson("/api/v1/organizations/{$inside->id}/catalog/lists", [
            'code' => 'BAD_LIST', 'name' => 'Liste invalide', 'scope_type' => 'facility', 'scope_id' => $facility->id,
        ])->assertUnprocessable();
    }

    public function test_web_catalog_and_excel_compatible_csv_exchange(): void
    {
        $organization = Organization::create(['code' => 'WEB_CAT', 'name' => 'Catalogue Web']);
        $user = $this->administrator();
        $this->assertTrue(app(UserScopeService::class)->organizations($user)->whereKey($organization->id)->exists());
        $this->actingAs($user)->get("/organizations/{$organization->id}/catalog")
            ->assertOk()->assertSee('Référentiels')->assertSee('Ajouter un médicament')->assertSee('product-create-sheet')->assertSee('pharmacare-logo.png');
        $this->actingAs($user)->get("/organizations/{$organization->id}/catalog/products/create")
            ->assertOk()->assertSee('Ajouter un médicament')->assertSee('DCI / nom générique')->assertSee('Classification pharmaceutique');
        $this->actingAs($user)->post("/organizations/{$organization->id}/catalog/products", [
            'code' => 'GAUZE', 'name' => 'Compresses stériles', 'product_type' => 'consumable', 'barcode' => '987654321',
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($user)->get("/organizations/{$organization->id}/catalog/products/export")
            ->assertOk()->assertDownload('produits-WEB_CAT.csv');
        $this->actingAs($user)->get("/organizations/{$organization->id}/catalog/products/export-xlsx")
            ->assertOk()->assertDownload('produits-WEB_CAT.xlsx');
        $csv = "code;name;generic_name;product_type;strength;barcode\nGANTS;Gants médicaux;;consumable;;111222333\n";
        $this->actingAs($user)->post("/organizations/{$organization->id}/catalog/products/import", [
            'file' => UploadedFile::fake()->createWithContent('produits.csv', $csv),
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('products', ['organization_id' => $organization->id, 'code' => 'GANTS', 'name' => 'Gants médicaux']);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['code', 'name', 'generic_name', 'product_type', 'strength', 'barcode'],
            ['MASQUE', 'Masques chirurgicaux', '', 'consumable', '', '444555666'],
        ]);
        $excelPath = tempnam(sys_get_temp_dir(), 'catalog-test-').'.xlsx';
        (new Xlsx($spreadsheet))->save($excelPath);
        $this->actingAs($user)->post("/organizations/{$organization->id}/catalog/products/import-xlsx", [
            'file' => new UploadedFile($excelPath, 'produits.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('products', ['organization_id' => $organization->id, 'code' => 'MASQUE', 'name' => 'Masques chirurgicaux']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'products.imported']);
    }

    public function test_catalog_can_be_disabled_for_an_organization(): void
    {
        $organization = Organization::create(['code' => 'OFF', 'name' => 'Catalogue désactivé']);
        ModuleActivation::create([
            'target_type' => 'organization', 'target_id' => $organization->id,
            'module_code' => 'references', 'is_enabled' => false,
        ]);
        $user = $this->administrator();
        Sanctum::actingAs($user);
        $this->getJson("/api/v1/organizations/{$organization->id}/catalog/products")->assertForbidden();
        $this->actingAs($user)->get("/organizations/{$organization->id}/catalog")->assertForbidden();
    }
}
