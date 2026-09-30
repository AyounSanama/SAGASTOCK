<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\ConfigurationWizardController;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SetupProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteConfigurationWizardTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Support\OfficialConfigurationFixtures;

    public function test_sago_uses_organization_country_choices_and_cannot_open_operational_wizard_steps(): void
    {
        $this->actingAs($this->sago());
        $this->get(route('configuration.index'))->assertOk()->assertSee('Organisations')->assertSee('Standards &amp; Référentiels', false)->assertDontSee('Missions / Pays');
        $this->get(route('configuration.organization', ['create' => 1]))->assertOk()->assertSee('name="geographic_access_type"', false)->assertSee('name="country_ids[]"', false);
        foreach (['projects', 'donors', 'modules', 'facilities', 'users-access', 'summary'] as $step) {
            $this->get(route('configuration.step', $step))->assertForbidden();
        }
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_configuration_hands_off_from_sago_to_coordination_and_project_admin(): void
    {
        $sago = $this->sago();
        $this->actingAs($sago);
        $organization = $this->createOfficialOrganization();
        $mission = $organization->missions()->firstOrFail();
        $coordination = User::where('email', 'coordination@stabilisation.example')->firstOrFail();
        $this->assertSame($organization->id, $coordination->organization_id);
        $this->actingAs($coordination)->post(route('organizations.projects.store', $organization), ['order_period_months' => 1, 'delivery_lead_time_months' => 1, 'safety_stock_months' => 1, 
            'mission_id' => $mission->id, 'code' => 'HEALTH', 'name' => 'Projet Santé',
            'admin' => ['first_name' => 'Admin', 'last_name' => 'Projet', 'email' => 'health@example.test', 'password' => 'PharmaCare!2026', 'password_confirmation' => 'PharmaCare!2026'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $project = $organization->projects()->firstOrFail();
        $projectAdmin = User::where('email', 'health@example.test')->firstOrFail();
        $this->assertDatabaseHas('role_user', ['user_id' => $projectAdmin->id, 'scope_type' => 'project', 'scope_id' => $project->id]);
        $this->actingAs($projectAdmin)->get('/health-facilities')->assertOk()->assertSee('FOSA');
        $this->actingAs($sago)->post(route('configuration.step.save', 'summary'), ['confirmation' => '1'])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['event' => 'configuration.organization.created', 'auditable_id' => $organization->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'project.created', 'auditable_id' => $project->id]);
    }

    public function test_coordination_archives_and_restores_projects_without_mutating_legacy_progress(): void
    {
        $this->actingAs($this->sago());
        $organization = $this->createOfficialOrganization();
        $mission = $organization->missions()->firstOrFail();
        $project = $organization->projects()->create(['mission_id' => $mission->id, 'name' => 'Projet conservé', 'code' => 'KEEP']);
        $progress = SetupProgress::query()->firstOrFail();
        $before = $progress->getAttributes();
        $coordination = User::where('email', 'coordination@stabilisation.example')->firstOrFail();
        $this->actingAs($coordination)->delete(route('organizations.projects.destroy', [$organization, $project]))->assertRedirect();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->post(route('organizations.projects.restore', [$organization, $project->id]))->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Projet conservé', 'deleted_at' => null, 'is_active' => true]);
        // CRUD no longer advances or invalidates the obsolete twelve-step wizard.
        $this->assertSame($before, $progress->fresh()->getAttributes());
    }

}
