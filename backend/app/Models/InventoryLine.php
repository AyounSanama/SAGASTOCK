<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class InventoryLine extends Model {
    use HasUuids; public $incrementing=false; protected $keyType='string';
    protected $fillable=['inventory_id','product_id','batch_id','theoretical_quantity','physical_quantity','variance_quantity','unit_cost','variance_value','justification','counted_by','counted_at'];
    protected function casts():array{return ['theoretical_quantity'=>'decimal:4','physical_quantity'=>'decimal:4','variance_quantity'=>'decimal:4','unit_cost'=>'decimal:4','variance_value'=>'decimal:4','counted_at'=>'datetime'];}
    public function inventory(){return $this->belongsTo(Inventory::class);} public function product(){return $this->belongsTo(Product::class);} public function batch(){return $this->belongsTo(Batch::class);}
}
