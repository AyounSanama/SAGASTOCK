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

    public function test_steps_are_locked_and_controlled_choices_are_rendered_as_selects(): void
    {
        [$owner] = $this->context();
        $this->actingAs($owner)->get(route('configuration.index'))
            ->assertOk()
            ->assertSee('Organisations')
            ->assertSee('Missions / Pays')
            ->assertSee('Listes standards')
            ->assertSee('Entrer dans l’application');
        $this->actingAs($owner)->get(route('configuration.step', 'projects'))->assertForbidden();
        SetupProgress::current()->update(['completed_steps' => [1, 2]]);
        $this->get(route('configuration.step', 'projects'))
            ->assertOk()->assertSee('<select name="mission_id"', false)
            ->assertSee('<select name="status"', false);
        $this->get(route('configuration.step', 'donors'))->assertForbidden();
    }

    public function test_complete_configuration_persists_every_step_and_finalizes(): void
    {
        [$owner, $organization, $mission, $country, $coordinationRole] = $this->context([1, 2]);
        $this->actingAs($owner);

        $this->save('projects', [
            'mission_id'=>$mission->id, 'name'=>'Projet Santé', 'code'=>'PRJ-CM',
            'starts_on'=>'2026-01-01', 'ends_on'=>'2027-12-31', 'status'=>'active',
        ], 'donors');
        $project = $organization->projects()->firstOrFail();

        $this->save('donors', [
            'name'=>'Fonds Santé', 'code'=>'FS', 'email'=>'fonds@example.org',
            'funding_amount'=>'1000000', 'currency'=>'XAF', 'agreement_reference'=>'AGR-01',
        ], 'programs');
        $this->assertCount(1, $project->fresh()->donors);

        $donor = $organization->donors()->firstOrFail();
        $this->save('programs', [
            'donor_id'=>$donor->id, 'name'=>'Programme Essentiel', 'code'=>'PE',
            'starts_on'=>'2026-01-01', 'ends_on'=>'2027-12-31',
        ], 'modules');
        $this->assertCount(1, $project->fresh()->programs);

        $this->save('modules', ['choices'=>['references','stocks','receptions']], 'features');
        $this->save('features', ['choices'=>['batch_tracking','expiry_alerts','fefo']], 'standard-lists');
        $this->assertDatabaseHas('module_activations', ['target_id'=>$organization->id, 'module_code'=>'stocks', 'is_enabled'=>true]);
        $this->assertDatabaseHas('module_activations', ['target_id'=>$organization->id, 'module_code'=>'feature:fefo', 'is_enabled'=>true]);

        $this->save('standard-lists', [
            'name'=>'Liste essentielle', 'code'=>'LE-CM', 'allow_outside_list'=>'0',
        ], 'facilities');
        $this->assertDatabaseHas('standard_list_versions', ['version_number'=>1, 'status'=>'published']);

        $this->save('facilities', [
            'mission_id'=>$mission->id, 'name'=>'Hôpital Central', 'code'=>'HC',
            'facility_type'=>'hospital', 'care_level'=>'tertiary',
        ], 'sites');
        $facility = $organization->healthFacilities()->firstOrFail();
        $this->assertTrue($facility->projects()->whereKey($project->id)->exists());

        $this->save('sites', [
            'health_facility_id'=>$facility->id, 'name'=>'Pharmacie centrale', 'code'=>'PC',
            'site_type'=>'stock_and_dispensing', 'location'=>'Bâtiment principal',
        ], 'users-access');

        $this->get(route('configuration.step', 'users-access'))
            ->assertOk()
            ->assertSee('configuration-user-create-sheet')
            ->assertSee('Ajouter un utilisateur');

        $this->post(route('configuration.users.store'), [
            'role_id'=>$coordinationRole->id, 'first_name'=>'Admin', 'last_name'=>'Coordination',
            'username'=>'admin_coordination', 'email'=>'coordination@example.org', 'phone'=>'+237600000000',
            'password'=>'Secret123!AB', 'password_confirmation'=>'Secret123!AB',
        ])->assertRedirect(route('configuration.step', 'users-access'))
            ->assertSessionHas('success', 'Utilisateur créé avec succès');
        $createdUser = User::where('email', 'coordination@example.org')->firstOrFail();
        $this->get(route('configuration.step', 'users-access'))->assertOk()
            ->assertSee('Charger ce compte et continuer')->assertSee('Admin Coordination');
        $this->post(route('configuration.users.load', $createdUser))
            ->assertRedirect(route('configuration.step', 'summary'));
        $this->assertAuthenticatedAs($owner);
        $this->assertDatabaseHas('role_user', [
            'role_id'=>$coordinationRole->id, 'scope_type'=>'organization', 'scope_id'=>$organization->id,
        ]);

        $this->post(route('configuration.step.save', 'summary'), ['confirmation'=>'1'])
            ->assertRedirect(route('control-center'));

        $progress = SetupProgress::current();
        $this->assertNotNull($progress->completed_at);
        $this->assertSame($owner->id, (int) $progress->completed_by);
        $this->assertEquals(range(1, 12), $progress->completed_steps);
        $this->assertDatabaseHas('audit_logs', ['event'=>'configuration.summary.validated']);

        $this->get(route('control-center'))
            ->assertOk()
            ->assertSee('Accueil Configuration')
            ->assertSee('ONG Test')
            ->assertSee('Mission Cameroun')
            ->assertSee('Projet Santé')
            ->assertSee('Hôpital Central')
            ->assertSee('Pharmacie centrale')
            ->assertSee('Entrer dans l’application');
    }

    public function test_project_archive_invalidates_dependent_steps_and_can_be_restored(): void
    {
        [$owner, $organization, $mission] = $this->context(range(1, 11));
        $project = $organization->projects()->create([
            'mission_id' => $mission->id,
            'name' => 'Projet conservé',
            'code' => 'KEEP',
            'starts_on' => '2026-01-01',
            'ends_on' => '2027-01-01',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->delete(route('configuration.step.archive', 'projects'))
            ->assertRedirect(route('configuration.step', 'projects'));

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->assertSame([1, 2], SetupProgress::current()->completed_steps);

        $this->post(route('configuration.step.restore', ['projects', $project->id]))
            ->assertRedirect(route('configuration.step', 'projects'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id, 'name' => 'Projet conservé',
            'deleted_at' => null, 'is_active' => true,
        ]);
    }

    private function save(string $step, array $payload, string $next): void
    {
        $this->post(route('configuration.step.save', $step), $payload)
            ->assertRedirect(route('configuration.step', $next))
            ->assertSessionHasNoErrors();
    }

    private function context(array $steps = []): array
    {
        $codes = [
            'configuration.view',
            'organizations.manage','missions.manage','projects.manage','funding.manage',
            'modules.manage','catalog.manage','structures.manage','users.manage',
        ];
        $permissions = collect($codes)->map(fn($code)=>Permission::firstOrCreate(['code'=>$code],['name'=>$code]));
        $ownerRole = Role::create(['code'=>'owner','name'=>'Propriétaire','is_system'=>true,'is_active'=>true]);
        $ownerRole->permissions()->attach($permissions->pluck('id'));
        $coordinationRole = Role::create([
            'code'=>'coordination_admin','name'=>'Admin Coordination','is_system'=>true,'is_active'=>true,
        ]);
        $owner = User::factory()->create(['is_active'=>true]);
        $owner->roles()->attach($ownerRole, ['scope_type'=>'platform','scope_id'=>null]);
        $organization = Organization::create(['code'=>'ONG','name'=>'ONG Test','country_code'=>'CM','is_active'=>true]);
        $country = Country::create(['iso2'=>'CM','name'=>'Cameroun','is_active'=>true]);
        $mission = Mission::create([
            'organization_id'=>$organization->id,'country_id'=>$country->id,
            'code'=>'MISSION','name'=>'Mission Cameroun','is_active'=>true,
        ]);
        SetupProgress::current()->update(['completed_steps'=>$steps]);
        return [$owner,$organization,$mission,$country,$coordinationRole];
    }
}
