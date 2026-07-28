<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;use Illuminate\Database\Eloquent\Relations\HasMany;use Illuminate\Database\Eloquent\SoftDeletes;
class Supplier extends Model {use HasUuids,SoftDeletes;public $incrementing=false;protected $keyType='string';protected $fillable=['organization_id','code','name','supplier_type','email','phone','address','country_code','is_active'];protected function casts():array{return ['is_active'=>'boolean'];}public function organization():BelongsTo{return $this->belongsTo(Organization::class);}public function batches():HasMany{return $this->hasMany(Batch::class);}}
