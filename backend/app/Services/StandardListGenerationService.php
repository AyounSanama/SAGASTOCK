<?php
namespace App\Services;
use App\Models\CatalogReference;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductStandardMapping;
use App\Models\Project;
use App\Models\StandardListVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
class StandardListGenerationService {
 /** Niveau 6 : « Pathologies et activités » regroupe pathologies et examens de laboratoire. */
 public const ACTIVITY_TYPES=['pathology','laboratory_exam'];

 public function options(Organization $organization): array {
  $types=['care_level','target_population','pathology','laboratory_exam','facility_category'];
  $refs=CatalogReference::query()->where(fn($q)=>$q->whereNull('organization_id')->orWhere('organization_id',$organization->id))->where('is_active',true)->whereIn('reference_type',$types)->orderBy('depth')->orderBy('name')->get()->groupBy('reference_type');
  return collect($types)->mapWithKeys(fn($type)=>[$type=>$refs->get($type,collect())->values()])->all();
 }

 /**
  * AM-113 — Complète le contexte saisi avec la configuration médicale du
  * projet (AM-112) : niveau de soins, populations et pathologies deviennent
  * facultatifs dans le formulaire lorsque le projet est configuré.
  */
 public function withProjectDefaults(Project $project, array $context): array {
  $careLevels=$this->projectIds($project,'project_care_levels','care_level_id');
  $context['care_level_id']??=$careLevels->first();
  if(empty($context['target_population_ids'])) $context['target_population_ids']=$this->projectIds($project,'project_target_populations','target_population_id')->all();
  if(empty($context['pathology_ids'])&&empty($context['laboratory_exam_ids'])) $context['pathology_ids']=$this->projectIds($project,'project_pathology_populations','pathology_id')->unique()->values()->all();
  return $context;
 }

 public function generate(Project $project,array $context): Collection {
  return $this->generateFor($project,$this->projectIds($project,'project_care_levels','care_level_id')->push($context['care_level_id']??null)->filter()->unique(),$context);
 }

 /**
  * Niveau 2 — Produits proposés pour des choix pas encore enregistrés
  * (assistant « Créer un projet / programme », étape 3).
  * @param Collection<int,string> $careLevelIds
  */
 public function generateFor(Project $project,Collection $careLevelIds,array $context): Collection {
  // Niveaux retenus avec leurs parents (un produit rattaché à « Soins de
  // santé primaire » vaut pour ses programmes) et leurs descendants
  // (retenir un niveau couvre ses programmes).
  $careLevelIds=$this->expandHierarchy($careLevelIds->filter()->unique()->values());
  $query=ProductStandardMapping::query()->where('organization_id',$project->organization_id)->whereIn('care_level_id',$careLevelIds)
   ->when($context['facility_category_id']??null,fn($q,$id)=>$q->where(fn($n)=>$n->whereNull('facility_category_id')->orWhere('facility_category_id',$id)))
   ->where(fn($q)=>$q->whereNull('target_population_id')->orWhereIn('target_population_id',$context['target_population_ids']??[]));
  // Niveau 6 : pathologies et examens de laboratoire forment une seule liste
  // d'activités ; une correspondance vaut si chacun de ses critères est retenu.
  $ids=array_values(array_unique([...($context['pathology_ids']??[]),...($context['laboratory_exam_ids']??[])]));
  foreach(['pathology_id','laboratory_exam_id'] as $field) $query->where(fn($q)=>$q->whereNull($field)->orWhereIn($field,$ids));
  return Product::query()->where('organization_id',$project->organization_id)->where('is_active',true)->whereIn('id',$query->pluck('product_id')->unique())->with(['baseUnit:id,name','dosageForm:id,name','category:id,name'])->orderBy('name')->get();
 }

 /**
  * Niveau 6 — Pathologies et examens de laboratoire proposés pour le couple
  * niveau de soins × population : ceux qui portent au moins un produit de
  * l'organisation pour ces choix (correspondances du catalogue).
  * @param Collection<int,string> $careLevelIds
  * @param Collection<int,string> $populationIds
  * @return Collection<int,string>
  */
 public function suggestedActivityIds(string $organizationId,Collection $careLevelIds,Collection $populationIds): Collection {
  if($careLevelIds->isEmpty()) return collect();
  $mappings=ProductStandardMapping::query()->where('organization_id',$organizationId)
   ->whereIn('care_level_id',$this->expandHierarchy($careLevelIds->filter()->unique()->values()))
   ->where(fn($q)=>$q->whereNull('target_population_id')->orWhereIn('target_population_id',$populationIds))
   ->get(['pathology_id','laboratory_exam_id']);
  return $mappings->pluck('pathology_id')->concat($mappings->pluck('laboratory_exam_id'))->filter()->unique()->values();
 }

 /** Vrai lorsque la configuration médicale a changé depuis la version publiée. */
 public function needsRegeneration(Project $project, ?StandardListVersion $published): bool {
  if(!$published) return false;
  $same=fn($a,$b)=>collect($a??[])->sort()->values()->all()===collect($b??[])->sort()->values()->all();
  $populations=$this->projectIds($project,'project_target_populations','target_population_id')->all();
  $pathologies=$this->projectIds($project,'project_pathology_populations','pathology_id')->unique()->values()->all();
  if($populations===[]) return false;
  return !$same($published->target_population_ids,$populations)||(empty($published->laboratory_exam_ids)&&!$same($published->pathology_ids,$pathologies));
 }

 /**
  * Produits retenus par la Coordination dans la dernière version publiée de
  * la Liste Standard du projet (null : aucune version publiée).
  * @return array<int,string>|null
  */
 public function publishedProductIds(Project $project): ?array {
  $version=StandardListVersion::where('status','published')
   ->whereHas('standardList',fn($q)=>$q->where('scope_type','project')->where('scope_id',$project->id))
   ->orderByDesc('version_number')->first();
  return $version?->products()->pluck('products.id')->all();
 }

 /** Niveaux retenus et leurs descendants (catégories, programmes). @return Collection<int,string> */
 public function expandDescendants(Collection $ids): Collection {
  $result=$ids->values();
  $frontier=$ids;
  for($i=0;$i<CareLevelHierarchyService::MAX_DEPTH&&$frontier->isNotEmpty();$i++){
   $frontier=CatalogReference::whereIn('parent_id',$frontier)->where('is_active',true)->pluck('id')->diff($result);
   $result=$result->merge($frontier);
  }
  return $result->unique()->values();
 }

 /** @return Collection<int,string> */
 public function expandHierarchy(Collection $ids): Collection {
  $result=$ids->values();
  // Ancêtres.
  $frontier=$ids;
  for($i=0;$i<CareLevelHierarchyService::MAX_DEPTH&&$frontier->isNotEmpty();$i++){
   $frontier=CatalogReference::whereIn('id',$frontier)->whereNotNull('parent_id')->pluck('parent_id')->diff($result);
   $result=$result->merge($frontier);
  }
  // Descendants.
  $frontier=$ids;
  for($i=0;$i<CareLevelHierarchyService::MAX_DEPTH&&$frontier->isNotEmpty();$i++){
   $frontier=CatalogReference::whereIn('parent_id',$frontier)->where('is_active',true)->pluck('id')->diff($result);
   $result=$result->merge($frontier);
  }
  return $result->unique()->values();
 }

 private function projectIds(Project $project,string $table,string $column): Collection {
  return DB::table($table)->where('project_id',$project->id)->pluck($column);
 }
}
