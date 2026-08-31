<?php
namespace App\Services;
use App\Models\CatalogReference;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductStandardMapping;
use App\Models\Project;
use Illuminate\Support\Collection;
class StandardListGenerationService {
 public function options(Organization $organization): array {
  $types=['care_level','target_population','pathology','laboratory_exam','facility_category'];
  $refs=CatalogReference::query()->where(fn($q)=>$q->whereNull('organization_id')->orWhere('organization_id',$organization->id))->where('is_active',true)->whereIn('reference_type',$types)->orderBy('name')->get()->groupBy('reference_type');
  return collect($types)->mapWithKeys(fn($type)=>[$type=>$refs->get($type,collect())->values()])->all();
 }
 public function generate(Project $project,array $context): Collection {
  $query=ProductStandardMapping::query()->where('organization_id',$project->organization_id)->where('care_level_id',$context['care_level_id'])
   ->when($context['facility_category_id']??null,fn($q,$id)=>$q->where(fn($n)=>$n->whereNull('facility_category_id')->orWhere('facility_category_id',$id)))
   ->where(fn($q)=>$q->whereNull('target_population_id')->orWhereIn('target_population_id',$context['target_population_ids']));
  $ids=$context['laboratory_exam_ids']?:$context['pathology_ids']; $field=$context['laboratory_exam_ids']?'laboratory_exam_id':'pathology_id';
  $query->where(fn($q)=>$q->whereNull($field)->orWhereIn($field,$ids));
  return Product::query()->where('organization_id',$project->organization_id)->where('is_active',true)->whereIn('id',$query->pluck('product_id')->unique())->with(['baseUnit:id,name','dosageForm:id,name','category:id,name'])->orderBy('name')->get();
 }
}
