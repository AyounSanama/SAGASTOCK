<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSyncState;
use App\Models\Mission;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Synchronisation des téléphones : chaque appareil déclare son état après une
 * synchronisation ; la supervision (Coordination, Admin Projet, Site) le lit
 * dans son périmètre, en lecture seule. Seul l'utilisateur du téléphone peut
 * réessayer ou abandonner un envoi.
 */
class SyncSupervisionService
{
    /** Au-delà, l'appareil est « En retard ». */
    public const LATE_HOURS = 24;

    public const MODULES = [
        'receipts' => 'Réceptions',
        'clinical' => 'Dispensations',
        'inventories' => 'Inventaires',
        'orders' => 'Commandes',
        'stocks' => 'Mouvements de stock',
    ];

    public function __construct(private readonly UserScopeService $scopes) {}

    /** Enregistre l'état déclaré par le téléphone de l'utilisateur. */
    public function report(User $user, array $data): DeviceSyncState
    {
        $device = Device::where('fingerprint', $data['device_id'])
            ->where('user_id', $user->id)->whereNull('revoked_at')->firstOrFail();
        $pending = collect($data['pending'] ?? [])
            ->only(array_keys(self::MODULES))->map(fn ($count) => max(0, (int) $count));
        $issues = collect($data['issues'] ?? [])->map(fn (array $issue) => [
            'id' => $issue['id'],
            'kind' => $issue['kind'],
            'module' => $issue['module'],
            'reference' => $issue['reference'] ?? null,
            'reason' => $issue['reason'] ?? null,
            'occurred_at' => $issue['occurred_at'] ?? null,
            'reported' => (bool) ($issue['reported'] ?? false),
        ])->values();

        return DeviceSyncState::updateOrCreate(['device_id' => $device->id, 'user_id' => $user->id], [
            'site_id' => $this->scopes->directSiteIds($user)->first(),
            'last_success_at' => $data['last_success_at'] ?? null,
            'reported_at' => now(),
            'pending_total' => $pending->sum(),
            'pending_by_module' => $pending->all(),
            'issues' => $issues->all(),
        ]);
    }

    /**
     * Tableau de supervision : un appareil par ligne (comptes Site du
     * périmètre), indicateurs, filtres et détail de l'appareil choisi.
     */
    public function overview(User $actor, array $filters = []): array
    {
        $projectId = $filters['project_id'] ?? null;
        $projects = $this->scopes->projects($actor)->orderBy('name')->get(['projects.id', 'projects.name', 'projects.code']);
        if ($projectId && ! $projects->contains('id', $projectId)) {
            $projectId = null;
        }

        $sites = Site::with('healthFacility.projects')
            ->whereIn('id', $this->scopes->siteIds($actor))
            ->when($projectId, fn ($query) => $query->whereHas(
                'healthFacility.projects', fn ($q) => $q->where('projects.id', $projectId)
            ))->get()->keyBy('id');
        // États déclarés par les téléphones des FOSA du périmètre (un par
        // appareil et par compte : un téléphone partagé garde chaque historique).
        $states = DeviceSyncState::with(['device', 'user'])->whereIn('site_id', $sites->keys())->get()
            ->filter(fn (DeviceSyncState $state) => $state->device && $state->device->revoked_at === null);
        // Téléphones des comptes Site qui n'ont encore rien déclaré.
        $siteOfUser = DB::table('role_user')->where('scope_type', 'site')
            ->whereIn('scope_id', $sites->keys())->pluck('scope_id', 'user_id');
        $silent = Device::with('user')->whereIn('user_id', $siteOfUser->keys())->whereNull('revoked_at')->get()
            ->reject(fn (Device $device) => $states->contains(
                fn (DeviceSyncState $state) => $state->device_id === $device->id && $state->user_id === $device->user_id
            ));

        $rows = $states->toBase()->map(fn (DeviceSyncState $state) => $this->row($state->device, $state->user, $sites[$state->site_id] ?? null, $state))
            ->merge($silent->map(fn (Device $device) => $this->row($device, $device->user, $sites[$siteOfUser[$device->user_id]] ?? null, null)))
            ->filter(fn (array $row) => $row['site_id'] !== null)
            ->sortBy([['status_rank', 'asc'], ['facility', 'asc']])->values();

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $filter = in_array($filters['filter'] ?? 'all', ['all', 'late', 'refused', 'conflicts'], true) ? ($filters['filter'] ?? 'all') : 'all';
        $visible = $rows->filter(fn (array $row) => match ($filter) {
            'late' => $row['status'] === 'late',
            'refused' => $row['refused'] > 0,
            'conflicts' => $row['conflicts'] > 0,
            default => true,
        })->filter(fn (array $row) => $search === ''
            || str_contains(mb_strtolower($row['facility'].' '.$row['user']), $search))->values();

        $selected = $rows->firstWhere('key', $filters['device'] ?? null);

        return [
            'context' => $this->context($actor),
            'stats' => [
                'devices' => $rows->count(),
                'up_to_date' => $rows->where('late', false)->count(),
                'late' => $rows->where('late', true)->count(),
                'refused' => $rows->sum('refused'),
                'conflicts' => $rows->sum('conflicts'),
            ],
            'rows' => $visible->map(fn (array $row) => collect($row)->except(['issues', 'status_rank'])->all())->all(),
            'selected' => $selected,
            'projects' => $projects->map(fn ($project) => ['id' => $project->id, 'name' => $project->name])->values()->all(),
            'filters' => ['project_id' => $projectId, 'filter' => $filter, 'search' => $filters['search'] ?? ''],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** Périmètre affiché au-dessus du titre (Web et mobile) : coordination, projet ou FOSA. */
    private function context(User $user): ?string
    {
        return match (app(GovernanceService::class)->roleCode($user)) {
            GovernanceService::COORDINATION_ADMIN => Mission::whereIn('id', $this->scopes->coordinationMissionIds($user))->value('name'),
            GovernanceService::PROJECT_ADMIN => Project::whereIn('id', $this->scopes->directProjectIds($user))->value('name'),
            GovernanceService::SITE_ADMIN, GovernanceService::SITE_USER => Site::with('healthFacility')
                ->whereIn('id', $this->scopes->directSiteIds($user))->first()?->healthFacility?->name,
            default => null,
        };
    }

    private function row(Device $device, ?User $user, ?Site $site, ?DeviceSyncState $state): array
    {
        $issues = collect($state?->issues ?? []);
        $lastSuccess = $state?->last_success_at;
        $late = $lastSuccess === null || $lastSuccess->lt(now()->subHours(self::LATE_HOURS));
        $refused = $issues->where('kind', 'refused')->count();
        $conflicts = $issues->where('kind', 'conflict')->count();
        $status = match (true) {
            $late => 'late',
            $refused + $conflicts > 0 => 'check',
            default => 'ok',
        };

        return [
            // Clé de ligne : appareil et compte (téléphone partagé).
            'key' => $device->id.'.'.$user?->id,
            'device_id' => $device->id,
            'site_id' => $site?->id,
            'facility' => $site?->healthFacility?->name ?? $site?->name ?? '—',
            'site' => $site?->name,
            'projects' => $site?->healthFacility?->projects->pluck('name')->values()->all() ?? [],
            'device' => 'Téléphone',
            'user' => $user?->name ?? '—',
            'last_success_at' => $lastSuccess?->toIso8601String(),
            'last_success_label' => $this->ago($lastSuccess),
            'reported_at' => $state?->reported_at?->toIso8601String(),
            'pending' => (int) ($state?->pending_total ?? 0),
            'pending_by_module' => collect($state?->pending_by_module ?? [])
                ->mapWithKeys(fn ($count, $module) => [self::MODULES[$module] ?? $module => $count])->all(),
            'refused' => $refused,
            'conflicts' => $conflicts,
            'late' => $late,
            'status' => $status,
            'status_label' => ['late' => 'En retard', 'check' => 'À vérifier', 'ok' => 'À jour'][$status],
            'status_rank' => ['late' => 0, 'check' => 1, 'ok' => 2][$status],
            'issues' => $issues->sortByDesc('occurred_at')->map(fn (array $issue) => [
                ...$issue,
                'module_label' => self::MODULES[$issue['module']] ?? $issue['module'],
                'kind_label' => $issue['kind'] === 'conflict' ? 'Conflit' : 'Refusé',
                'occurred_label' => $this->ago(isset($issue['occurred_at']) ? Carbon::parse($issue['occurred_at']) : null, false),
            ])->values()->all(),
        ];
    }

    /** « Auj. 09:42 », « Hier 16:12 », « Il y a 30 h », « Il y a 5 jours ». */
    private function ago(?Carbon $at, bool $relative = true): string
    {
        if ($at === null) {
            return 'Jamais';
        }
        $at = $at->copy()->setTimezone(config('app.timezone'));
        if ($at->isToday()) {
            return 'Auj. '.$at->format('H:i');
        }
        if (! $relative || $at->isYesterday() && $at->gt(now()->subHours(self::LATE_HOURS))) {
            return ($at->isYesterday() ? 'Hier ' : $at->format('d/m ')).$at->format('H:i');
        }
        $hours = (int) $at->diffInHours(now());

        return $hours < 48 ? "Il y a {$hours} h" : 'Il y a '.(int) $at->diffInDays(now()).' jours';
    }
}
