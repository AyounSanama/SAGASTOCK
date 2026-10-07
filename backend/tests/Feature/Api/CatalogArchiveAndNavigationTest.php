<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnforceV1ModuleAvailability;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogArchiveAndNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** Admin Coordination de l'organisation (codification du catalogue réservée à la Coordination). */
    private function administrator(Organization $organization): User
    {
        $this->seed(DatabaseSeeder::class);
        // Codification du catalogue par la Coordination : permission prévue au lot f
        // (AM-173) ; écrans catalogue masqués en V1. On vérifie ici la logique conservée.
        $this->withoutMiddleware(EnforceV1ModuleAvailability::class);
        Role::where('code', 'coordination_admin')->firstOrFail()->permissions()
            ->syncWithoutDetaching(Permission::whereIn('code', ['catalog.manage', 'products.manage'])->pluck('id'));
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => Country::where('iso2', 'CM')->value('id'), 'code' => 'M-'.$organization->code, 'name' => 'Mission', 'is_active' => true]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $mission->id]);

        return $user;
    }

    public function test_archived_records_are_listed_and_can_be_restored(): void
    {
        $organization = Organization::create(['code' => 'ARCH', 'name' => 'Catalogue archives']);
        Sanctum::actingAs($this->administrator($organization));
        $reference = $organization->catalogReferences()->create([
            'reference_type' => 'category', 'code' => 'MED', 'name' => 'Medicaments',
        ]);
        $product = $organization->products()->create([
            'code' => 'PARA', 'name' => 'Paracetamol', 'product_type' => 'medicine',
        ]);
        $list = $organization->standardLists()->create([
            // Liste d'un projet de la mission : hors plateforme, seules les listes de projet sont visibles.
            'code' => 'ESS', 'name' => 'Liste essentielle', 'scope_type' => 'project',
            'scope_id' => Project::create(['organization_id' => $organization->id, 'mission_id' => Mission::where('organization_id', $organization->id)->value('id'), 'code' => 'P-ARCH', 'name' => 'Projet'])->id,
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
        $user = $this->administrator($organization);

        $this->actingAs($user)->get('/products')
            ->assertRedirect(route('organizations.catalog.index', [$organization, 'section' => 'products']));
        // Niveau 6 : la Coordination ouvre sa Liste Standard (par projet et par FOSA).
        $this->actingAs($user)->get('/standard-lists')
            ->assertRedirect(route('coordination.standard-list.show'));
    }
}
