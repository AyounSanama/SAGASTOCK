<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class CatalogReference extends Model {
 use HasUuids,SoftDeletes; public $incrementing=false; protected $keyType='string';
 protected $fillable=['organization_id','reference_type','code','name','description','metadata','version','is_active'];
 protected function casts():array{return ['metadata'=>'array','is_active'=>'boolean'];}
 public function organization():BelongsTo{return $this->belongsTo(Organization::class);}
}
