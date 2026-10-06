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
use Illuminate\Support\Facades\Gate;

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
        $facility = HealthFacility::findOrFail($id);
        Gate::forUser($actor)->authorize('coordinate', $facility);

        return $facility;
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
        Gate::forUser($request->user())->authorize('setActive', $target);
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

    /** Délai au-delà duquel une FOSA sans contact est « à surveiller » (maquettes). */
    public const SYNC_LATE_DAYS = 3;

    /** Fenêtre de comptage des opérations refusées par le serveur. */
    public const SYNC_FAILURE_DAYS = 7;

    /**
     * Tableau de bord de la Coordination (maquettes Coordination 07 et 08) :
     * chiffres réels pour les FOSA et la synchronisation. Les ruptures,
     * péremptions et graphiques arrivent avec les analyses de base (niveau 9).
     *
     * Synchronisation d'une FOSA, calculée côté serveur :
     * - dernier contact = dernier appel de l'API par un de ses comptes (jeton) ou dernière connexion ;
     * - échec = au moins une opération refusée par le serveur depuis 7 jours ;
     * - à surveiller = aucun contact depuis plus de 3 jours.
     *
     * @return array<string, mixed>
     */
    public function dashboard(User $actor, Mission $mission): array
    {
        $overview = $this->overview($actor, $mission);
        $facilities = $overview['facilities'];
        $sites = Site::whereIn('health_facility_id', $facilities->pluck('id'))->pluck('health_facility_id', 'id');
        $accountsByFacility = $overview['facility_accounts']->groupBy(fn (User $user) => $sites[$user->roles->first()?->pivot?->scope_id] ?? null);
        $userIds = $overview['facility_accounts']->pluck('id');
        $lastTokenUse = DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $userIds)
            ->groupBy('tokenable_id')->selectRaw('tokenable_id, max(last_used_at) as last_used_at')->pluck('last_used_at', 'tokenable_id');
        $failures = \App\Models\ApiIdempotencyKey::whereIn('user_id', $userIds)->where('response_status', '>=', 400)
            ->where('created_at', '>=', now()->subDays(self::SYNC_FAILURE_DAYS))
            ->groupBy('user_id')->selectRaw('user_id, count(*) as total')->pluck('total', 'user_id');

        $rows = $facilities->whereIn('validation_status', [HealthFacility::STATUS_VALIDATED, HealthFacility::STATUS_SUSPENDED])->map(function (HealthFacility $facility) use ($accountsByFacility, $lastTokenUse, $failures) {
            $accounts = $accountsByFacility[$facility->id] ?? collect();
            $lastContact = $accounts->flatMap(fn (User $user) => [$user->last_login_at, isset($lastTokenUse[$user->id]) ? \Illuminate\Support\Carbon::parse($lastTokenUse[$user->id]) : null])
                ->filter()->max();
            $failed = (int) $accounts->sum(fn (User $user) => $failures[$user->id] ?? 0);
            $status = match (true) {
                $facility->validation_status === HealthFacility::STATUS_SUSPENDED => 'suspended',
                $failed > 0 => 'failed',
                $lastContact === null => 'never',
                $lastContact->lt(now()->subDays(self::SYNC_LATE_DAYS)) => 'late',
                default => 'ok',
            };

            return [
                'id' => $facility->id, 'code' => $facility->code, 'name' => $facility->name,
                'category' => $facility->facilityCategory?->name,
                'projects' => $facility->projects->pluck('code')->values(),
                'project_ids' => $facility->projects->pluck('id')->values(),
                'last_contact_at' => $lastContact?->toIso8601String(),
                'last_contact_days' => $lastContact ? (int) $lastContact->diffInDays(now()) : null,
                'failed_operations' => $failed,
                'sync_status' => $status,
                'sync_label' => [
                    'ok' => 'À jour', 'late' => 'À surveiller', 'failed' => 'Échec de synchro',
                    'never' => 'Jamais synchronisée', 'suspended' => 'Suspendue',
                ][$status],
            ];
        })->values();

        $projects = $overview['projects']->map(function (Project $project) use ($facilities, $rows) {
            $projectFacilities = $facilities->filter(fn ($facility) => $facility->projects->contains('id', $project->id));
            $projectRows = $rows->filter(fn ($row) => $row['project_ids']->contains($project->id));
            $failed = $projectRows->where('sync_status', 'failed')->count();
            $late = $projectRows->whereIn('sync_status', ['late', 'never'])->count();

            return [
                'id' => $project->id, 'code' => $project->code, 'name' => $project->name,
                'validated' => $projectFacilities->where('validation_status', HealthFacility::STATUS_VALIDATED)->count(),
                'total' => $projectFacilities->whereIn('validation_status', [HealthFacility::STATUS_VALIDATED, HealthFacility::STATUS_PENDING, HealthFacility::STATUS_SUSPENDED])->count(),
                'sync_status' => $projectRows->isEmpty() ? 'none' : ($failed > 0 ? 'failed' : ($late > 0 ? 'late' : 'ok')),
                'sync_label' => $projectRows->isEmpty() ? 'Aucune FOSA'
                    : ($failed > 0 ? $failed.' FOSA en échec' : ($late > 0 ? $late.' FOSA à surveiller' : 'À jour')),
            ];
        })->values();

        $pending = $facilities->where('validation_status', HealthFacility::STATUS_PENDING);
        $todo = collect();
        if ($pending->isNotEmpty()) {
            $todo->push(['tone' => 'info', 'action' => 'validate',
                'title' => $pending->count().' '.($pending->count() > 1 ? 'FOSA attendent' : 'FOSA attend').' votre validation',
                'detail' => 'Projets '.$pending->flatMap->projects->pluck('code')->unique()->join(' et ')]);
        }
        foreach ($rows->where('sync_status', 'failed') as $row) {
            $todo->push(['tone' => 'danger', 'action' => 'facility', 'facility_id' => $row['id'],
                'title' => $row['name'].' : '.$row['failed_operations'].' '.($row['failed_operations'] > 1 ? 'opérations refusées' : 'opération refusée').' par le serveur',
                'detail' => 'Projet '.$row['projects']->join(', ').', '.self::SYNC_FAILURE_DAYS.' derniers jours']);
        }
        foreach ($rows->where('sync_status', 'late') as $row) {
            $todo->push(['tone' => 'danger', 'action' => 'facility', 'facility_id' => $row['id'],
                'title' => $row['name'].' ne s’est pas synchronisée depuis '.$row['last_contact_days'].' jours',
                'detail' => 'Projet '.$row['projects']->join(', ')]);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'stats' => [
                'validated' => $facilities->where('validation_status', HealthFacility::STATUS_VALIDATED)->count(),
                'total' => $facilities->whereIn('validation_status', [HealthFacility::STATUS_VALIDATED, HealthFacility::STATUS_PENDING, HealthFacility::STATUS_SUSPENDED])->count(),
                'pending' => $pending->count(),
                'sync_failed' => $rows->where('sync_status', 'failed')->count(),
                'sync_late' => $rows->whereIn('sync_status', ['late', 'never'])->count(),
            ],
            'projects' => $projects,
            'facilities' => $rows->map(fn ($row) => collect($row)->except('project_ids')->all())->values(),
            'todo' => $todo->values(),
            // Indicateurs prévus au niveau 9 (analyses de base) : jamais de chiffre inventé.
            'analyses_available' => false,
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
