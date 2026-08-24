<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrganizationConfigurationSync extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['organization_effective_configuration_id', 'organization_id', 'user_id', 'device_id', 'checksum', 'status', 'synced_at'];
    protected function casts(): array { return ['synced_at' => 'datetime']; }
}
