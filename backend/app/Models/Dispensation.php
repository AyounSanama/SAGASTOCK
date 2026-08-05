<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Dispensation extends Model { use HasUuids; public $incrementing=false; protected $keyType='string'; protected $fillable=['organization_id','patient_id','prescription_id','site_id','offline_uuid','reference','dispensed_at','status','notes','dispensed_by']; protected function casts(): array{return ['dispensed_at'=>'datetime'];} public function patient(){return $this->belongsTo(Patient::class);} public function prescription(){return $this->belongsTo(Prescription::class);} public function site(){return $this->belongsTo(Site::class);} public function items(){return $this->hasMany(DispensationItem::class);} }
