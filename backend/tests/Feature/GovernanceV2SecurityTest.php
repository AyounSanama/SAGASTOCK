<?php

namespace Tests\Feature;

use App\Models\HealthFacility;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GovernanceV2SecurityTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void{parent::setUp();$this->seed(DatabaseSeeder::class);}

    public function test_sago_admin_is_the_only_platform_role_and_can_create_organization():void
    {
        $admin=$this->actor('sago_admin','platform');
        $this->assertSame(GovernanceService::SAGO_ADMIN,app(GovernanceService::class)->roleCode($admin));
        $this->assertTrue(app(UserScopeService::class)->isPlatform($admin));
        Sanctum::actingAs($admin);
        $country=Country::firstOrCreate(['iso2'=>'CM'],['name'=>'Cameroun','is_active'=>true]);
        $this->postJson('/api/v1/organizations',[
            'code'=>'SAGO-ORG','name'=>'Organisation Sago','organization_type'=>'ngo',
            'geographic_access_type'=>'single_country','country_ids'=>[$country->id],
            'admin_first_name'=>'Admin','admin_last_name'=>'Coordination',
            'admin_email'=>'coordination.sago@example.test','admin_username'=>'coordination_sago',
        ])->assertCreated();
    }

    public function test_coordination_is_strictly_isolated_and_cannot_create_organization():void
    {
        $a=Organization::create(['code'=>'A','name'=>'Organisation A']);$b=Organization::create(['code'=>'B','name'=>'Organisation B']);
        $user=$this->actor('coordination_admin','organization',$a->id,$a->id);
        $scopes=app(UserScopeService::class);
        $this->assertSame([$a->id],$scopes->organizationIds($user)->all());
        $this->assertFalse($user->hasPermission('configuration.view'));
        $this->assertFalse($user->hasPermission('organizations.manage'));
        $this->assertTrue($user->hasPermission('orders.approve'));
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/organizations')->assertForbidden();
        $this->postJson('/api/v1/organizations',['code'=>'C','name'=>'Interdite'])->assertForbidden();
        $this->getJson("/api/v1/organizations/{$b->id}")->assertForbidden();
        $this->actingAs($user)->get('/configuration')->assertForbidden();
    }

    public function test_site_scope_does_not_expand_to_sibling_site():void
    {
        $organization=Organization::create(['code'=>'SITE','name'=>'Organisation Site']);
        $facility=HealthFacility::create(['organization_id'=>$organization->id,'code'=>'F','name'=>'Centre','facility_type'=>'clinic']);
        $siteA=Site::create(['organization_id'=>$organization->id,'health_facility_id'=>$facility->id,'code'=>'A','name'=>'Site A','site_type'=>'dispensing']);
        $siteB=Site::create(['organization_id'=>$organization->id,'health_facility_id'=>$facility->id,'code'=>'B','name'=>'Site B','site_type'=>'dispensing']);
        $user=$this->actor('site_admin','site',$siteA->id,$organization->id);
        $this->assertSame([$siteA->id],app(UserScopeService::class)->siteIds($user)->all());
        $this->assertFalse(app(UserScopeService::class)->siteIds($user)->contains($siteB->id));
        $this->assertFalse($user->hasPermission('organizations.manage'));
        $this->assertFalse($user->hasPermission('users.manage'));
    }

    public function test_legacy_owner_code_remains_a_compatibility_alias():void
    {
        $this->assertSame(GovernanceService::SAGO_ADMIN,app(GovernanceService::class)->canonicalCode('owner'));
        $this->assertSame(GovernanceService::SAGO_ADMIN,app(GovernanceService::class)->canonicalCode('platform_owner'));
    }

    private function actor(string $code,string $scopeType,?string $scopeId=null,?string $organizationId=null):User
    {
        $user=User::factory()->create(['is_active'=>true,'organization_id'=>$organizationId]);
        $role=Role::where('code',$code)->firstOrFail();
        $user->roles()->attach($role,['scope_type'=>$scopeType,'scope_id'=>$scopeId]);
        return $user;
    }
}
