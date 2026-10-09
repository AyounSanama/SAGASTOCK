<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Dispensation;
use App\Models\Donor;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\HealthFacilityConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Niveau 7 — Policies patients et ordonnances ; matrice E1 (09/10) : la
 * Coordination et l'Admin Projet ne voient ni ne saisissent de dossier patient.
 */
class PatientPrescriptionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
    }

    public function test_patient_records_stay_within_the_site_of_the_account(): void
    {
        [$site, , $mission] = $this->facility('YDE');
        [$otherSite] = $this->facility('DLA');
        $mine = $this->patient($site, 'P-YDE', 'Essomba');
        $foreign = $this->patient($otherSite, 'P-DLA', 'Ngono');
        $agent = $this->account('site_user', 'site', $site->id);
        Sanctum::actingAs($agent);
        $api = "/api/v1/organizations/{$this->organization->id}";

        $codes = collect($this->getJson("$api/patients")->assertOk()->json('data'))->pluck('code');
        $this->assertSame(['P-YDE'], $codes->all());
        $this->getJson("$api/patients/{$mine->id}/history")->assertOk();
        $this->getJson("$api/patients/{$foreign->id}/history")->assertNotFound();
        // Utilisateur Site : consulte, mais ne modifie pas le dossier (patients.manage absent).
        $this->putJson("$api/patients/{$mine->id}", ['code' => 'P-YDE', 'first_name' => 'X', 'last_name' => 'Y'])->assertForbidden();

        $web = $this->actingAs($agent)->get('/dispensations')->assertOk();
        $web->assertSee('Essomba')->assertDontSee('Ngono');

        $coordination = $this->account('coordination_admin', 'mission', $mission->id);
        foreach (['patients.view', 'patients.manage', 'prescriptions.manage', 'dispensations.manage'] as $permission) {
            $this->assertFalse($coordination->hasPermission($permission), $permission);
        }
        $this->assertTrue($coordination->hasPermission('dispensations.view'));
    }

    public function test_without_patient_record_the_identity_is_hidden(): void
    {
        [$site] = $this->facility('YDE');
        $patient = $this->patient($site, 'P-YDE', 'Essomba');
        Dispensation::create(['organization_id' => $this->organization->id, 'site_id' => $site->id, 'patient_id' => $patient->id,
            'destination_type' => 'patient', 'reference' => 'DIS-1', 'dispensed_at' => now(), 'status' => 'validated',
            'patient_age_years' => 34, 'patient_sex' => 'male', 'prescription_number' => 'ORD-1', 'prescriber_name' => 'Dr Mbarga']);

        // Compte de consultation sur le site, sans fiche patient.
        $viewer = $this->account('site_user', 'site', $site->id);
        $viewer->roles()->first()->permissions()->detach(\App\Models\Permission::where('code', 'patients.view')->value('id'));
        Sanctum::actingAs($viewer->fresh());

        $row = $this->getJson("/api/v1/organizations/{$this->organization->id}/dispensations")->assertOk()->json('data.0');
        $this->assertSame('P-YDE', $row['patient']['code']);
        $this->assertArrayNotHasKey('last_name', $row['patient']);
        $this->assertNull($row['patient_age_years']);
        $this->assertNull($row['patient_sex']);
        $this->getJson("/api/v1/organizations/{$this->organization->id}/patients/{$patient->id}/history")->assertForbidden();
    }

    private function patient(Site $site, string $code, string $lastName): Patient
    {
        return Patient::create(['organization_id' => $this->organization->id, 'site_id' => $site->id, 'health_facility_id' => $site->health_facility_id,
            'code' => $code, 'first_name' => 'Jean', 'last_name' => $lastName, 'sex' => 'male', 'is_active' => true]);
    }

    private function account(string $role, string $scopeType, string $scopeId): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
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
