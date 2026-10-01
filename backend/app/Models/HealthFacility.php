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

    /** Cycle de validation par la Coordination (AM-162 / AM-172). */
    public const STATUS_PENDING = 'pending';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REFUSED = 'refused';
    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'En attente de validation',
        self::STATUS_VALIDATED => 'Validée',
        self::STATUS_REFUSED => 'Refusée',
        self::STATUS_SUSPENDED => 'Suspendue',
    ];

    /** Paramètres d'approvisionnement Pharmacie du projet → FOSA (DEC-08). */
    public const SUPPLY_FIELDS = ['order_period_months', 'delivery_lead_time_months', 'safety_stock_months', 'inventory_date', 'order_submission_date', 'order_receipt_date'];

    /** Stock de sécurité, en mois (décision C-05). */
    public const SAFETY_STOCK_OPTIONS = [0.25, 0.5, 0.75, 1, 1.5, 2];

    protected $fillable = ['organization_id', 'mission_id', 'code', 'name', 'facility_type', 'care_level', 'email', 'phone', 'address', 'region', 'district', 'locality', 'latitude', 'longitude', 'is_active',
        'validation_status', 'declared_by', 'validated_at', 'validated_by', 'refusal_reason', 'suspended_at', 'suspension_reason',
        'facility_category_id', 'care_level_id', 'order_period_months', 'delivery_lead_time_months', 'safety_stock_months',
        'inventory_date', 'order_submission_date', 'order_receipt_date'];

    protected $appends = ['validation_status_label'];

    protected function casts(): array { return ['is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7',
        'validated_at' => 'datetime', 'suspended_at' => 'datetime', 'order_period_months' => 'integer', 'delivery_lead_time_months' => 'integer',
        'safety_stock_months' => 'float', 'inventory_date' => 'date', 'order_submission_date' => 'date', 'order_receipt_date' => 'date']; }

    public function getValidationStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->validation_status ?? self::STATUS_VALIDATED] ?? (string) $this->validation_status;
    }

    public function isValidated(): bool
    {
        return $this->validation_status === self::STATUS_VALIDATED;
    }

    public function facilityCategory(): BelongsTo { return $this->belongsTo(CatalogReference::class, 'facility_category_id'); }
    public function careLevel(): BelongsTo { return $this->belongsTo(CatalogReference::class, 'care_level_id'); }
    public function targetPopulations(): BelongsToMany { return $this->belongsToMany(CatalogReference::class, 'health_facility_target_populations', 'health_facility_id', 'target_population_id')->withTimestamps(); }
    public function pathologies(): BelongsToMany { return $this->belongsToMany(CatalogReference::class, 'health_facility_pathologies', 'health_facility_id', 'pathology_id')->withTimestamps(); }
    public function primarySite(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(Site::class)->where('is_primary', true); }
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
