<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class DispensationItem extends Model { use HasUuids; public $incrementing=false; protected $keyType='string'; protected $fillable=['dispensation_id','prescription_item_id','product_id','batch_id','quantity']; protected function casts(): array{return ['quantity'=>'decimal:4'];} public function product(){return $this->belongsTo(Product::class);} public function batch(){return $this->belongsTo(Batch::class);} }
