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

/** Niveau 3 — « Modifier » un compte de la coordination (maquette Coordination 04), Web et API. */
class CoordinationAccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private User $coordination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ONG', 'name' => 'ONG Santé']);
        $this->mission = $this->mission('YDE');
        $this->coordination = $this->user('coordination_admin', 'mission', $this->mission->id);
    }

    public function test_coordination_edits_a_project_admin_and_moves_it_to_another_project(): void
    {
        $gffo5 = $this->project('GFFO5', $this->mission);
        $fh4 = $this->project('FH4', $this->mission);
        $admin = $this->user('project_admin', 'project', $gffo5->id, ['first_name' => 'Joseph', 'last_name' => 'Atangana', 'email' => 'j.atangana@ong.example']);

        $this->actingAs($this->coordination)->get(route('organizations.missions.show', [$this->organization, $this->mission, 'tab' => 'accounts']))
            ->assertOk()->assertSee('Modifier')->assertSee('account-edit-'.$admin->id, false);

        $this->actingAs($this->coordination)->put(route('coordination.accounts.update', $admin), [
            'first_name' => 'Joseph', 'last_name' => 'Atangana Mbarga', 'email' => 'joseph.atangana@ong.example', 'phone' => '+237 600', 'project_id' => $fh4->id,
        ])->assertRedirect()->assertSessionHas('status');

        $admin->refresh();
        $this->assertSame('Joseph Atangana Mbarga', $admin->name);
        $this->assertSame('joseph.atangana@ong.example', $admin->email);
        $this->assertSame([$fh4->id], $admin->roles()->where('roles.code', 'project_admin')->get()->pluck('pivot.scope_id')->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.updated', 'auditable_id' => (string) $admin->id]);
    }

    public function test_api_and_scope_rules(): void
    {
        $project = $this->project('GFFO5', $this->mission);
        $reader = $this->user('coordination_admin', 'mission', $this->mission->id, ['read_only' => true]);
        $admin = $this->user('project_admin', 'project', $project->id);
        $foreign = $this->project('AUTRE', $this->mission('DLA'));
        Sanctum::actingAs($this->coordination);

        // Coordination (lecture seule) : pas de projet à fournir.
        $this->putJson("/api/v1/coordination/accounts/{$reader->id}", ['first_name' => 'Diane', 'last_name' => 'Tchoua', 'email' => 'd.tchoua@ong.example'])
            ->assertOk()->assertJsonPath('user.name', 'Diane Tchoua');
        // Projet hors de la coordination refusé.
        $this->putJson("/api/v1/coordination/accounts/{$admin->id}", ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a.b@ong.example', 'project_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('project_id');
        // Son propre compte : refusé.
        $this->putJson("/api/v1/coordination/accounts/{$this->coordination->id}", ['first_name' => 'A', 'last_name' => 'B', 'email' => 'x@ong.example'])
            ->assertForbidden();

        // La lecture seule ne modifie rien.
        Sanctum::actingAs($reader);
        $this->putJson("/api/v1/coordination/accounts/{$admin->id}", ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a.b@ong.example', 'project_id' => $project->id])
            ->assertForbidden();
    }

    private function mission(string $code): Mission
    {
        return Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->firstOrFail()->id, 'code' => $code, 'name' => 'Coordination '.$code, 'is_active' => true]);
    }

    private function project(string $code, Mission $mission): Project
    {
        return Project::create(['organization_id' => $this->organization->id, 'mission_id' => $mission->id, 'code' => $code, 'name' => 'Projet '.$code, 'status' => 'active']);
    }

    private function user(string $role, string $scopeType, string $scopeId, array $attributes = []): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false, ...$attributes]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }
}
