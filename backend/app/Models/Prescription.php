<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Prescription extends Model {
    use HasUuids;
    public $incrementing=false; protected $keyType='string';
    protected $fillable=['organization_id','client_reference','patient_id','site_id','reference','prescribed_on','prescriber_name','service_origin','attachment_path','attachment_original_name','attachment_mime_type','attachment_size','attachment_captured_at','status','diagnosis','notes','clinical_validation_notes','rejection_reason','protocol_confirmed','dosage_confirmed','contraindications_checked','created_by','validated_by','validated_at'];
    protected function casts(): array{return ['prescribed_on'=>'date','attachment_captured_at'=>'datetime','attachment_size'=>'integer','validated_at'=>'datetime','protocol_confirmed'=>'boolean','dosage_confirmed'=>'boolean','contraindications_checked'=>'boolean'];}
    public function patient(){return $this->belongsTo(Patient::class);}
    public function site(){return $this->belongsTo(Site::class);}
    public function items(){return $this->hasMany(PrescriptionItem::class);}
    public function dispensations(){return $this->hasMany(Dispensation::class);}
}
