<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mission extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'organization_id',
        'country_id',
        'code',
        'name',
        'starts_on',
        'ends_on',
        'address',
        'manager_name',
        'phone',
        'email',
        'description',
        'is_active',
        // Langues choisies par la Coordination elle-même (Ma Coordination).
        'default_language',
        'additional_languages',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean', 'additional_languages' => 'array'];
    }

    /** Langue principale puis langues supplémentaires, avec leur nom. @return array<string,string> */
    public function languageLabels(): array
    {
        $catalog = config('pharmacare_languages.catalog', []);

        return collect([$this->default_language ?: 'fr', ...($this->additional_languages ?? [])])->unique()
            ->mapWithKeys(fn (string $code) => [$code => $catalog[$code] ?? $code])->all();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
