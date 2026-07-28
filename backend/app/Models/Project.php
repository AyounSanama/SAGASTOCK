<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['organization_id', 'mission_id', 'code', 'name', 'description', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function donors(): BelongsToMany
    {
        return $this->belongsToMany(Donor::class, 'project_donors')
            ->withPivot(['funding_amount', 'currency', 'agreement_reference'])->withTimestamps();
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class)->withTimestamps();
    }

    public function healthFacilities(): BelongsToMany
    {
        return $this->belongsToMany(HealthFacility::class)->withTimestamps();
    }
}
