<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;use Illuminate\Database\Eloquent\Relations\BelongsToMany;use Illuminate\Database\Eloquent\SoftDeletes;
class Kit extends Model {use HasUuids,SoftDeletes;public $incrementing=false;protected $keyType='string';protected $fillable=['organization_id','code','name','description','is_active'];protected function casts():array{return ['is_active'=>'boolean'];}public function organization():BelongsTo{return $this->belongsTo(Organization::class);}public function products():BelongsToMany{return $this->belongsToMany(Product::class,'kit_items')->withPivot('quantity')->withTimestamps();}}
