<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\Role;
use App\Models\HealthFacility;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserScopeService
{
    public function isPlatform(User $user): bool
    {
        return $user->roles()->wherePivot('scope_type', 'platform')->exists();
    }

    public function organizationIds(User $user): Collection
    {
        return $user->roles()->wherePivot('scope_type', 'organization')->pluck('role_user.scope_id')->filter()->values();
    }

    public function directProjectIds(User $user): Collection
    {
        return $user->roles()->wherePivot('scope_type', 'project')->pluck('role_user.scope_id')->filter()->values();
    }

    public function projectIds(User $user): Collection
    {
        if ($this->isPlatform($user)) return Project::pluck('id');
        return $this->directProjectIds($user)->merge(
            Project::whereIn('organization_id', $this->organizationIds($user))->pluck('id'),
        )->unique()->values();
    }

    public function facilityIds(User $user): Collection
    {
        if ($this->isPlatform($user)) return HealthFacility::pluck('id');
        $direct = $user->roles()->wherePivot('scope_type', 'facility')->pluck('role_user.scope_id');
        return $direct->merge(
            HealthFacility::whereIn('organization_id', $this->organizationIds($user))
                ->orWhereHas('projects', fn (Builder $q) => $q->whereIn('projects.id', $this->projectIds($user)))
                ->pluck('id')
        )->filter()->unique()->values();
    }

    public function siteIds(User $user): Collection
    {
        if ($this->isPlatform($user)) return Site::pluck('id');
        return $user->roles()->wherePivot('scope_type', 'site')->pluck('role_user.scope_id')
            ->merge(Site::whereIn('health_facility_id', $this->facilityIds($user))->pluck('id'))
            ->filter()->unique()->values();
    }

    public function users(User $actor, ?Builder $query = null): Builder
    {
        $query ??= User::query();
        if ($this->isPlatform($actor)) return $query;
        $organizations = $this->organizationIds($actor);
        $projects = $this->projectIds($actor);
        $facilities = $this->facilityIds($actor);
        $sites = $this->siteIds($actor);
        return $query->where(function (Builder $users) use ($actor, $organizations, $projects, $facilities, $sites) {
            $users->whereKey($actor->id);
            if ($organizations->isNotEmpty() || $projects->isNotEmpty()) {
                $users->orWhereHas('roles', function (Builder $roles) use ($organizations, $projects, $facilities, $sites) {
                $roles->where(function (Builder $scopes) use ($organizations, $projects, $facilities, $sites) {
                    if ($organizations->isNotEmpty()) {
                        $scopes->orWhere(fn (Builder $q) => $q->where('role_user.scope_type', 'organization')->whereIn('role_user.scope_id', $organizations));
                    }
                    if ($projects->isNotEmpty()) {
                        $scopes->orWhere(fn (Builder $q) => $q->where('role_user.scope_type', 'project')->whereIn('role_user.scope_id', $projects));
                    }
                    if ($facilities->isNotEmpty()) {
                        $scopes->orWhere(fn (Builder $q) => $q->where('role_user.scope_type', 'facility')->whereIn('role_user.scope_id', $facilities));
                    }
                    if ($sites->isNotEmpty()) {
                        $scopes->orWhere(fn (Builder $q) => $q->where('role_user.scope_type', 'site')->whereIn('role_user.scope_id', $sites));
                    }
                });
                });
            }
        });
    }

    public function canAccess(User $actor, User $target): bool
    {
        return $this->users($actor, User::withTrashed())->whereKey($target->id)->exists();
    }

    public function organizations(User $actor): Builder
    {
        return $this->isPlatform($actor)
            ? Organization::query()
            : Organization::whereIn('id', $this->organizationIds($actor));
    }

    public function archivedOrganizations(User $actor): Builder
    {
        return $this->isPlatform($actor)
            ? Organization::onlyTrashed()
            : Organization::onlyTrashed()->whereIn('id', $this->organizationIds($actor));
    }

    public function projects(User $actor): Builder
    {
        return $this->isPlatform($actor)
            ? Project::query()
            : Project::whereIn('id', $this->projectIds($actor));
    }

    public function facilities(User $actor): Builder
    {
        return $this->isPlatform($actor) ? HealthFacility::query() : HealthFacility::whereIn('id', $this->facilityIds($actor));
    }

    public function sites(User $actor): Builder
    {
        return $this->isPlatform($actor) ? Site::query() : Site::whereIn('id', $this->siteIds($actor));
    }

    public function allowsScope(User $actor, string $type, ?string $id): bool
    {
        if ($this->isPlatform($actor)) return true;
        if ($type === 'platform') return false;
        return match ($type) {
            'organization' => $this->organizationIds($actor)->contains($id),
            'project' => $this->projectIds($actor)->contains($id),
            'facility' => $this->facilityIds($actor)->contains($id),
            'site' => $this->siteIds($actor)->contains($id),
            default => false,
        };
    }

    public function roles(User $actor): Builder
    {
        if ($this->isPlatform($actor)) return Role::query();
        $organizations = $this->organizationIds($actor);
        $projects = $this->projectIds($actor);
        $facilities = $this->facilityIds($actor);
        $sites = $this->siteIds($actor);
        return Role::where('is_system', true)->orWhere(function (Builder $query) use ($organizations, $projects, $facilities, $sites) {
            $query->where(fn (Builder $q) => $q->where('scope_type', 'organization')->whereIn('scope_id', $organizations))
                ->orWhere(fn (Builder $q) => $q->where('scope_type', 'project')->whereIn('scope_id', $projects))
                ->orWhere(fn (Builder $q) => $q->where('scope_type', 'facility')->whereIn('scope_id', $facilities))
                ->orWhere(fn (Builder $q) => $q->where('scope_type', 'site')->whereIn('scope_id', $sites));
        });
    }

    public function assignableRoles(User $actor): Builder
    {
        if ($this->isPlatform($actor)) {
            return Role::query();
        }

        $roleCodes = $actor->roles()->pluck('roles.code');
        $allowedSystemCodes = collect();

        if ($roleCodes->contains('organization_admin')) {
            $allowedSystemCodes = $allowedSystemCodes->merge([
                'organization_admin', 'project_coordinator', 'facility_manager',
                'pharmacist', 'clinician', 'supervisor',
            ]);
        }
        if ($roleCodes->contains('project_coordinator')) {
            $allowedSystemCodes = $allowedSystemCodes->merge([
                'project_coordinator', 'facility_manager', 'pharmacist',
                'clinician', 'supervisor',
            ]);
        }
        if ($roleCodes->contains('facility_manager')) {
            $allowedSystemCodes = $allowedSystemCodes->merge([
                'facility_manager', 'pharmacist', 'clinician',
            ]);
        }

        $organizations = $this->organizationIds($actor);
        $projects = $this->projectIds($actor);
        $facilities = $this->facilityIds($actor);
        $sites = $this->siteIds($actor);

        return Role::where(function (Builder $query) use ($allowedSystemCodes, $organizations, $projects, $facilities, $sites) {
            $query->where(fn (Builder $system) => $system
                ->where('is_system', true)
                ->whereIn('code', $allowedSystemCodes->unique()))
                ->orWhere(function (Builder $custom) use ($organizations, $projects, $facilities, $sites) {
                    $custom->where('is_system', false)
                        ->where(function (Builder $scopes) use ($organizations, $projects, $facilities, $sites) {
                            $scopes->where(fn (Builder $q) => $q
                                ->where('scope_type', 'organization')
                                ->whereIn('scope_id', $organizations))
                                ->orWhere(fn (Builder $q) => $q
                                    ->where('scope_type', 'project')
                                    ->whereIn('scope_id', $projects))
                                ->orWhere(fn (Builder $q) => $q
                                    ->where('scope_type', 'facility')
                                    ->whereIn('scope_id', $facilities))
                                ->orWhere(fn (Builder $q) => $q
                                    ->where('scope_type', 'site')
                                    ->whereIn('scope_id', $sites));
                        });
                });
        });
    }

    public function canManageRole(User $actor, Role $role): bool
    {
        if ($role->is_system) return false;
        return $this->isPlatform($actor) || $this->allowsScope($actor, $role->scope_type, $role->scope_id);
    }
}
