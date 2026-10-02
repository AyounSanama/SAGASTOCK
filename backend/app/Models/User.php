<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = ['organization_id', 'name', 'first_name', 'last_name', 'username', 'email', 'phone', 'preferred_locale', 'password', 'is_active', 'must_change_password', 'read_only', 'last_login_at', 'password_changed_at', 'failed_login_attempts', 'locked_until'];
    protected $hidden = ['password', 'remember_token'];

    /** S-01 : un compte désactivé ou archivé perd immédiatement ses jetons. */
    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                $user->tokens()->delete();
            }
        });
        static::deleted(fn (User $user) => $user->tokens()->delete());
    }

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'must_change_password' => 'boolean', 'read_only' => 'boolean', 'last_login_at' => 'datetime', 'password_changed_at' => 'datetime', 'failed_login_attempts' => 'integer', 'locked_until' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot(['scope_type', 'scope_id'])->withTimestamps();
    }
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
    /** Permission de consultation (seules permissions d'un compte en lecture seule). */
    public static function isReadPermission(string $permission): bool
    {
        return str_ends_with($permission, '.view');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->read_only && ! self::isReadPermission($permission)) {
            return false;
        }
        $codes = collect([$permission])->merge($this->permissionAliases($permission));

        return $this->directPermissions()->whereIn('code', $codes)->exists()
            || $this->roles()->whereHas('permissions', fn($query) => $query->whereIn('code', $codes))->exists();
    }

    private function permissionAliases(string $permission): array
    {
        return match ($permission) {
            'configuration.platform.manage', 'configuration.organization.manage', 'configuration.project.manage', 'configuration.site.manage' => [
                'configuration.view',
                'configuration.platform.manage',
                'configuration.organization.manage',
                'configuration.project.manage',
                'configuration.site.manage',
            ],
            'settings.view' => ['settings.view', 'settings.manage'],
            'standard_lists.view' => ['standard_lists.view', 'catalog.view'],
            'products.view' => ['products.view', 'catalog.view'],
            'sites.view' => ['sites.view', 'structures.view'],
            'sites.manage' => ['sites.manage', 'structures.manage'],
            'structures.view' => ['structures.view', 'health_facilities.view', 'dispensing_sites.view'],
            'structures.manage' => ['structures.manage', 'health_facilities.manage', 'dispensing_sites.manage'],
            default => [$permission],
        };
    }
}
