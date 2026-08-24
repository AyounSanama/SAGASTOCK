<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\ApplicationNavigationService;
use App\Services\UserScopeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectAdminScopeIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_project_admin_sees_only_assigned_project_and_dependent_structures(): void
    {
        $organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $otherOrganization = Organization::create(['code' => 'MSF', 'name' => 'MSF']);
        $country = Country::where('iso2', 'CM')->firstOrFail();
        $mission = Mission::create(['organization_id' => $organization->id, 'country_id' => $country->id, 'code' => 'CM', 'name' => 'Mission Cameroun']);
        $otherMission = Mission::create(['organization_id' => $otherOrganization->id, 'country_id' => $country->id, 'code' => 'MSF', 'name' => 'Mission MSF']);
        $nutrition = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'NUT', 'name' => 'Projet Nutrition']);
        $maternal = Project::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'MAT', 'name' => 'Projet Santé Maternelle']);
        $foreign = Project::create(['organization_id' => $otherOrganization->id, 'mission_id' => $otherMission->id, 'code' => 'OTHER', 'name' => 'Projet étranger']);

        $allowedFacility = HealthFacility::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'F1', 'name' => 'Centre autorisé', 'facility_type' => 'hospital']);
        $hiddenFacility = HealthFacility::create(['organization_id' => $organization->id, 'mission_id' => $mission->id, 'code' => 'F2', 'name' => 'Centre non autorisé', 'facility_type' => 'hospital']);
        $allowedFacility->projects()->attach($nutrition->id);
        $hiddenFacility->projects()->attach($maternal->id);
        $allowedSite = Site::create(['organization_id' => $organization->id, 'health_facility_id' => $allowedFacility->id, 'code' => 'S1', 'name' => 'Site autorisé', 'site_type' => 'dispensing']);
        $hiddenSite = Site::create(['organization_id' => $organization->id, 'health_facility_id' => $hiddenFacility->id, 'code' => 'S2', 'name' => 'Site non autorisé', 'site_type' => 'dispensing']);

        $admin = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $admin->roles()->attach(Role::where('code', 'project_admin')->firstOrFail(), [
            'scope_type' => 'project', 'scope_id' => $nutrition->id,
        ]);

        $navigation = collect(app(ApplicationNavigationService::class)->items($admin))->pluck('key');
        $this->assertTrue($navigation->contains('projects'));
        $this->assertFalse($navigation->contains('missions'));
        $scopes = app(UserScopeService::class);
        $this->assertEqualsCanonicalizing([$nutrition->id], $scopes->projectIds($admin)->all());
        $this->assertTrue($scopes->facilityIds($admin)->contains($allowedFacility->id));
        $this->assertFalse($scopes->facilityIds($admin)->contains($hiddenFacility->id));
        $this->assertTrue($scopes->siteIds($admin)->contains($allowedSite->id));
        $this->assertFalse($scopes->siteIds($admin)->contains($hiddenSite->id));

        $this->actingAs($admin)->get('/projects')->assertOk()
            ->assertSee('Projet Nutrition')->assertDontSee('Projet Santé Maternelle')->assertDontSee('Projet étranger');
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/organizations/{$organization->id}/projects")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $nutrition->id);
        $this->getJson("/api/v1/organizations/{$otherOrganization->id}/projects")->assertNotFound();
        $this->assertNotSame($maternal->id, $nutrition->id);
        $this->assertNotSame($foreign->id, $nutrition->id);
    }
}
