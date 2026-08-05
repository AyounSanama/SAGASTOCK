<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['organization_id', 'site_id', 'product_id', 'batch_id', 'movement_type', 'quantity', 'unit_cost', 'currency', 'reference_type', 'reference_id', 'client_reference', 'compensates_movement_id', 'reason', 'status', 'created_by', 'validated_by', 'validated_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'validated_at' => 'datetime'];
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

    public function compensates(): BelongsTo
    {
        return $this->belongsTo(self::class, 'compensates_movement_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Un mouvement validé est immuable.'));
        static::deleting(fn () => throw new \LogicException('Un mouvement validé ne peut pas être supprimé.'));
    }
}
