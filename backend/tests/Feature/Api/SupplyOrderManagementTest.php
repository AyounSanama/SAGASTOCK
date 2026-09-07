<?php

namespace Tests\Feature\Api;

use App\Models\HealthFacility;
use App\Models\Inventory;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplyOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $organization=Organization::create(['code'=>'ORD','name'=>'Organisation commandes']);
        $facility=HealthFacility::create(['organization_id'=>$organization->id,'code'=>'HF','name'=>'Centre','facility_type'=>'clinic']);
        $requesting=Site::create(['organization_id'=>$organization->id,'health_facility_id'=>$facility->id,'code'=>'REQ','name'=>'Pharmacie demandeuse','site_type'=>'dispensing']);
        $supplying=Site::create(['organization_id'=>$organization->id,'health_facility_id'=>$facility->id,'code'=>'SUP','name'=>'Dépôt fournisseur','site_type'=>'stock']);
        $product=Product::create(['organization_id'=>$organization->id,'code'=>'MED','name'=>'Paracétamol','product_type'=>'medicine','is_active'=>true]);
        $permissions=collect(['orders.view','orders.manage','orders.approve','orders.prepare'])->map(fn($code)=>Permission::firstOrCreate(['code'=>$code],['name'=>$code]));
        $role=Role::create(['code'=>'order_test','name'=>'Commandes']); $role->permissions()->attach($permissions);
        $user=User::factory()->create(['is_active'=>true,'organization_id'=>$organization->id]);
        $user->roles()->attach($role,['scope_type'=>'organization','scope_id'=>$organization->id]); Sanctum::actingAs($user);
        Inventory::create(['organization_id'=>$organization->id,'site_id'=>$requesting->id,'reference'=>'INV-CLOSED','inventory_type'=>'monthly','period_date'=>today(),'status'=>'validated','created_by'=>$user->id,'validated_by'=>$user->id,'validated_at'=>now()]);
        return compact('organization','requesting','supplying','product','role','user');
    }

    public function test_complete_multi_level_approval_and_preparation_cycle(): void
    {
        $c=$this->context(); $url="/api/v1/organizations/{$c['organization']->id}/orders";
        $payload=['offline_uuid'=>fake()->uuid(),'reference'=>'CMD-001','requesting_site_id'=>$c['requesting']->id,'supplying_site_id'=>$c['supplying']->id,'priority'=>'urgent','required_approval_levels'=>2,'lines'=>[['product_id'=>$c['product']->id,'requested_quantity'=>20]]];
        $order=$this->postJson($url,$payload)->assertCreated()->assertJsonPath('order.status','draft')->json('order');
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('order.id',$order['id']);
        $this->postJson("$url/{$order['id']}/submit")->assertOk()->assertJsonPath('order.status','submitted');
        $this->postJson("$url/{$order['id']}/decision",['decision'=>'approve'])->assertOk()->assertJsonPath('order.current_approval_level',1)->assertJsonPath('order.status','submitted');
        $this->postJson("$url/{$order['id']}/decision",['decision'=>'approve'])->assertUnprocessable();
        $second=User::factory()->create(['is_active'=>true,'organization_id'=>$c['organization']->id]); $second->roles()->attach($c['role'],['scope_type'=>'organization','scope_id'=>$c['organization']->id]); Sanctum::actingAs($second);
        $approved=$this->postJson("$url/{$order['id']}/decision",['decision'=>'approve'])->assertOk()->assertJsonPath('order.status','approved')->json('order');
        $this->postJson("$url/{$order['id']}/prepare",['complete'=>true,'lines'=>[['id'=>$approved['lines'][0]['id'],'prepared_quantity'=>18]]])->assertOk()->assertJsonPath('order.status','completed');
        $this->assertDatabaseCount('supply_order_approvals',2); $this->assertDatabaseHas('audit_logs',['event'=>'order.completed']);
    }

    public function test_rejection_requires_reason_and_preserves_history(): void
    {
        $c=$this->context(); $url="/api/v1/organizations/{$c['organization']->id}/orders";
        $order=$this->postJson($url,['reference'=>'CMD-R','requesting_site_id'=>$c['requesting']->id,'priority'=>'normal','required_approval_levels'=>1,'lines'=>[['product_id'=>$c['product']->id,'requested_quantity'=>4]]])->json('order');
        $this->postJson("$url/{$order['id']}/submit")->assertOk();
        $this->postJson("$url/{$order['id']}/decision",['decision'=>'reject'])->assertUnprocessable();
        $this->postJson("$url/{$order['id']}/decision",['decision'=>'reject','comment'=>'Quantité à justifier avec le responsable'])->assertOk()->assertJsonPath('order.status','rejected');
        $this->assertDatabaseHas('supply_orders',['id'=>$order['id'],'status'=>'rejected']);
        $this->assertDatabaseHas('supply_order_approvals',['supply_order_id'=>$order['id'],'decision'=>'reject']);
    }

    public function test_order_proposal_is_rejected_without_a_validated_inventory(): void
    {
        $c=$this->context();
        Inventory::where('site_id',$c['requesting']->id)->delete();
        $this->postJson("/api/v1/organizations/{$c['organization']->id}/orders",[
            'reference'=>'CMD-NO-INV','requesting_site_id'=>$c['requesting']->id,'priority'=>'normal',
            'required_approval_levels'=>1,'lines'=>[['product_id'=>$c['product']->id,'requested_quantity'=>4]],
        ])->assertUnprocessable()->assertJsonPath('message','Un inventaire clôturé et validé est obligatoire avant de créer une proposition de commande.');
    }
}
