<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationEffectiveConfiguration extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['organization_id', 'configuration_category', 'intervention_action', 'platform_standard_id', 'platform_standard_assignment_id', 'configuration_version', 'previous_configuration_version', 'standard_version_number', 'configuration', 'changes', 'checksum', 'status', 'effective_at', 'applied_by', 'synchronization_status', 'synchronization_required_at'];
    protected function casts(): array { return ['configuration' => 'array', 'changes' => 'array', 'effective_at' => 'datetime', 'synchronization_required_at' => 'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function standard(): BelongsTo { return $this->belongsTo(PlatformStandard::class, 'platform_standard_id'); }
    public function assignment(): BelongsTo { return $this->belongsTo(PlatformStandardAssignment::class, 'platform_standard_assignment_id'); }
    public function appliedBy(): BelongsTo { return $this->belongsTo(User::class, 'applied_by'); }
    public function synchronizations() { return $this->hasMany(OrganizationConfigurationSync::class); }
}
