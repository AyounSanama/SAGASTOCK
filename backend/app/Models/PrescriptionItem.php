<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class PrescriptionItem extends Model { use HasUuids; public $incrementing=false; protected $keyType='string'; protected $fillable=['prescription_id','product_id','quantity_prescribed','quantity_dispensed','dosage','frequency','duration','instructions']; protected function casts(): array{return ['quantity_prescribed'=>'decimal:4','quantity_dispensed'=>'decimal:4'];} public function product(){return $this->belongsTo(Product::class);} }
