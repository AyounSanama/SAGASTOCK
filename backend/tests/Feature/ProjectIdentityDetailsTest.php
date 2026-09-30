<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** AM-110 — Fiche projet enrichie, workflow unique Web / API. */
class ProjectIdentityDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA']);
        $this->mission = Mission::create([
            'organization_id' => $this->organization->id,
            'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id,
            'code' => 'MAROUA', 'name' => 'Coordination Maroua',
        ]);
    }

    private function coordination(): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'coordination_admin')->firstOrFail(), [
            'scope_type' => 'mission', 'scope_id' => $this->mission->id,
        ]);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return [
            'mission_id' => $this->mission->id,
            'code' => 'NUT-2026',
            'name' => 'Projet Nutrition',
            'implementing_partner' => 'Programme national de nutrition',
            'donor_reference_code' => 'ECHO-2026-017',
            'moh_program_code' => 'PNLN-CM',
            'responsible_name' => 'Dr Aïcha Bello',
            'responsible_contact' => '+237 690 00 00 00',
            'status' => 'draft',
            'order_period_months' => 1,
            'delivery_lead_time_months' => 1,
            'safety_stock_months' => 1,
            ...$overrides,
        ];
    }

    public function test_api_creates_and_updates_a_project_with_its_identity_details(): void
    {
        Sanctum::actingAs($this->coordination());

        $id = $this->postJson("/api/v1/organizations/{$this->organization->id}/projects", $this->payload())
            ->assertCreated()
            ->assertJsonPath('project.implementing_partner', 'Programme national de nutrition')
            ->assertJsonPath('project.moh_program_code', 'PNLN-CM')
            ->assertJsonPath('project.status', 'draft')
            ->assertJsonPath('project.status_label', 'Brouillon')
            ->assertJsonPath('project.is_active', false)
            ->assertJsonPath('admin_project', null)
            ->json('project.id');

        $this->putJson("/api/v1/organizations/{$this->organization->id}/projects/{$id}", $this->payload(['status' => 'active', 'responsible_name' => 'Dr Paul Ndi']))
            ->assertOk()
            ->assertJsonPath('project.status', 'active')
            ->assertJsonPath('project.is_active', true)
            ->assertJsonPath('project.responsible_name', 'Dr Paul Ndi');

        $this->assertDatabaseHas('audit_logs', ['event' => 'project.updated', 'auditable_id' => $id]);
    }

    public function test_web_and_api_share_the_same_validation(): void
    {
        $user = $this->coordination();
        $invalid = $this->payload(['status' => 'archived', 'ends_on' => '2026-01-01', 'starts_on' => '2026-06-01']);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/organizations/{$this->organization->id}/projects", $invalid)
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'ends_on']);

        $this->actingAs($user)->post(route('organizations.projects.store', $this->organization), $invalid)
            ->assertSessionHasErrors(['status', 'ends_on']);
    }

    public function test_web_creation_no_longer_requires_a_project_admin_and_ignores_an_empty_admin_block(): void
    {
        $this->actingAs($this->coordination())
            ->post(route('organizations.projects.store', $this->organization), $this->payload([
                'admin' => ['first_name' => '', 'last_name' => '', 'email' => '', 'password' => '', 'password_confirmation' => ''],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Projet créé avec succès.');

        $this->assertDatabaseHas('projects', ['code' => 'NUT-2026', 'status' => 'draft', 'is_active' => false]);
    }

    public function test_legacy_clients_sending_only_is_active_keep_working(): void
    {
        Sanctum::actingAs($this->coordination());
        $payload = collect($this->payload(['is_active' => false]))->except('status')->all();

        $this->postJson("/api/v1/organizations/{$this->organization->id}/projects", $payload)
            ->assertCreated()
            ->assertJsonPath('project.status', 'suspended')
            ->assertJsonPath('project.is_active', false);
    }

    public function test_project_admin_can_read_but_never_modify_the_project_identity(): void
    {
        $project = Project::create([...$this->payload(), 'organization_id' => $this->organization->id]);
        $admin = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'project_admin')->firstOrFail(), [
            'scope_type' => 'project', 'scope_id' => $project->id,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()->assertJsonPath('project.donor_reference_code', 'ECHO-2026-017');
        $this->putJson("/api/v1/organizations/{$this->organization->id}/projects/{$project->id}", $this->payload(['name' => 'Intrusion']))
            ->assertForbidden();
        $this->assertDatabaseMissing('projects', ['name' => 'Intrusion']);
    }
}
