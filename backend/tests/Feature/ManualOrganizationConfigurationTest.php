<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationEffectiveConfiguration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManualOrganizationConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_and_pages_expose_only_assistance_and_history(): void
    {
        $response = $this->actingAs($this->sago())->get('/configuration/platform-standards');
        $response->assertOk()->assertSee('Assistance aux organisations')->assertSee('Historique')
            ->assertDontSee('Modèles standards')->assertDontSee('Aucun modèle disponible');
        $this->get('/configuration/platform-standards?tab=history')->assertOk()
            ->assertSee('Historique des interventions')->assertSee('Aucune intervention enregistrée pour cette période.');
    }

    #[DataProvider('categories')]
    public function test_each_category_can_be_previewed_then_applied_and_audited(string $category, array $settings): void
    {
        $organization = $this->organization('ORG-A');
        $this->actingAs($this->sago())->post('/configuration/platform-standards/manual/preview', [
            'organization_id' => $organization->id, 'category' => $category, 'settings' => $settings,
        ])->assertRedirect(route('configuration.platform-standards.organizations.assist', $organization));
        $this->assertDatabaseCount('organization_effective_configurations', 0);

        $this->post('/configuration/platform-standards/manual/publish', [
            'organization_id' => $organization->id, 'category' => $category,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('organization_effective_configurations', [
            'organization_id' => $organization->id, 'configuration_category' => $category,
            'configuration_version' => 1, 'status' => 'active', 'synchronization_status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'organization_configuration.applied']);
    }

    public function test_versions_are_isolated_by_organization_and_history_filters_work(): void
    {
        $actor = $this->sago(); $a = $this->organization('A'); $b = $this->organization('B');
        $this->actingAs($actor); $this->apply($a, 'security', $this->security(30));
        $this->apply($a, 'security', $this->security(60));
        $this->apply($b, 'platform', ['notifications_enabled'=>1,'maintenance_messages'=>0,'default_page_size'=>25,'support_contact_visible'=>1]);

        $this->assertDatabaseCount('organization_effective_configurations', 3);
        $this->assertDatabaseHas('organization_effective_configurations', ['organization_id'=>$a->id,'configuration_version'=>2,'status'=>'active']);
        $this->assertDatabaseHas('organization_effective_configurations', ['organization_id'=>$b->id,'configuration_version'=>1,'status'=>'active']);
        $this->get('/configuration/platform-standards?tab=history&organization_id='.$a->id.'&category=security')
            ->assertOk()->assertSee('Organisation A')->assertSee('v1.1')->assertSee('Total interventions</dt><dd>2', false);
    }

    public function test_detail_and_restore_create_a_new_immutable_version(): void
    {
        $organization=$this->organization('RESTORE'); $this->actingAs($this->sago());
        $this->apply($organization,'security',$this->security(30)); $old=OrganizationEffectiveConfiguration::firstOrFail();
        $this->apply($organization,'security',$this->security(60));
        $this->get(route('configuration.platform-standards.history.show',$old))->assertOk()->assertSee('Avant / Après');
        $this->post(route('configuration.platform-standards.history.restore',$old))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('organization_effective_configurations',3);
        $this->assertDatabaseHas('organization_effective_configurations',['organization_id'=>$organization->id,'configuration_version'=>3,'intervention_action'=>'restored','status'=>'active']);
        $this->assertDatabaseHas('organization_effective_configurations',['id'=>$old->id,'status'=>'superseded']);
    }

    public function test_non_sago_is_denied_even_with_permissions(): void
    {
        $permission=Permission::firstOrCreate(['code'=>'platform_standards.view'],['name'=>'view']);
        $role=Role::firstOrCreate(['code'=>'coordination_admin'],['name'=>'Coordination','is_active'=>true,'is_system'=>true]);
        $role->permissions()->sync([$permission->id]); $user=User::factory()->create(['is_active'=>true]);
        $user->roles()->attach($role,['scope_type'=>'organization']);
        $this->actingAs($user)->get('/configuration/platform-standards')->assertForbidden();
    }

    public static function categories(): array
    {
        return [
            'general'=>['general',['default_language'=>'fr','additional_languages'=>['en'],'timezone'=>'Africa/Douala','locale'=>'fr_FR','date_format'=>'d/m/Y','time_format'=>'H:i']],
            'access'=>['access',['allowed_profiles'=>['ADMIN_COORDINATION','ADMIN_PROJECT']]],
            'security'=>['security',['session_duration_minutes'=>60,'logout_after_inactivity'=>1,'maximum_login_attempts'=>5,'temporary_lock_minutes'=>15,'authentication_policy'=>'strict']],
            'synchronization'=>['synchronization',['automatic_sync'=>1,'sync_on_reconnect'=>1,'offline_enabled'=>1,'configuration_download'=>'automatic','sync_interval_minutes'=>15]],
            'platform'=>['platform',['notifications_enabled'=>1,'maintenance_messages'=>0,'default_page_size'=>25,'support_contact_visible'=>1]],
        ];
    }

    private function apply(Organization $organization,string $category,array $settings): void
    {
        $this->post('/configuration/platform-standards/manual/preview',compact('category','settings')+['organization_id'=>$organization->id])->assertRedirect();
        $this->post('/configuration/platform-standards/manual/publish',['organization_id'=>$organization->id,'category'=>$category])->assertRedirect();
    }
    private function security(int $duration): array { return ['session_duration_minutes'=>$duration,'logout_after_inactivity'=>1,'maximum_login_attempts'=>5,'temporary_lock_minutes'=>15,'authentication_policy'=>'strict']; }
    private function organization(string $code): Organization { return Organization::create(['code'=>$code,'name'=>'Organisation '.$code,'is_active'=>true]); }
    private function sago(): User
    {
        $permissions=collect(['platform_standards.view','standards.assign'])->map(fn($code)=>Permission::firstOrCreate(['code'=>$code],['name'=>$code]));
        $role=Role::firstOrCreate(['code'=>'sago_admin'],['name'=>'Admin Sago','is_active'=>true,'is_system'=>true]);
        $role->permissions()->sync($permissions->pluck('id')); $user=User::factory()->create(['is_active'=>true]);
        $user->roles()->attach($role,['scope_type'=>'platform']); return $user;
    }
}
