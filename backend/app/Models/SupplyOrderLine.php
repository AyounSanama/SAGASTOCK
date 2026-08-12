<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyOrderLine extends Model
{
    use HasUuids;
    protected $fillable = ['supply_order_id','product_id','requested_quantity','approved_quantity','prepared_quantity','justification'];
    protected function casts(): array { return ['requested_quantity'=>'decimal:4','approved_quantity'=>'decimal:4','prepared_quantity'=>'decimal:4']; }
    public function order(): BelongsTo { return $this->belongsTo(SupplyOrder::class, 'supply_order_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
