<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\CatalogReference;
use App\Models\Project;
use App\Models\Product;
use App\Models\ProductStandardMapping;
use App\Models\StandardList;
use App\Services\AuditService;
use App\Services\GovernanceService;
use App\Services\StandardListGenerationService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectStandardListController extends Controller {
 public function __construct(private UserScopeService $scopes,private StandardListGenerationService $generator,private AuditService $audit,private GovernanceService $governance) {}
 public function show(Request $request,Project $project): JsonResponse {
  $this->project($request,$project);
  $list=StandardList::where('scope_type','project')->where('scope_id',$project->id)->with(['versions'=>fn($q)=>$q->with(['products.baseUnit','products.dosageForm','products.category'])->latest('version_number')])->first();
  return response()->json(['project'=>$project->load('mission.country'),'list'=>$list,'options'=>$this->generator->options($project->organization)]);
 }
 public function generate(Request $request,Project $project): JsonResponse {
  $this->manage($request,$project); $context=$this->context($request,$project);
  return response()->json(['products'=>$this->generator->generate($project,$context),'context'=>$context]);
 }
 public function save(Request $request,Project $project): JsonResponse {
  $this->manage($request,$project); $context=$this->context($request,$project);
  $data=$request->validate(['code'=>['required','alpha_dash','max:60'],'name'=>['required','string','max:190'],'description'=>['nullable','string','max:3000'],'product_ids'=>['required','array','min:1'],'product_ids.*'=>['uuid','distinct']]);
  abort_unless($project->organization->products()->whereIn('id',$data['product_ids'])->count()===count($data['product_ids']),422,'Un produit ne dépend pas de cette organisation.');
  $list=DB::transaction(function() use($project,$data,$context){
   $list=StandardList::firstOrCreate(['organization_id'=>$project->organization_id,'scope_type'=>'project','scope_id'=>$project->id],['code'=>$data['code'],'name'=>$data['name'],'description'=>$data['description']??null,'is_active'=>true]);
   $list->update(['code'=>$data['code'],'name'=>$data['name'],'description'=>$data['description']??null,'is_active'=>true]);
   $version=$list->versions()->create([...$context,'version_number'=>((int)$list->versions()->max('version_number'))+1,'status'=>'draft']);
   $version->products()->sync($data['product_ids']); return $list->load('versions.products');
  });
  $this->audit->record($request,'project_standard_list.saved',$list,[],['project_id'=>$project->id]);
  return response()->json(['list'=>$list],201);
 }
 public function publish(Request $request,Project $project,StandardList $list): JsonResponse {
  $this->manage($request,$project); abort_unless($list->scope_type==='project'&&$list->scope_id===$project->id,404);
  $version=$list->versions()->where('status','draft')->latest('version_number')->firstOrFail(); abort_unless($version->products()->exists(),422);
  DB::transaction(function() use($list,$version,$request){$list->versions()->where('status','published')->update(['status'=>'superseded']);$version->update(['status'=>'published','published_by'=>$request->user()->id,'published_at'=>now()]);});
  $this->audit->record($request,'project_standard_list.published',$version); return response()->json(['list'=>$list->fresh('versions.products')]);
 }
 public function mapProduct(Request $request,Project $project,Product $product):JsonResponse {
  $this->manage($request,$project); abort_unless($product->organization_id===$project->organization_id,404);
  $data=$request->validate(['mappings'=>['required','array','min:1'],'mappings.*.care_level_id'=>['required','uuid'],'mappings.*.target_population_id'=>['nullable','uuid'],'mappings.*.pathology_id'=>['nullable','uuid'],'mappings.*.laboratory_exam_id'=>['nullable','uuid'],'mappings.*.facility_category_id'=>['nullable','uuid']]);
  foreach($data['mappings'] as $mapping){foreach(['care_level_id'=>'care_level','target_population_id'=>'target_population','pathology_id'=>'pathology','laboratory_exam_id'=>'laboratory_exam','facility_category_id'=>'facility_category'] as $field=>$type){if(!empty($mapping[$field]))$this->references($project,[$mapping[$field]],$type);}}
  DB::transaction(function()use($project,$product,$data){ProductStandardMapping::where('organization_id',$project->organization_id)->where('product_id',$product->id)->delete();foreach($data['mappings'] as $mapping)ProductStandardMapping::create(['organization_id'=>$project->organization_id,'product_id'=>$product->id,...$mapping]);});
  return response()->json(['mappings'=>ProductStandardMapping::where('product_id',$product->id)->get()]);
 }
 private function context(Request $request,Project $project): array {
  $data=$request->validate(['care_level_id'=>['required','uuid'],'facility_category_id'=>['required','uuid'],'target_population_ids'=>['required','array','min:1'],'target_population_ids.*'=>['uuid','distinct'],'pathology_ids'=>['nullable','array'],'pathology_ids.*'=>['uuid','distinct'],'laboratory_exam_ids'=>['nullable','array'],'laboratory_exam_ids.*'=>['uuid','distinct']]);
  $types=['care_level_id'=>'care_level','facility_category_id'=>'facility_category']; foreach($types as $field=>$type)$this->references($project,[$data[$field]],$type);
  $this->references($project,$data['target_population_ids'],'target_population'); $this->references($project,$data['pathology_ids']??[],'pathology'); $this->references($project,$data['laboratory_exam_ids']??[],'laboratory_exam');
  abort_if(empty($data['pathology_ids'])&&empty($data['laboratory_exam_ids']),422,'Sélectionnez une pathologie ou un examen de laboratoire.'); return $data;
 }
 private function references(Project $project,array $ids,string $type): void { abort_unless(CatalogReference::whereIn('id',$ids)->where('reference_type',$type)->where(fn($q)=>$q->whereNull('organization_id')->orWhere('organization_id',$project->organization_id))->count()===count($ids),422,"Référentiel $type invalide."); }
 private function project(Request $request,Project $project): void { abort_unless($this->scopes->projects($request->user())->whereKey($project->id)->exists(),404); }
 private function manage(Request $request,Project $project): void { $this->project($request,$project); abort_unless($this->governance->roleCode($request->user())===GovernanceService::COORDINATION_ADMIN&&$request->user()->hasPermission('standard_lists.manage'),403); }
}
