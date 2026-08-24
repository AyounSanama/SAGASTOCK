<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformStandardAssignment extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['organization_id', 'platform_standard_id', 'platform_standard_version_id', 'status', 'assigned_by', 'published_by', 'published_at'];
    protected function casts(): array { return ['published_at' => 'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function standard(): BelongsTo { return $this->belongsTo(PlatformStandard::class, 'platform_standard_id'); }
    public function version(): BelongsTo { return $this->belongsTo(PlatformStandardVersion::class, 'platform_standard_version_id'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }
}
