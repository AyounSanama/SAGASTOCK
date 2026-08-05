<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Prescription extends Model { use HasUuids; public $incrementing=false; protected $keyType='string'; protected $fillable=['organization_id','patient_id','site_id','reference','prescribed_on','prescriber_name','status','diagnosis','notes','created_by','validated_by','validated_at']; protected function casts(): array{return ['prescribed_on'=>'date','validated_at'=>'datetime'];} public function patient(){return $this->belongsTo(Patient::class);} public function site(){return $this->belongsTo(Site::class);} public function items(){return $this->hasMany(PrescriptionItem::class);} public function dispensations(){return $this->hasMany(Dispensation::class);} }
