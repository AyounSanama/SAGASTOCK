<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductCode extends Model {use HasUuids;public $incrementing=false;protected $keyType='string';protected $fillable=['product_id','code_type','value','is_primary'];protected function casts():array{return ['is_primary'=>'boolean'];}public function product():BelongsTo{return $this->belongsTo(Product::class);}}
