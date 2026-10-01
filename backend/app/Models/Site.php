<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class Site extends Model {
    use HasUuids, SoftDeletes;
    public $incrementing=false; protected $keyType='string';
    protected $fillable=['organization_id','health_facility_id','department_id','pharmacy_id','code','name','site_type','location','is_active','is_primary'];
    protected function casts():array{return ['is_active'=>'boolean'];}
    public function healthFacility():BelongsTo{return $this->belongsTo(HealthFacility::class);}
    public function organization():BelongsTo{return $this->belongsTo(Organization::class);}
    public function department():BelongsTo{return $this->belongsTo(Department::class);}
    public function pharmacy():BelongsTo{return $this->belongsTo(Pharmacy::class);}
}
