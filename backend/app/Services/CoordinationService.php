<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AM-172 (niveau 3) — « Ma Coordination » : validation, refus, suspension et
 * réactivation des FOSA, suspension des comptes. Règles partagées par le Web
 * et l'API ; toujours limitées aux missions de la Coordination connectée.
 */
class CoordinationService
{
    /** Événements du journal affichés dans « Journal des actions ». */
    public const JOURNAL_EVENTS = [
        'facility.created' => 'a déclaré la FOSA',
        'facility.updated' => 'a modifié la FOSA',
        'facility.validated' => 'a validé la FOSA',
        'facility.refused' => 'a refusé la FOSA',
        'facility.suspended' => 'a suspendu la FOSA',
        'facility.reactivated' => 'a réactivé la FOSA',
        'user.created' => 'a créé le compte',
        'user.suspended' => 'a suspendu le compte',
        'user.reactivated' => 'a réactivé le compte',
        'project.created' => 'a créé le projet',
        'project.updated' => 'a modifié le projet',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly UserScopeService $scopes,
        private readonly GovernanceService $governance,
    ) {}

    public function assertCoordinator(User $actor): void
    {
        abort_unless($this->governance->roleCode($actor) === GovernanceService::COORDINATION_ADMIN, 403);
    }

    /** FOSA des projets (ou des missions) de la Coordination. */
    public function facilities(User $actor): Builder
    {
        $missionIds = $this->scopes->coordinationMissionIds($actor);

        return HealthFacility::query()->where(fn (Builder $query) => $query
            ->whereIn('mission_id', $missionIds)
            ->orWhereHas('projects', fn (Builder $projects) => $projects->whereIn('projects.mission_id', $missionIds)));
    }

    public function facility(User $actor, string $id): HealthFacility
    {
        $this->assertCoordinator($actor);

        return $this->facilities($actor)->whereKey($id)->firstOrFail();
    }

    public function validate(Request $request, HealthFacility $facility): void
    {
        $this->transition($request, $facility, [HealthFacility::STATUS_PENDING], 'facility.validated', [
            'validation_status' => HealthFacility::STATUS_VALIDATED,
            'validated_at' => now(), 'validated_by' => $request->user()->id, 'refusal_reason' => null,
        ], 'Seule une FOSA en attente peut être validée.');
    }

    public function refuse(Request $request, HealthFacility $facility, string $reason): void
    {
        $this->transition($request, $facility, [HealthFacility::STATUS_PENDING], 'facility.refused', [
            'validation_status' => HealthFacility::STATUS_REFUSED, 'refusal_reason' => $reason,
        ], 'Seule une FOSA en attente peut être refusée.');
    }

    /** Suspension : les comptes de la FOSA perdent l'accès (EnsureAccountIsActive). */
    public function suspend(Request $request, HealthFacility $facility, string $reason): void
    {
        $this->transition($request, $facility, [HealthFacility::STATUS_VALIDATED], 'facility.suspended', [
            'validation_status' => HealthFacility::STATUS_SUSPENDED, 'suspended_at' => now(), 'suspension_reason' => $reason,
        ], 'Seule une FOSA validée peut être suspendue.');
        $this->facilityAccounts(collect([$facility->id]))->get()->each(fn (User $user) => $user->tokens()->delete());
    }

    public function reactivate(Request $request, HealthFacility $facility): void
    {
        $this->transition($request, $facility, [HealthFacility::STATUS_SUSPENDED], 'facility.reactivated', [
            'validation_status' => HealthFacility::STATUS_VALIDATED, 'suspended_at' => null, 'suspension_reason' => null,
        ], 'Seule une FOSA suspendue peut être réactivée.');
    }

    /**
     * Suspendre ou réactiver un compte : Admins Projet et Coordination en
     * lecture seule de ses projets, comptes de ses FOSA. Jamais son propre compte.
     */
    public function setAccountActive(Request $request, User $target, bool $active): void
    {
        $actor = $request->user();
        $this->assertCoordinator($actor);
        abort_if($actor->read_only || $actor->is($target), 403, 'Vous ne pouvez pas gérer ce compte.');
        abort_unless($this->manageableAccounts($actor)->whereKey($target->id)->exists(), 404);
        if ($target->is_active === $active) {
            return;
        }
        $target->update(['is_active' => $active]);
        if (! $active) {
            $target->tokens()->delete();
        }
        $this->audit->record($request, $active ? 'user.reactivated' : 'user.suspended', $target, ['is_active' => ! $active], ['is_active' => $active]);
    }

    /** Comptes que la Coordination peut suspendre ou réactiver. */
    public function manageableAccounts(User $actor): Builder
    {
        $missionIds = $this->scopes->coordinationMissionIds($actor);
        $projectIds = Project::whereIn('mission_id', $missionIds)->pluck('id');
        $siteIds = Site::whereIn('health_facility_id', $this->facilities($actor)->pluck('id'))->pluck('id');

        return User::query()->whereKeyNot($actor->id)->whereHas('roles', fn (Builder $roles) => $roles->where(fn (Builder $scope) => $scope
            ->where(fn (Builder $q) => $q->where('roles.code', 'project_admin')->where('role_user.scope_type', 'project')->whereIn('role_user.scope_id', $projectIds))
            ->orWhere(fn (Builder $q) => $q->where('roles.code', 'coordination_admin')->where('role_user.scope_type', 'mission')->whereIn('role_user.scope_id', $missionIds))
            ->orWhere(fn (Builder $q) => $q->whereIn('roles.code', ['site_admin', 'site_user'])->where('role_user.scope_type', 'site')->whereIn('role_user.scope_id', $siteIds))));
    }

    /** Comptes des FOSA données (Admin Site et Utilisateur Site). */
    public function facilityAccounts(Collection $facilityIds): Builder
    {
        $siteIds = Site::whereIn('health_facility_id', $facilityIds)->pluck('id');

        return User::query()->with(['roles' => fn ($query) => $query->wherePivot('scope_type', 'site')])
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('roles.code', ['site_admin', 'site_user'])
                ->where('role_user.scope_type', 'site')->whereIn('role_user.scope_id', $siteIds));
    }

    /** Statut affiché d'un compte : « À activer » tant que la première connexion n'est pas faite (M-05). */
    public static function accountStatus(User $user): array
    {
        if (! $user->is_active) {
            return ['label' => 'Suspendu', 'tone' => 'danger'];
        }
        if ($user->last_login_at === null || $user->must_change_password) {
            return ['label' => 'À activer', 'tone' => 'info'];
        }

        return ['label' => 'Actif', 'tone' => 'success'];
    }

    /**
     * Données de « Ma Coordination » pour une mission : projets et programmes,
     * FOSA par statut, comptes. Partagées par le Web et l'API.
     *
     * @return array<string, mixed>
     */
    public function overview(User $actor, Mission $mission): array
    {
        $projects = $mission->projects()->with(['donors:id,name'])->withCount([
            'healthFacilities as validated_facilities_count' => fn ($q) => $q->where('validation_status', HealthFacility::STATUS_VALIDATED),
            'healthFacilities as pending_facilities_count' => fn ($q) => $q->where('validation_status', HealthFacility::STATUS_PENDING),
        ])->orderBy('code')->get();
        $admins = User::whereHas('roles', fn (Builder $roles) => $roles->where('roles.code', 'project_admin')
            ->where('role_user.scope_type', 'project')->whereIn('role_user.scope_id', $projects->pluck('id')))
            ->with(['roles' => fn ($query) => $query->where('roles.code', 'project_admin')->wherePivot('scope_type', 'project')])->get();
        $projects->each(function (Project $project) use ($admins): void {
            $admin = $admins->first(fn (User $user) => $user->roles->contains(fn ($role) => $role->pivot->scope_id === $project->id));
            $project->setAttribute('admin_name', $admin?->name);
            $project->setAttribute('donor_name', $project->donors->first()?->name
                ?? ($project->type === 'national_program' ? Project::NATIONAL_PROGRAM_DEFAULT_DONOR : null));
        });

        $facilities = $this->facilities($actor)->with(['projects:id,code,name', 'facilityCategory:id,name', 'careLevel:id,name'])->orderBy('name')->get();
        $accounts = $this->facilityAccounts($facilities->pluck('id'))->orderBy('name')->get();
        $coordinationAccounts = $this->manageableAccounts($actor)->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('roles.code', ['site_admin', 'site_user']))
            ->with(['roles' => fn ($query) => $query->whereIn('roles.code', ['project_admin', 'coordination_admin'])])->orderBy('name')->get();

        return [
            'projects' => $projects,
            'facilities' => $facilities,
            'facility_accounts' => $accounts,
            'coordination_accounts' => $coordinationAccounts,
            'stats' => [
                'projects' => $projects->count(),
                'donor_projects' => $projects->where('type', '!=', 'national_program')->count(),
                'national_programs' => $projects->where('type', 'national_program')->count(),
                'validated' => $facilities->where('validation_status', HealthFacility::STATUS_VALIDATED)->count(),
                'validated_projects' => $facilities->where('validation_status', HealthFacility::STATUS_VALIDATED)->flatMap->projects->pluck('id')->unique()->count(),
                'pending' => $facilities->where('validation_status', HealthFacility::STATUS_PENDING)->count(),
                'active_accounts' => $coordinationAccounts->where('is_active', true)->count() + $accounts->where('is_active', true)->count(),
                'active_coordination_accounts' => $coordinationAccounts->where('is_active', true)->count(),
                'active_facility_accounts' => $accounts->where('is_active', true)->count(),
            ],
        ];
    }

    /** Journal des actions de la Coordination : FOSA, comptes et projets de son périmètre. */
    public function journal(User $actor, ?int $days = null, int $limit = 50): Collection
    {
        $facilityIds = $this->facilities($actor)->pluck('id')->map(fn ($id) => (string) $id);
        $projectIds = Project::whereIn('mission_id', $this->scopes->coordinationMissionIds($actor))->pluck('id')->map(fn ($id) => (string) $id);
        $userIds = $this->manageableAccounts($actor)->pluck('id')->map(fn ($id) => (string) $id);

        return AuditLog::query()->with('user:id,name')->withoutHealthData()
            ->whereIn('event', array_keys(self::JOURNAL_EVENTS))
            ->where(fn (Builder $query) => $query
                ->where(fn ($q) => $q->where('auditable_type', HealthFacility::class)->whereIn('auditable_id', $facilityIds))
                ->orWhere(fn ($q) => $q->where('auditable_type', Project::class)->whereIn('auditable_id', $projectIds))
                ->orWhere(fn ($q) => $q->where('auditable_type', User::class)->whereIn('auditable_id', $userIds)))
            ->when($days, fn ($query) => $query->where('created_at', '>=', now()->subDays($days)))
            ->latest()->limit($limit)->get()
            ->map(function (AuditLog $log) use ($actor) {
                $subject = match ($log->auditable_type) {
                    HealthFacility::class => HealthFacility::withTrashed()->find($log->auditable_id)?->name,
                    Project::class => Project::withTrashed()->find($log->auditable_id)?->code,
                    User::class => User::withTrashed()->find($log->auditable_id)?->username,
                    default => null,
                };

                return [
                    'id' => $log->id,
                    'event' => $log->event,
                    'actor' => $log->user_id === $actor->id ? 'Vous' : ($log->user?->name ?? 'Système'),
                    'is_self' => $log->user_id === $actor->id,
                    'action' => self::JOURNAL_EVENTS[$log->event],
                    'subject' => $subject,
                    'reason' => $log->new_values['refusal_reason'] ?? $log->new_values['suspension_reason'] ?? null,
                    'created_at' => $log->created_at,
                ];
            });
    }

    private function transition(Request $request, HealthFacility $facility, array $from, string $event, array $changes, string $message): void
    {
        abort_unless(in_array($facility->validation_status, $from, true), 422, $message);
        DB::transaction(function () use ($request, $facility, $event, $changes): void {
            $old = $facility->only(array_keys($changes));
            $facility->update($changes);
            $this->audit->record($request, $event, $facility, $old, $facility->only(array_keys($changes)));
        });
    }
}
