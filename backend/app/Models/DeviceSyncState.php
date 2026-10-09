<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dernier état de synchronisation déclaré par un téléphone.
 *
 * `issues` : envois refusés et conflits, chacun
 * {id, kind: refused|conflict, module, reference, reason, occurred_at, reported}.
 */
class DeviceSyncState extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'device_id', 'user_id', 'site_id', 'last_success_at', 'reported_at',
        'pending_total', 'pending_by_module', 'issues',
    ];

    protected function casts(): array
    {
        return [
            'last_success_at' => 'datetime',
            'reported_at' => 'datetime',
            'pending_total' => 'integer',
            'pending_by_module' => 'array',
            'issues' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
