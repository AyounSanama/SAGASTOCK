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
        $officialRole = app(GovernanceService::class)->roleCode($user);
        return in_array($officialRole, [GovernanceService::SAGO_ADMIN, null], true)
            && $user->roles()
            ->wherePivot('scope_type', 'platform')
            ->exists();
    }

    public function organizationIds(User $user): Collection
    {
        if ($this->isPlatform($user)) return Organization::pluck('id');
        $direct = $user->roles()
            ->wherePivot('scope_type', 'organization')
            ->pluck('role_user.scope_id');
        if ($user->organization_id) {
            $direct->push($user->organization_id);
        }
        $projectOrganizations = Project::whereIn('id', $this->directProjectIds($user))
            ->pluck('organization_id');
        $siteOrganizations = HealthFacility::whereHas(
            'sites',
            fn (Builder $query) => $query->whereIn('sites.id', $this->directSiteIds($user)),
        )->pluck('organization_id');

        return $direct->merge($projectOrganizations)->merge($siteOrganizations)
            ->filter()->unique()->values();
    }

    public function directProjectIds(User $user): Collection
    {
        return $user->roles()
            ->wherePivot('scope_type', 'project')
            ->pluck('role_user.scope_id')->filter()->values();
    }

    public function projectIds(User $user): Collection
    {
        if ($this->isPlatform($user)) return Project::pluck('id');
        $role = app(GovernanceService::class)->roleCode($user);
        if ($role === GovernanceService::COORDINATION_ADMIN) {
            return Project::whereIn('organization_id', $this->coordinationOrganizationIds($user))
                ->pluck('id')->unique()->values();
        }
        if ($role === GovernanceService::PROJECT_ADMIN) {
            return $this->directProjectIds($user)->unique()->values();
        }
        if ($role === null) {
            return $this->directProjectIds($user)->merge(
                Project::whereIn('organization_id', $this->organizationIds($user))->pluck('id'),
            )->unique()->values();
        }
        return collect();
    }

    public function facilityIds(User $user): Collection
    {
        if ($this->isPlatform($user)) return HealthFacility::pluck('id');
        $role = app(GovernanceService::class)->roleCode($user);
        if ($role === GovernanceService::COORDINATION_ADMIN) {
            return HealthFacility::whereIn('organization_id', $this->coordinationOrganizationIds($user))
                ->pluck('id')->unique()->values();
        }
        if ($role === GovernanceService::PROJECT_ADMIN) {
            return HealthFacility::whereHas('projects', fn (Builder $q) =>
                $q->whereIn('projects.id', $this->directProjectIds($user)))
                ->pluck('id')->unique()->values();
        }
        if (in_array($role, [GovernanceService::SITE_ADMIN, GovernanceService::SITE_USER], true)) {
            return Site::whereIn('id', $this->directSiteIds($user))
                ->pluck('health_facility_id')->filter()->unique()->values();
        }
        return HealthFacility::whereIn('organization_id', $this->organizationIds($user))
            ->orWhereHas('projects', fn (Builder $q) => $q->whereIn('projects.id', $this->directProjectIds($user)))
            ->orWhereHas('sites', fn (Builder $q) => $q->whereIn('sites.id', $this->directSiteIds($user)))
            ->pluck('id')->filter()->unique()->values();
    }

    public function directSiteIds(User $user): Collection
    {
        return $user->roles()
            ->wherePivot('scope_type', 'site')
            ->pluck('role_user.scope_id')->filter()->values();
    }

    private function coordinationOrganizationIds(User $user): Collection
    {
        return $user->roles()
            ->whereIn('roles.code', ['coordination_admin', 'organization_admin'])
            ->wherePivot('scope_type', 'organization')
            ->pluck('role_user.scope_id')->filter()->values();
    }

    public function siteIds(User $user): Collection
    {
        if ($this->isPlatform($user)) return Site::pluck('id');
        $role = app(GovernanceService::class)->roleCode($user);
        if ($role === GovernanceService::COORDINATION_ADMIN) {
            return Site::whereIn('organization_id', $this->coordinationOrganizationIds($user))
                ->pluck('id')->unique()->values();
        }
        if ($role === GovernanceService::PROJECT_ADMIN) {
            return Site::whereIn('health_facility_id', $this->facilityIds($user))
                ->pluck('id')->unique()->values();
        }
        if (in_array($role, [GovernanceService::SITE_ADMIN, GovernanceService::SITE_USER], true)) {
            return $this->directSiteIds($user)->unique()->values();
        }
        return $this->directSiteIds($user)->merge(
            Site::whereIn('organization_id', $this->organizationIds($user))
                ->orWhereIn('health_facility_id', $this->facilityIds($user))->pluck('id'),
        )->filter()->unique()->values();
    }

    public function users(User $actor, ?Builder $query = null): Builder
    {
        $query ??= User::query();
        if ($this->isPlatform($actor)) return $query;
        $role = app(GovernanceService::class)->roleCode($actor);
        if ($role === GovernanceService::SITE_USER || $role === null) {
            return $query->whereKey($actor->id);
        }
        $organizations = $role === GovernanceService::COORDINATION_ADMIN
            ? $this->coordinationOrganizationIds($actor) : collect();
        $projects = in_array($role, [GovernanceService::COORDINATION_ADMIN, GovernanceService::PROJECT_ADMIN], true)
            ? $this->projectIds($actor) : collect();
        $sites = $this->siteIds($actor);
        return $query->where(function (Builder $users) use ($actor, $organizations, $projects, $sites) {
            $users->whereKey($actor->id);
            if ($organizations->isNotEmpty()) {
                $users->orWhereIn('organization_id', $organizations);
            }
            if ($organizations->isNotEmpty() || $projects->isNotEmpty() || $sites->isNotEmpty()) {
                $users->orWhereHas('roles', function (Builder $roles) use ($organizations, $projects, $sites) {
                $roles->where(function (Builder $scopes) use ($organizations, $projects, $sites) {
                    if ($organizations->isNotEmpty()) {
                        $scopes->orWhere(fn (Builder $q) => $q->where('role_user.scope_type', 'organization')->whereIn('role_user.scope_id', $organizations));
                    }
                    if ($projects->isNotEmpty()) {
                        $scopes->orWhere(fn (Builder $q) => $q->where('role_user.scope_type', 'project')->whereIn('role_user.scope_id', $projects));
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
        // Les rôles administratifs officiels obéissent toujours à la matrice
        // de gouvernance, même si une ancienne affectation leur a attribué par
        // erreur un périmètre plateforme.
        $governance = app(GovernanceService::class);
        $officialRole = $governance->roleCode($actor);
        if ($officialRole !== null) {
            $assignableCodes = $governance->assignableCodes($actor);

            return $assignableCodes === []
                ? Role::query()->whereRaw('1 = 0')
                : Role::query()
                    ->where('is_system', true)
                    ->where('is_active', true)
                    ->whereIn('code', $assignableCodes);
        }

        // Les anciens rôles administratifs personnalisés restent pilotés par
        // leurs permissions explicites, sans contourner le périmètre plateforme.
        if ($this->isPlatform($actor) && $actor->hasPermission('users.manage')) {
            return Role::query()->where('is_active', true);
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
