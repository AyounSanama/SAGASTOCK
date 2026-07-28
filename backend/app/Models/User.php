<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'first_name', 'last_name', 'username', 'email', 'phone', 'password', 'is_active', 'must_change_password', 'last_login_at', 'password_changed_at', 'failed_login_attempts', 'locked_until'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'must_change_password' => 'boolean', 'last_login_at' => 'datetime', 'password_changed_at' => 'datetime', 'failed_login_attempts' => 'integer', 'locked_until' => 'datetime'];
    }

    public function uniqueIds(): array { return ['uuid']; }
    public function roles(): BelongsToMany { return $this->belongsToMany(Role::class)->withPivot(['scope_type', 'scope_id'])->withTimestamps(); }
    public function devices(): HasMany { return $this->hasMany(Device::class); }
    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', fn ($query) => $query->where('code', $permission))->exists();
    }
}



