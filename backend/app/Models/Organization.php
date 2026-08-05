<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'code', 'name', 'legal_name', 'organization_type', 'logo_path',
        'email', 'phone', 'country_code', 'default_language', 'address',
        'manager_name', 'manager_title', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function donors(): HasMany
    {
        return $this->hasMany(Donor::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function healthFacilities(): HasMany
    {
        return $this->hasMany(HealthFacility::class);
    }
    public function sites(): HasMany { return $this->hasMany(Site::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function catalogReferences(): HasMany { return $this->hasMany(CatalogReference::class); }
    public function suppliers(): HasMany { return $this->hasMany(Supplier::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function batches(): HasMany { return $this->hasMany(Batch::class); }
    public function kits(): HasMany { return $this->hasMany(Kit::class); }
    public function standardLists(): HasMany { return $this->hasMany(StandardList::class); }
}
