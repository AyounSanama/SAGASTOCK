<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformStandardVersion extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['platform_standard_id', 'version_number', 'status', 'snapshot', 'change_notes', 'created_by', 'published_by', 'published_at', 'archived_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'published_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(PlatformStandard::class, 'platform_standard_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function getLabelAttribute(): string
    {
        return 'v'.$this->version_number.'.0';
    }
}
