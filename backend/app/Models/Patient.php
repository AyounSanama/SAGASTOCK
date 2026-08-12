<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Patient extends Model {
    use HasUuids, SoftDeletes;
    public $incrementing=false; protected $keyType='string';
    protected $fillable=['organization_id','client_reference','code','first_name','last_name','date_of_birth','sex','phone','external_identifier','address','allergies','clinical_notes','is_active'];
    protected function casts(): array{return ['date_of_birth'=>'date','is_active'=>'boolean'];}
    public function prescriptions(){return $this->hasMany(Prescription::class);}
    public function dispensations(){return $this->hasMany(Dispensation::class);}
}
