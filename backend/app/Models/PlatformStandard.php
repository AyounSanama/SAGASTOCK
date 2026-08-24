<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformStandard extends Model
{
    use HasUuids, SoftDeletes;

    public const CATEGORIES = [
        'general_reference' => 'Référentiels généraux',
        'organization_template' => 'Modèles d’organisation',
        'access_profile' => 'Profils standards d’accès',
        'technical_standard' => 'Standards techniques',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['category', 'code', 'name', 'description', 'definition', 'status', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PlatformStandardVersion::class);
    }

    public function latestVersion()
    {
        return $this->hasOne(PlatformStandardVersion::class)->latestOfMany('version_number');
    }

    public function publishedVersion()
    {
        return $this->hasOne(PlatformStandardVersion::class)->where('status', 'published')->latestOfMany('version_number');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PlatformStandardAssignment::class);
    }
}
