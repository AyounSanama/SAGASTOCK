<?php
namespace Tests\Feature\Api;
use App\Models\CatalogReference;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductStandardMapping;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectStandardListTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void { parent::setUp(); $this->seed(DatabaseSeeder::class); }
 public function test_coordination_generates_versions_and_project_admin_can_only_read():void {
  $organization=Organization::create(['code'=>'STD_ORG','name'=>'Standard Org']); $country=Country::where('iso2','CM')->firstOrFail();
  $mission=Mission::create(['organization_id'=>$organization->id,'country_id'=>$country->id,'code'=>'STD_CM','name'=>'Coordination CM']);
  $project=Project::create(['organization_id'=>$organization->id,'mission_id'=>$mission->id,'code'=>'STD_PROJECT','name'=>'Projet standard']);
  $care=$this->ref($organization,'care_level','PRIMARY','Soins primaires'); $population=$this->ref($organization,'target_population','ADULT','Adultes');
  $pathology=$this->ref($organization,'pathology','MALARIA','Paludisme'); $facility=$this->ref($organization,'facility_category','CSI','CSI');
  $product=Product::create(['organization_id'=>$organization->id,'code'=>'ACT','name'=>'Artéméther','product_type'=>'medicine','is_active'=>true]);
  ProductStandardMapping::create(['organization_id'=>$organization->id,'product_id'=>$product->id,'care_level_id'=>$care->id,'target_population_id'=>$population->id,'pathology_id'=>$pathology->id,'facility_category_id'=>$facility->id]);
  $coordination=$this->user($organization,'coordination_admin','mission',$mission->id); Sanctum::actingAs($coordination);
  $context=['care_level_id'=>$care->id,'facility_category_id'=>$facility->id,'target_population_ids'=>[$population->id],'pathology_ids'=>[$pathology->id],'laboratory_exam_ids'=>[]];
  $this->postJson("/api/v1/projects/{$project->id}/standard-list/generate",$context)->assertOk()->assertJsonPath('products.0.id',$product->id);
  $response=$this->postJson("/api/v1/projects/{$project->id}/standard-list",[...$context,'code'=>'STD_LIST','name'=>'Liste du projet','product_ids'=>[$product->id]])->assertCreated()->assertJsonPath('list.scope_id',$project->id);
  $listId=$response->json('list.id'); $this->postJson("/api/v1/projects/{$project->id}/standard-list/{$listId}/publish")->assertOk()->assertJsonPath('list.versions.0.status','published');
  $this->postJson("/api/v1/projects/{$project->id}/standard-list",[...$context,'code'=>'STD_LIST','name'=>'Liste du projet','product_ids'=>[$product->id]])->assertCreated();
  $this->assertDatabaseCount('standard_list_versions',2);
  $projectAdmin=$this->user($organization,'project_admin','project',$project->id); Sanctum::actingAs($projectAdmin);
  $this->getJson("/api/v1/projects/{$project->id}/standard-list")->assertOk()->assertJsonPath('list.scope_id',$project->id);
  $this->postJson("/api/v1/projects/{$project->id}/standard-list/generate",$context)->assertForbidden();
 }
 private function ref(Organization $organization,string $type,string $code,string $name):CatalogReference{return CatalogReference::create(['organization_id'=>$organization->id,'reference_type'=>$type,'code'=>$code,'name'=>$name,'is_active'=>true]);}
 private function user(Organization $organization,string $role,string $scope,string $id):User{$user=User::factory()->create(['organization_id'=>$organization->id]);$user->roles()->attach(Role::where('code',$role)->firstOrFail(),['scope_type'=>$scope,'scope_id'=>$id]);return $user;}
}
