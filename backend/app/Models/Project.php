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
    public const STATUSES = ['draft' => 'Brouillon', 'active' => 'Actif', 'suspended' => 'Suspendu', 'closed' => 'Clôturé'];

    protected $fillable = ['organization_id', 'mission_id', 'code', 'name', 'implementing_partner', 'donor_reference_code', 'moh_program_code', 'responsible_name', 'responsible_contact', 'description', 'starts_on', 'ends_on', 'order_period_months', 'delivery_lead_time_months', 'safety_stock_months', 'inventory_date', 'order_submission_date', 'order_receipt_date', 'status', 'type', 'is_active'];

    /** Type de projet (décision E1) : un bailleur au plus dans les deux cas. */
    public const TYPES = [
        'donor_project' => 'Projet bailleur',
        'national_program' => 'Programme national',
    ];

    /** Bailleur affiché par défaut pour un programme national sans bailleur. */
    public const NATIONAL_PROGRAM_DEFAULT_DONOR = 'Ministère de la Santé';

    protected $appends = ['status_label', 'type_label'];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type ?? 'donor_project'] ?? (string) $this->type;
    }

    protected static function booted(): void
    {
        // Le statut pilote le cycle de vie ; is_active reste dérivé pour les
        // clients et filtres existants.
        static::saving(function (Project $project): void {
            if ($project->isDirty('status')) {
                $project->is_active = $project->status === 'active';
            } elseif ($project->isDirty('is_active')) {
                $project->status = $project->is_active
                    ? 'active'
                    : (in_array($project->status, ['draft', 'closed'], true) ? $project->status : 'suspended');
            }
        });
    }

    /** Mois décimaux (0,25 à 2) ; entier lorsque la valeur est ronde, pour les clients existants. */
    public function getSafetyStockMonthsAttribute(mixed $value): int|float|null
    {
        if ($value === null) {
            return null;
        }
        $months = (float) $value;

        return floor($months) === $months ? (int) $months : $months;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status ?? 'active'] ?? (string) $this->status;
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'order_period_months' => 'integer', 'delivery_lead_time_months' => 'integer', 'inventory_date' => 'date', 'order_submission_date' => 'date', 'order_receipt_date' => 'date', 'is_active' => 'boolean'];
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

    public function administrators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user', 'scope_id', 'user_id')
            ->wherePivot('scope_type', 'project')
            ->whereHas('roles', fn ($query) => $query->where('code', 'project_admin'))
            ->withTimestamps();
    }
}
