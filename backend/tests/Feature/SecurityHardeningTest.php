<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Country;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\UserScopeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Corrections de sécurité critiques et élevées (audit 09) : un test par point,
 * avec de VRAIS jetons Bearer comme un appel mobile.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Mission $mission;

    private Project $project;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->organization = Organization::create(['code' => 'ALIMA', 'name' => 'ALIMA', 'is_active' => true]);
        $this->mission = Mission::create(['organization_id' => $this->organization->id, 'country_id' => Country::where('iso2', 'CM')->value('id'), 'code' => 'M', 'name' => 'Mission', 'is_active' => true]);
        $this->project = Project::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'P', 'name' => 'Projet']);
        $facility = HealthFacility::create(['organization_id' => $this->organization->id, 'mission_id' => $this->mission->id, 'code' => 'F', 'name' => 'FOSA', 'facility_type' => 'health_center']);
        $facility->projects()->attach($this->project);
        $this->site = Site::create(['organization_id' => $this->organization->id, 'health_facility_id' => $facility->id, 'code' => 'S', 'name' => 'Pharmacie', 'site_type' => 'stock_and_dispensing']);
    }

    /** Chaque appel repart d'une authentification vierge, comme une vraie requête. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        // Appels mobiles (Bearer) seulement : les appels Web gardent actingAs.
        if (isset($server['HTTP_AUTHORIZATION'])) {
            $auth = $this->app['auth'];
            $auth->forgetGuards();
            $auth->setDefaultDriver('web');
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    private function user(string $role, string $scopeType, ?string $scopeId, array $attributes = []): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'must_change_password' => false, ...$attributes]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail(), ['scope_type' => $scopeType, 'scope_id' => $scopeId]);

        return $user;
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken, 'Accept' => 'application/json'];
    }

    public function test_s01_a_deactivated_account_loses_access_immediately(): void
    {
        $siteUser = $this->user('site_user', 'site', $this->site->id);
        $headers = $this->bearer($siteUser);
        $this->getJson('/api/v1/auth/me', $headers)->assertOk();

        // Désactivation directe en base (aucun écran) : refus dès la requête suivante.
        User::whereKey($siteUser->id)->update(['is_active' => false]);
        $this->getJson('/api/v1/auth/me', $headers)->assertUnauthorized()->assertJsonPath('code', 'account_disabled');
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $siteUser->id)->count());

        // Désactivation par le modèle (toute interface) : jetons révoqués aussitôt.
        $other = $this->user('site_user', 'site', $this->site->id);
        $this->bearer($other);
        $other->update(['is_active' => false]);
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $other->id)->count());

        // Session Web fermée.
        $webUser = $this->user('project_admin', 'project', $this->project->id);
        $this->actingAs($webUser)->get('/profile')->assertOk();
        User::whereKey($webUser->id)->update(['is_active' => false]);
        $this->actingAs($webUser->fresh())->get('/profile')->assertRedirect(route('login'));
    }

    public function test_s01_a_deactivated_organization_cuts_all_its_accounts(): void
    {
        $projectAdmin = $this->user('project_admin', 'project', $this->project->id);
        $headers = $this->bearer($projectAdmin);
        $this->getJson('/api/v1/auth/me', $headers)->assertOk();

        $this->organization->update(['is_active' => false]);
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $projectAdmin->id)->count());
        $token = $projectAdmin->createToken('mobile')->plainTextToken;
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $token", 'Accept' => 'application/json'])
            ->assertUnauthorized()->assertJsonPath('code', 'account_disabled');
    }

    public function test_s04_the_server_enforces_the_temporary_password_change(): void
    {
        $user = $this->user('project_admin', 'project', $this->project->id, ['must_change_password' => true, 'password' => 'Temporaire!2026']);
        $headers = $this->bearer($user);

        $this->getJson("/api/v1/organizations/{$this->organization->id}/structures", $headers)
            ->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/v1/auth/me', $headers)->assertOk();
        $this->putJson('/api/v1/auth/password', ['current_password' => 'Temporaire!2026', 'password' => 'Definitif!2026', 'password_confirmation' => 'Definitif!2026'], $headers)->assertOk();
        $this->getJson("/api/v1/organizations/{$this->organization->id}/structures", $headers)->assertOk();

        // Web : toute page renvoie au profil tant que le mot de passe n'est pas changé.
        $webUser = $this->user('project_admin', 'project', $this->project->id, ['must_change_password' => true]);
        $this->actingAs($webUser)->get('/dashboard')->assertRedirect(route('profile.show'));
        $this->actingAs($webUser)->get('/profile')->assertOk();
    }

    public function test_s05_health_values_never_reach_the_audit_log_nor_the_global_journal(): void
    {
        $siteUser = $this->user('site_user', 'site', $this->site->id);
        Role::where('code', 'site_user')->firstOrFail()->permissions()->syncWithoutDetaching(Permission::where('code', 'patients.manage')->pluck('id'));
        $headers = $this->bearer($siteUser);
        $base = "/api/v1/organizations/{$this->organization->id}";
        $patientId = $this->postJson("$base/patients", ['site_id' => $this->site->id, 'code' => 'PAT-001', 'first_name' => 'Aïcha', 'last_name' => 'Bello', 'sex' => 'female', 'date_of_birth' => '1995-04-02', 'phone' => '690000000'], $headers)
            ->assertCreated()->json('patient.id');
        $this->putJson("$base/patients/$patientId", ['code' => 'PAT-001', 'first_name' => 'Aïcha', 'last_name' => 'Bello-Ngo', 'phone' => '699999999'], $headers)->assertOk();

        $entry = AuditLog::where('event', 'patient.updated')->firstOrFail();
        $this->assertNull($entry->old_values);
        $this->assertContains('last_name', $entry->new_values['champs_modifies']);
        $raw = json_encode(AuditLog::all()->toArray());
        foreach (['Bello', '690000000', '699999999', '1995-04-02'] as $value) {
            $this->assertStringNotContainsString($value, $raw);
        }

        // Le journal global (Admin Sago) ne contient aucun événement de santé.
        $sago = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $sago->roles()->attach(Role::where('code', 'sago_admin')->firstOrFail(), ['scope_type' => 'platform']);
        $sago->roles()->first()->permissions()->syncWithoutDetaching(Permission::where('code', 'audit.view')->pluck('id'));
        $events = collect($this->getJson('/api/v1/security/audits', $this->bearer($sago))->assertOk()->json('data'))->pluck('event');
        $this->assertFalse($events->contains(fn ($event) => str_starts_with($event, 'patient.')));
    }

    public function test_s05_existing_entries_are_anonymized_by_the_command(): void
    {
        $log = AuditLog::create([
            'event' => 'patient.updated', 'auditable_type' => Patient::class, 'auditable_id' => 'x',
            'old_values' => ['last_name' => 'Ancien', 'phone' => '690000001'], 'new_values' => ['last_name' => 'Nouveau'],
        ]);
        Artisan::call('pharmacare:audit:anonymize-health', ['--dry-run' => true]);
        $this->assertSame('Ancien', $log->fresh()->old_values['last_name']);

        Artisan::call('pharmacare:audit:anonymize-health');
        $fresh = $log->fresh();
        $this->assertNull($fresh->old_values);
        $this->assertSame(['champs_modifies' => ['last_name', 'phone']], $fresh->new_values);
    }

    public function test_s07_only_the_sago_admin_has_the_platform_scope(): void
    {
        $role = Role::create(['code' => 'auditeur', 'name' => 'Auditeur']);
        $legacy = User::factory()->create(['is_active' => true]);
        $legacy->roles()->attach($role, ['scope_type' => 'platform']);
        $sago = User::factory()->create(['is_active' => true]);
        $sago->roles()->attach(Role::where('code', 'sago_admin')->firstOrFail(), ['scope_type' => 'platform']);

        $scopes = app(UserScopeService::class);
        $this->assertFalse($scopes->isPlatform($legacy));
        $this->assertTrue($scopes->organizationIds($legacy)->isEmpty());
        $this->assertTrue($scopes->isPlatform($sago));
    }

    public function test_s03_the_mobile_token_is_extended_on_each_exchange(): void
    {
        $user = $this->user('site_user', 'site', $this->site->id);
        $plain = $user->createToken('mobile', ['*'], now()->addDays(3))->plainTextToken;
        $token = PersonalAccessToken::findToken($plain);

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $plain", 'Accept' => 'application/json'])->assertOk();
        $this->assertTrue($token->fresh()->expires_at->gt(now()->addDays(29)));

        // Jeton expiré : refusé (401) — le mobile conserve ses opérations.
        $token->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $plain", 'Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_s09_web_login_is_limited_per_ip_address(): void
    {
        // Comptes différents : seul le limiteur par adresse IP peut bloquer.
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/login', ['login' => "inconnu$i@example.test", 'password' => 'Mauvais!2026'])->assertStatus(302);
        }
        $this->post('/login', ['login' => 'inconnu7@example.test', 'password' => 'Mauvais!2026'])->assertStatus(429);
    }
}
