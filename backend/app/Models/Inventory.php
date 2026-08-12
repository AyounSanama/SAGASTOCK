<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Inventory extends Model {
    use HasUuids; public $incrementing=false; protected $keyType='string';
    protected $fillable=['organization_id','site_id','offline_uuid','reference','inventory_type','period_date','status','frozen_at','submitted_at','validated_at','created_by','submitted_by','validated_by','notes','rejection_reason','theoretical_value','physical_value','variance_value'];
    protected function casts():array{return ['period_date'=>'date','frozen_at'=>'datetime','submitted_at'=>'datetime','validated_at'=>'datetime','theoretical_value'=>'decimal:4','physical_value'=>'decimal:4','variance_value'=>'decimal:4'];}
    public function organization(){return $this->belongsTo(Organization::class);} public function site(){return $this->belongsTo(Site::class);} public function lines(){return $this->hasMany(InventoryLine::class);}
}
