<?php

namespace Tests\Feature;

use App\Models\{HealthFacility, Organization, Role, Site, User};
use App\Services\UserScopeService;
use App\Models\{Country, Mission, Project};
use App\Notifications\OperationalNotification;
use Laravel\Sanctum\Sanctum;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\V1FacilityFixtures;
use Tests\TestCase;

class SiteScopeStabilisationTest extends TestCase
{
    use RefreshDatabase;
    use V1FacilityFixtures;

    public function test_project_creates_facility_site_and_account_then_site_login_is_scoped(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::create(['code' => 'CHAIN', 'name' => 'Parcours FOSA']);
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => Country::where('iso2', 'CM')->value('id'), 'code' => 'CM', 'name' => 'Coordination']);
        $project = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'A', 'name' => 'Projet A']);
        $foreignProject = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'B', 'name' => 'Projet B']);
        $foreignFacility = HealthFacility::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'B', 'name' => 'FOSA B', 'facility_type' => 'clinic']);
        $foreignFacility->projects()->attach($foreignProject);
        $foreignSite = $foreignFacility->sites()->create(['organization_id' => $organization->id, 'code' => 'B', 'name' => 'Site B', 'site_type' => 'stock_and_dispensing']);
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->roles()->attach(Role::where('code', 'project_admin')->firstOrFail(), ['scope_type' => 'project', 'scope_id' => $project->id]);
        $v1 = $this->configureV1Project($project);
        Sanctum::actingAs($admin);
        $base = "/api/v1/organizations/{$organization->id}";
        $facilityId = $this->postJson("$base/facilities", ['code' => 'A', 'name' => 'FOSA A', ...$v1])
            ->assertCreated()->json('facility.id');
        $facility = HealthFacility::findOrFail($facilityId);
        $this->assertSame($mission->id, $facility->mission_id);
        $this->assertSame([$project->id], $facility->projects()->pluck('projects.id')->all());
        $this->putJson("$base/facilities/$facilityId", ['code' => 'A', 'name' => 'FOSA A actualisee', ...$v1])->assertOk();
        $this->validateFacility($facilityId);
        $this->putJson("$base/facilities/{$foreignFacility->id}", ['code' => 'B', 'name' => 'Modification interdite', 'facility_type' => 'clinic'])->assertNotFound();
        $this->assertSame('FOSA B', $foreignFacility->fresh()->name);
        $siteId = $this->postJson("$base/facilities/$facilityId/sites", ['code' => 'A', 'name' => 'Pharmacie A', 'site_type' => 'stock_and_dispensing'])->assertCreated()->json('site.id');
        $payload = ['name' => 'Admin FOSA A', 'email' => 'fosa-a@example.test', 'role_id' => Role::where('code', 'site_admin')->value('id'), 'scope_type' => 'site', 'scope_id' => $siteId, 'password' => 'PharmaCare!2026', 'password_confirmation' => 'PharmaCare!2026'];
        $userId = $this->postJson('/api/v1/users', $payload)->assertCreated()->json('user.id');
        $this->postJson('/api/v1/users', [...$payload, 'email' => 'intrus@example.test', 'scope_id' => $foreignSite->id])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'intrus@example.test']);
        $siteAdmin = User::findOrFail($userId);
        $this->assertSame($organization->id, $siteAdmin->organization_id);
        $this->postJson('/api/v1/auth/login', ['login' => $siteAdmin->email, 'password' => 'PharmaCare!2026', 'device_name' => 'FOSA test', 'device_id' => '11111111-2222-4333-8444-555555555555', 'platform' => 'android'])
            ->assertOk()->assertJsonPath('user.role', 'site_admin');
        Sanctum::actingAs($siteAdmin);
        $this->getJson('/api/v1/dashboard')->assertOk();
        $scopes = app(UserScopeService::class);
        $this->assertSame([$siteId], $scopes->siteIds($siteAdmin)->all());
        $this->assertSame([$facilityId], $scopes->facilityIds($siteAdmin)->all());
        $this->getJson("$base/inventories/options")->assertOk()->assertJsonFragment(['id' => $siteId])->assertJsonMissing(['id' => $foreignSite->id]);
        $this->postJson("$base/inventories", ['site_id' => $foreignSite->id, 'reference' => 'CROSS', 'inventory_type' => 'monthly', 'period_date' => today()->toDateString()])->assertNotFound();
        $this->actingAs($siteAdmin)->get('/dashboard')->assertOk();
        $this->get('/profile')->assertOk();
        $product = $organization->products()->create(['code' => 'MED', 'name' => 'Medicament test', 'product_type' => 'medicine', 'is_active' => true]);
        $receiptId = $this->postJson("$base/receipts", [
            'site_id' => $siteId, 'reference' => 'RECEPTION-A', 'received_on' => today()->toDateString(), 'origin_type' => 'other', 'origin_label' => 'Fournisseur test',
            'items' => [['product_id' => $product->id, 'batch_number' => 'LOT-A', 'expires_on' => today()->addYear()->toDateString(), 'quantity_ordered' => 10, 'quantity_received' => 10, 'quantity_accepted' => 10]],
        ])->assertCreated()->json('receipt.id');
        $this->postJson("$base/receipts/$receiptId/validate")->assertOk()->assertJsonPath('receipt.status', 'validated');
        $this->assertDatabaseHas('stock_balances', ['site_id' => $siteId, 'product_id' => $product->id, 'theoretical_quantity' => 10]);
        $foreignUser = $this->member($organization, $foreignSite, 'site_user');
        $foreignUser->notify(new OperationalNotification(['title' => 'Privee B']));
        $notificationId = $this->getJson('/profile/notifications')->assertOk()->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.title', 'Réception validée')->assertJsonPath('data.0.action_path', '/receipts')
            ->assertJsonMissing(['title' => 'Privee B'])->json('data.0.id');
        $this->get('/receipts')->assertOk()->assertSee('RECEPTION-A');
        $this->getJson("$base/operational-report")->assertOk()->assertJsonPath('receipts.validated', 1)->assertJsonPath('stock.available_quantity', 10);
        $this->postJson('/profile/notifications/'.$foreignUser->notifications()->first()->id.'/read')->assertNotFound();
        $this->postJson("/profile/notifications/$notificationId/read")->assertOk()->assertJsonPath('unread_count', 0);
        $this->get('/users')->assertForbidden();
        $this->postJson('/profile/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertSame(1, $foreignUser->unreadNotifications()->count());
        $webReceipt = \App\Models\Receipt::create(['organization_id' => $organization->id, 'site_id' => $siteId, 'reference' => 'RECEPTION-WEB', 'received_on' => today(), 'created_by' => $siteAdmin->id, 'status' => 'draft']);
        $webReceipt->items()->create(['product_id' => $product->id, 'batch_id' => $product->batches()->value('id'), 'quantity_ordered' => 1, 'quantity_received' => 1, 'quantity_accepted' => 1, 'quantity_rejected' => 0]);
        $this->post(route('organizations.receipts.validate', [$organization, $webReceipt]))->assertRedirect()->assertSessionHasNoErrors();
        $this->getJson('/profile/notifications')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.title', 'Réception validée');
        $this->assertDatabaseHas('stock_balances', ['site_id' => $siteId, 'product_id' => $product->id, 'theoretical_quantity' => 11]);
        $this->post(route('organizations.receipts.validate', [$organization, $webReceipt]))->assertUnprocessable();
        $this->assertSame(2, $siteAdmin->notifications()->count());
        Sanctum::actingAs($foreignUser);
        $this->getJson("$base/receipts")->assertOk()->assertJsonMissing(['id' => $receiptId]);
        $this->getJson("$base/operational-report")->assertOk()->assertJsonPath('receipts.total', 0)->assertJsonPath('stock.available_quantity', 0);
        $this->actingAs($foreignUser, 'web')->get('/receipts')->assertOk()->assertDontSee('RECEPTION-A');
        $this->get(route('organizations.receipts.show', [$organization, $receiptId]))->assertNotFound();
    }

    public function test_site_admin_user_scope_excludes_other_sites_in_the_same_organization(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::create(['code' => 'SITE-SCOPE', 'name' => 'Site scope']);
        $facility = HealthFacility::create(['organization_id' => $organization->id, 'code' => 'FOSA', 'name' => 'FOSA', 'facility_type' => 'clinic']);
        $sites = collect(['A', 'B'])->map(fn ($code) => Site::create([
            'organization_id' => $organization->id, 'health_facility_id' => $facility->id,
            'code' => $code, 'name' => $code, 'site_type' => 'stock_and_dispensing',
        ]));
        $actor = $this->member($organization, $sites[0], 'site_admin');
        $inside = $this->member($organization, $sites[0], 'site_user');
        $outside = $this->member($organization, $sites[1], 'site_user');
        $scopes = app(UserScopeService::class);
        $this->assertEqualsCanonicalizing([$actor->id, $inside->id], $scopes->users($actor)->pluck('id')->all());
        $this->assertFalse($scopes->canAccess($actor, $outside));
        $this->assertFalse($scopes->allowsScope($actor, 'site', $sites[1]->id));
        $this->assertEquals([$inside->id], $scopes->users($inside)->pluck('id')->all());
        // The existing role has no user administration permission.
        $this->actingAs($actor)->get('/users')->assertForbidden();
    }

    private function member(Organization $organization, Site $site, string $code): User
    {
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->roles()->attach(Role::where('code', $code)->firstOrFail(), ['scope_type' => 'site', 'scope_id' => $site->id]);
        return $user;
    }
}
