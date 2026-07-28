<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Department extends Model {
    use HasUuids, SoftDeletes;
    public $incrementing=false; protected $keyType='string';
    protected $fillable=['health_facility_id','code','name','department_type','is_active'];
    protected function casts():array{return ['is_active'=>'boolean'];}
    public function healthFacility():BelongsTo{return $this->belongsTo(HealthFacility::class);}
    public function pharmacies():HasMany{return $this->hasMany(Pharmacy::class);}
    public function sites():HasMany{return $this->hasMany(Site::class);}
}
