<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Device;
use App\Models\Donor;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\HealthFacilityConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Synchronisation : état déclaré par le téléphone et supervision en lecture seule. */
class SyncSupervisionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
    }

    public function test_phone_reports_its_state_and_coordination_supervises_it(): void
    {
        [$site, $project, $mission] = $this->facility('YDE');
        [$otherSite] = $this->facility('DLA');
        $agent = $this->siteAccount($site, 'site_user', 'Paul Owona');
        $device = $this->device($agent);
        $lateAgent = $this->siteAccount($otherSite, 'site_admin', 'Christine Mballa');
        $this->device($lateAgent);

        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/sync/report', [
            'device_id' => $device->fingerprint,
            'last_success_at' => now()->subHour()->toIso8601String(),
            'pending' => ['receipts' => 2, 'clinical' => 1, 'unknown' => 9],
            'issues' => [
                ['id' => (string) Str::uuid(), 'kind' => 'refused', 'module' => 'clinical', 'reference' => 'DIS-0317',
                    'reason' => 'Le stock de ce site est gelé par un inventaire en cours.', 'occurred_at' => now()->subMinutes(5)->toIso8601String()],
                ['id' => (string) Str::uuid(), 'kind' => 'conflict', 'module' => 'receipts', 'reference' => 'REC-249', 'reported' => true],
            ],
        ])->assertOk();
        $this->assertDatabaseHas('device_sync_states', ['device_id' => $device->id, 'site_id' => $site->id, 'pending_total' => 3]);

        $coordination = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $coordination->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $mission->id]);
        Sanctum::actingAs($coordination);
        $board = $this->getJson('/api/v1/sync/supervision')->assertOk()->json();

        $this->assertSame(1, $board['stats']['devices'], 'Seule la FOSA de sa coordination');
        $this->assertSame(1, $board['stats']['refused']);
        $this->assertSame(1, $board['stats']['conflicts']);
        $row = $board['rows'][0];
        $this->assertSame('CSI YDE', $row['facility']);
        $this->assertSame([$project->name], $row['projects']);
        $this->assertSame('check', $row['status']);
        $this->assertSame(3, $row['pending']);

        $detail = $this->getJson('/api/v1/sync/supervision?device='.$device->id.'.'.$agent->id)->json('selected');
        $refused = collect($detail['issues'])->firstWhere('kind', 'refused');
        $this->assertSame('Refusé', $refused['kind_label']);
        $this->assertSame('DIS-0317', $refused['reference']);
        $this->assertTrue(collect($detail['issues'])->firstWhere('kind', 'conflict')['reported']);

        $this->get('/synchronization')->assertOk()->assertSee('Supervision de la synchronisation')->assertSee('CSI YDE');
    }

    public function test_device_without_recent_success_is_late_and_filterable(): void
    {
        [$site, , $mission] = $this->facility('YDE');
        $agent = $this->siteAccount($site, 'site_user', 'Paul Owona');
        $device = $this->device($agent);
        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/sync/report', ['device_id' => $device->fingerprint, 'last_success_at' => now()->subHours(30)->toIso8601String()])->assertOk();

        $coordination = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $coordination->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $mission->id]);
        Sanctum::actingAs($coordination);

        $board = $this->getJson('/api/v1/sync/supervision?filter=late')->json();
        $this->assertSame('late', $board['rows'][0]['status']);
        $this->assertSame('Il y a 30 h', $board['rows'][0]['last_success_label']);
        $this->assertCount(0, $this->getJson('/api/v1/sync/supervision?filter=refused')->json('rows'));
    }

    public function test_shared_phone_keeps_the_state_of_each_account(): void
    {
        [$site, , $mission] = $this->facility('YDE');
        $agent = $this->siteAccount($site, 'site_user', 'Paul Owona');
        $device = $this->device($agent);
        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/sync/report', [
            'device_id' => $device->fingerprint,
            'last_success_at' => now()->subMinutes(10)->toIso8601String(),
            'issues' => [['id' => (string) Str::uuid(), 'kind' => 'refused', 'module' => 'orders', 'reference' => 'CMD-1']],
        ])->assertOk();

        // Le même téléphone passe à un compte Coordination (connexion = appareil réattribué).
        $coordination = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $coordination->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), ['scope_type' => 'mission', 'scope_id' => $mission->id]);
        $device->update(['user_id' => $coordination->id]);
        Sanctum::actingAs($coordination);
        $this->postJson('/api/v1/sync/report', ['device_id' => $device->fingerprint, 'last_success_at' => now()->toIso8601String()])->assertOk();

        $board = $this->getJson('/api/v1/sync/supervision')->json();
        $this->assertSame(1, $board['stats']['devices']);
        $this->assertSame('Paul Owona', $board['rows'][0]['user']);
        $this->assertSame(1, $board['rows'][0]['refused']);

        // Un autre compte de la FOSA, connecté mais sans rapport : « Jamais ».
        $this->device($this->siteAccount($site, 'site_admin', 'Christine Mballa'));
        $rows = collect($this->getJson('/api/v1/sync/supervision')->assertOk()->json('rows'));
        $this->assertSame('Jamais', $rows->firstWhere('user', 'Christine Mballa')['last_success_label']);
        $this->assertCount(2, $rows);
    }

    public function test_phone_cannot_report_for_a_device_of_another_user(): void
    {
        [$site] = $this->facility('YDE');
        $agent = $this->siteAccount($site, 'site_user', 'Paul Owona');
        $other = $this->siteAccount($site, 'site_admin', 'Christine Mballa');
        $foreignDevice = $this->device($other);

        Sanctum::actingAs($agent);
        $this->postJson('/api/v1/sync/report', ['device_id' => $foreignDevice->fingerprint])->assertNotFound();
        $this->postJson('/api/v1/sync/report', [
            'device_id' => $this->device($agent)->fingerprint,
            'issues' => [['id' => (string) Str::uuid(), 'kind' => 'deleted', 'module' => 'clinical']],
        ])->assertUnprocessable();
    }

    private function siteAccount(Site $site, string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => 'site', 'scope_id' => $site->id]);

        return $user;
    }

    private function device(User $user): Device
    {
        $id = (string) Str::uuid();

        return Device::create(['fingerprint' => $id, 'user_id' => $user->id, 'name' => 'Pixel', 'platform' => 'android', 'last_seen_at' => now()]);
    }

    /** @return array{0: Site, 1: Project, 2: Mission} */
    private function facility(string $code): array
    {
        $mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::firstOrCreate(['iso2' => 'CM'], ['iso3' => 'CMR', 'name' => 'Cameroun'])->id,
            'code' => $code, 'name' => "Coordination $code", 'is_active' => true]);
        $project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $mission->id, 'code' => "P-$code", 'name' => "Projet $code", 'status' => 'active', 'is_active' => true]);
        $project->donors()->attach(Donor::create(['organization_id' => $this->organization->id, 'code' => "D-$code", 'name' => "Bailleur $code", 'is_active' => true])->id);
        $facility = HealthFacility::create(['organization_id' => $this->organization->id, 'mission_id' => $mission->id, 'code' => "CSI-$code", 'name' => "CSI $code",
            'facility_type' => 'health_center', 'validation_status' => HealthFacility::STATUS_VALIDATED, 'is_active' => true]);
        $facility->projects()->sync([$project->id]);

        return [app(HealthFacilityConfigurationService::class)->ensurePrimarySite($facility), $project, $mission];
    }
}
