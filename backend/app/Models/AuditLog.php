<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AuditLog extends Model {
    /** S-05 : sujets et événements qui touchent aux données de santé. */
    public const HEALTH_SUBJECTS = [Patient::class, Prescription::class, Dispensation::class];
    public const HEALTH_EVENT_PREFIXES = ['patient.', 'prescription.', 'dispensation.'];

    protected $fillable = ['user_id','event','auditable_type','auditable_id','old_values','new_values','ip_address','user_agent'];
    protected function casts(): array { return ['old_values'=>'array','new_values'=>'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function concernsHealthData(string $event, ?string $subjectType): bool
    {
        return in_array($subjectType, self::HEALTH_SUBJECTS, true)
            || collect(self::HEALTH_EVENT_PREFIXES)->contains(fn (string $prefix) => str_starts_with($event, $prefix));
    }

    /** Journal global (Admin Sago, tableaux de bord) : jamais d'événement de santé. */
    public function scopeWithoutHealthData(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('auditable_type')->orWhereNotIn('auditable_type', self::HEALTH_SUBJECTS);
        })->where(function (Builder $q): void {
            foreach (self::HEALTH_EVENT_PREFIXES as $prefix) {
                $q->where('event', 'not like', $prefix.'%');
            }
        });
    }
}
