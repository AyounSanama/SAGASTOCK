<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptItem extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['receipt_id', 'product_id', 'batch_id', 'quantity_ordered', 'quantity_received', 'quantity_accepted', 'quantity_rejected', 'unit_cost', 'discrepancy_reason'];
    protected function casts(): array { return ['quantity_ordered' => 'decimal:4', 'quantity_received' => 'decimal:4', 'quantity_accepted' => 'decimal:4', 'quantity_rejected' => 'decimal:4', 'unit_cost' => 'decimal:4']; }
    public function receipt(): BelongsTo { return $this->belongsTo(Receipt::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
}
