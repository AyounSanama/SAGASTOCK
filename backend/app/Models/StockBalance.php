<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['organization_id', 'site_id', 'product_id', 'batch_id', 'theoretical_quantity', 'physical_quantity', 'reserved_quantity', 'last_movement_at'];

    protected $appends = ['available_quantity'];

    protected function casts(): array
    {
        return ['theoretical_quantity' => 'decimal:4', 'physical_quantity' => 'decimal:4', 'reserved_quantity' => 'decimal:4', 'last_movement_at' => 'datetime'];
    }

    public function getAvailableQuantityAttribute(): string
    {
        return bcsub((string) $this->theoretical_quantity, (string) $this->reserved_quantity, 4);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
