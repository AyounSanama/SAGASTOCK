<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HealthFacility extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['organization_id', 'mission_id', 'code', 'name', 'facility_type', 'care_level', 'email', 'phone', 'address', 'region', 'district', 'locality', 'latitude', 'longitude', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function mission(): BelongsTo { return $this->belongsTo(Mission::class); }
    public function projects(): BelongsToMany { return $this->belongsToMany(Project::class)->withTimestamps(); }
    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function archivedDepartments(): HasMany { return $this->hasMany(Department::class)->onlyTrashed(); }
    public function pharmacies(): HasMany { return $this->hasMany(Pharmacy::class); }
    public function archivedPharmacies(): HasMany { return $this->hasMany(Pharmacy::class)->onlyTrashed(); }
    public function sites(): HasMany { return $this->hasMany(Site::class); }
    public function archivedSites(): HasMany { return $this->hasMany(Site::class)->onlyTrashed(); }
}
