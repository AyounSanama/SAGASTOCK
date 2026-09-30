<?php

namespace Tests\Feature\Web;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SetupProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionConfigurationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Support\OfficialConfigurationFixtures;

    public function test_manual_mission_step_stays_forbidden_after_automatic_provisioning(): void
    {
        $owner = $this->sago();
        $this->actingAs($owner)->get(route('configuration.mission'))->assertForbidden();
        $organization = $this->createOfficialOrganization();
        $this->assertSame(1, $organization->missions()->count());
        // Provisioning is automatic; validating an organization never reopens the legacy wizard.
        $this->get(route('configuration.mission'))->assertForbidden();
    }

    public function test_provisioned_mission_has_scoped_admin_who_can_open_projects(): void
    {
        $this->actingAs($this->sago());
        $organization = $this->createOfficialOrganization();
        $mission = $organization->missions()->firstOrFail();
        $this->assertSame(Country::where('iso2', 'CM')->value('id'), $mission->country_id);
        $this->assertTrue($mission->is_active);
        $admin = User::where('email', 'coordination@stabilisation.example')->firstOrFail();
        $this->assertDatabaseHas('role_user', ['user_id' => $admin->id, 'scope_type' => 'mission', 'scope_id' => $mission->id]);
        $this->actingAs($admin)->get('/projects')->assertOk()->assertSee('project-create-sheet');
    }

    public function test_invalid_organization_country_rolls_back_coordination_provisioning(): void
    {
        $this->actingAs($this->sago());
        // Countries are now selected on the organization form; no manual mission dates are submitted.
        $this->post(route('configuration.organization.save'), $this->organizationPayload([
            'name' => '', 'code' => '', 'country_ids' => ['11111111-2222-4333-8444-555555555555'],
        ]))->assertSessionHasErrors(['name', 'code', 'country_ids.0']);
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('missions', 0);
        $this->assertDatabaseMissing('users', ['email' => 'coordination@stabilisation.example']);
    }

    public function test_repeated_organization_updates_do_not_duplicate_coordination(): void
    {
        $this->actingAs($this->sago());
        $organization = $this->createOfficialOrganization();
        $mission = $organization->missions()->firstOrFail();
        $payload = ['name' => 'Nom actualisé', 'code' => $organization->code, 'status' => 'active', 'geographic_access_type' => 'single_country', 'country_ids' => [$mission->country_id]];
        $this->put(route('configuration.organization.update', $organization), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('configuration.organization.update', $organization), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('missions', 1);
        $this->assertSame($mission->id, $organization->missions()->value('id'));
        $this->assertSame('Nom actualisé', $organization->fresh()->name);
    }

    public function test_legacy_mission_draft_cannot_mutate_provisioned_coordination(): void
    {
        $this->actingAs($this->sago());
        $organization = $this->createOfficialOrganization();
        $mission = $organization->missions()->firstOrFail();
        $before = $mission->getAttributes();
        $this->post(route('configuration.mission.save'), ['name' => 'Brouillon interdit', 'code' => 'DRAFT', 'country_id' => $mission->country_id, 'status' => 'inactive', 'action' => 'continue'])->assertForbidden();
        $this->assertSame($before, $mission->fresh()->getAttributes());
        $this->get(route('configuration.step', 'projects'))->assertForbidden();
    }

    public function test_official_roles_cannot_archive_or_restore_provisioned_mission_manually(): void
    {
        $this->actingAs($this->sago());
        $organization = $this->createOfficialOrganization();
        $mission = $organization->missions()->firstOrFail();
        $this->delete(route('configuration.mission.archive', $mission))->assertForbidden();
        $this->post(route('configuration.mission.restore', $mission->id))->assertForbidden();
        $this->assertDatabaseHas('missions', ['id' => $mission->id, 'deleted_at' => null, 'is_active' => true]);
        $admin = User::where('email', 'coordination@stabilisation.example')->firstOrFail();
        $this->actingAs($admin)->delete(route('organizations.missions.destroy', [$organization, $mission]))->assertForbidden();
        $this->assertDatabaseHas('missions', ['id' => $mission->id, 'deleted_at' => null]);
    }

    public function test_adding_country_provisions_second_mission_without_overwriting_first(): void
    {
        $this->actingAs($this->sago());
        $organization = $this->createOfficialOrganization();
        $first = $organization->missions()->firstOrFail();
        $chad = Country::where('iso2', 'TD')->firstOrFail();
        $this->put(route('configuration.organization.update', $organization), [
            'name' => $organization->name, 'code' => $organization->code, 'status' => 'active',
            'geographic_access_type' => 'multi_country', 'country_ids' => [$first->country_id, $chad->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('missions', 2);
        $this->assertSame($first->name, $first->fresh()->name);
        $this->assertDatabaseHas('missions', ['organization_id' => $organization->id, 'country_id' => $chad->id]);
    }

    public function test_user_without_mission_permission_is_forbidden(): void
    {
        SetupProgress::current()->update(['completed_steps' => [1]]);

        $this->actingAs(User::factory()->create())
            ->get(route('configuration.mission'))
            ->assertForbidden();
    }

}
