<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['organization_id', 'site_id', 'supplier_id', 'reference', 'order_reference', 'received_on', 'status', 'notes', 'created_by', 'validated_by', 'validated_at',
        // Niveau 7 : origine (couple ONG/Bailleur = projet, ou « Autre »).
        'origin_type', 'origin_project_id', 'origin_label'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'validated_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Niveau 7 : couple ONG/Bailleur d'origine (projet de la FOSA). */
    public function originProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'origin_project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }
}
