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

    public function test_step_is_available_only_after_organization_validation(): void
    {
        [$owner] = $this->context();

        $this->actingAs($owner)
            ->get(route('configuration.mission'))
            ->assertForbidden();

        SetupProgress::current()->update(['completed_steps' => [1]]);

        $this->get(route('configuration.mission'))
            ->assertOk()
            ->assertSee('Étape 2')
            ->assertSee('Mission')
            ->assertSee('Enregistrer et continuer');
    }

    public function test_valid_mission_is_persisted_and_unlocks_projects(): void
    {
        [$owner, $organization, $country] = $this->context([1]);

        $response = $this->actingAs($owner)->post(route('configuration.mission.save'), [
            'name' => 'Mission Santé Cameroun',
            'code' => 'MSC-CM',
            'country_id' => $country->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2028-12-31',
            'status' => 'active',
            'action' => 'continue',
        ]);

        $response->assertRedirect(route('configuration.step', 'projects'));
        $mission = Mission::firstOrFail();
        $this->assertSame($organization->id, $mission->organization_id);
        $this->assertSame($country->id, $mission->country_id);
        $this->assertSame('MSC-CM', $mission->code);
        $this->assertTrue($mission->is_active);
        $this->assertContains(2, SetupProgress::current()->completed_steps);

        $this->get(route('configuration.step', 'projects'))
            ->assertOk()
            ->assertSee('Projet principal')
            ->assertSee('Nom du projet');
    }

    public function test_validation_rejects_missing_fields_invalid_dates_and_inactive_country(): void
    {
        [$owner, , $country] = $this->context([1], false);

        $this->actingAs($owner)
            ->from(route('configuration.mission'))
            ->post(route('configuration.mission.save'), [
                'name' => '',
                'code' => '',
                'country_id' => $country->id,
                'starts_on' => '2027-01-01',
                'ends_on' => '2026-01-01',
                'status' => 'active',
                'action' => 'save',
            ])
            ->assertRedirect(route('configuration.mission'))
            ->assertSessionHasErrors(['name', 'code', 'country_id', 'ends_on']);

        $this->assertDatabaseCount('missions', 0);
    }

    public function test_saving_again_updates_without_creating_a_duplicate(): void
    {
        [$owner, $organization, $country] = $this->context([1, 2]);
        $mission = $organization->missions()->create([
            'country_id' => $country->id,
            'code' => 'OLD',
            'name' => 'Ancienne mission',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->post(route('configuration.mission.save'), [
            'name' => 'Mission actualisée',
            'code' => 'NEW',
            'country_id' => $country->id,
            'status' => 'active',
            'action' => 'save',
        ])->assertRedirect(route('configuration.mission'));

        $this->assertDatabaseCount('missions', 1);
        $this->assertSame('Mission actualisée', $mission->fresh()->name);
        $this->assertSame('NEW', $mission->fresh()->code);
    }

    public function test_inactive_mission_is_saved_as_draft_but_cannot_unlock_projects(): void
    {
        [$owner, , $country] = $this->context([1]);
        $payload = [
            'name' => 'Mission en préparation',
            'code' => 'DRAFT',
            'country_id' => $country->id,
            'status' => 'inactive',
        ];

        $this->actingAs($owner)->post(route('configuration.mission.save'), [
            ...$payload,
            'action' => 'save',
        ])->assertRedirect(route('configuration.mission'));

        $this->assertFalse(Mission::firstOrFail()->is_active);
        $this->assertNotContains(2, SetupProgress::current()->completed_steps);

        $this->from(route('configuration.mission'))
            ->post(route('configuration.mission.save'), [
                ...$payload,
                'action' => 'continue',
            ])
            ->assertRedirect(route('configuration.mission'))
            ->assertSessionHasErrors('status');

        $this->get(route('configuration.step', 'projects'))->assertForbidden();
    }

    public function test_mission_can_be_archived_and_restored_without_data_loss(): void
    {
        [$owner, $organization, $country] = $this->context([1, 2]);
        $mission = $organization->missions()->create([
            'country_id' => $country->id,
            'code' => 'ARCH',
            'name' => 'Mission à archiver',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->delete(route('configuration.mission.archive', $mission))
            ->assertRedirect(route('configuration.mission'));

        $this->assertSoftDeleted('missions', ['id' => $mission->id]);
        $this->assertNotContains(2, SetupProgress::current()->completed_steps);

        $this->post(route('configuration.mission.restore', $mission->id))
            ->assertRedirect(route('configuration.mission'));

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'name' => 'Mission à archiver',
            'deleted_at' => null,
            'is_active' => true,
        ]);
    }

    public function test_a_second_mission_can_be_added_without_overwriting_the_first(): void
    {
        [$owner, $organization, $country] = $this->context([1, 2]);
        $first = $organization->missions()->create([
            'country_id' => $country->id,
            'code' => 'FIRST',
            'name' => 'Première mission',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->post(route('configuration.mission.save'), [
            'create_new' => '1',
            'name' => 'Deuxième mission',
            'code' => 'SECOND',
            'country_id' => $country->id,
            'status' => 'active',
            'action' => 'save',
        ])->assertRedirect(route('configuration.mission'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('missions', 2);
        $this->assertSame('Première mission', $first->fresh()->name);
        $this->get(route('configuration.mission'))
            ->assertOk()
            ->assertSee('Première mission')
            ->assertSee('Deuxième mission')
            ->assertSee('Ajouter une mission');
    }

    public function test_user_without_mission_permission_is_forbidden(): void
    {
        SetupProgress::current()->update(['completed_steps' => [1]]);

        $this->actingAs(User::factory()->create())
            ->get(route('configuration.mission'))
            ->assertForbidden();
    }

    private function context(array $completedSteps = [], bool $countryActive = true): array
    {
        $organizationPermission = Permission::create([
            'code' => 'organizations.manage',
            'name' => 'Gérer les organisations',
        ]);
        $configurationPermission = Permission::firstOrCreate([
            'code' => 'configuration.view',
        ], [
            'name' => 'Accéder à la configuration',
        ]);
        $missionPermission = Permission::create([
            'code' => 'missions.manage',
            'name' => 'Gérer les missions',
        ]);
        $projectPermission = Permission::create([
            'code' => 'projects.manage',
            'name' => 'Gérer les projets',
        ]);
        $role = Role::create([
            'code' => 'owner',
            'name' => 'Propriétaire',
            'is_system' => true,
        ]);
        $role->permissions()->attach([
            $organizationPermission->id,
            $missionPermission->id,
            $projectPermission->id,
            $configurationPermission->id,
        ]);
        $owner = User::factory()->create(['is_active' => true]);
        $owner->roles()->attach($role, ['scope_type' => 'platform', 'scope_id' => null]);
        $organization = Organization::create([
            'code' => 'ONG-CM',
            'name' => 'ONG Santé Cameroun',
            'country_code' => 'CM',
        ]);
        $country = Country::create([
            'iso2' => 'CM',
            'name' => 'Cameroun',
            'is_active' => $countryActive,
        ]);
        SetupProgress::current()->update(['completed_steps' => $completedSteps]);

        return [$owner, $organization, $country];
    }
}
