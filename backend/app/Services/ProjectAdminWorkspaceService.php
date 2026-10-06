<?php

namespace App\Services;

use App\Models\HealthFacility;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Niveau 5 — Espace de l'Admin Projet (maquettes AdminProjet 01 à 08) :
 * tableau de bord, « Projet & FOSA », fiche FOSA, Liste Standard en
 * consultation. Données communes au Web et à l'API (mobile).
 */
class ProjectAdminWorkspaceService
{
    public const POPULATION_SHORT = ['Femmes enceintes' => 'FE', 'Enfants < 5 ans' => '< 5 ans'];

    public function __construct(
        private UserScopeService $scopes,
        private CoordinationService $coordination,
        private StandardListGenerationService $generator,
        private HealthFacilityManagementService $facilities,
        private ProjectWizardService $wizard,
    ) {}

    /** Projet unique de l'Admin Projet (404 sinon). */
    public function project(User $user): Project
    {
        $ids = $this->scopes->directProjectIds($user)->unique()->values();
        abort_unless($ids->count() === 1, 404, 'Le compte Admin Projet doit être rattaché à un projet unique.');

        return Project::with(['organization:id,name', 'mission.country:id,name', 'donors:id,code,name'])->findOrFail($ids->first());
    }

    /** FOSA du projet (toutes : en attente, validées, refusées, suspendues). */
    public function facilities(Project $project): Collection
    {
        return HealthFacility::whereHas('projects', fn ($query) => $query->whereKey($project->id))
            ->with(['facilityCategory:id,name,code', 'careLevel:id,name', 'targetPopulations:id,name', 'pathologies:id,name', 'projects:id,code'])
            ->orderBy('name')->get();
    }

    /** Comptes Admin Site et Utilisateur Site des FOSA données, rôles chargés. */
    public function accounts(Collection $facilities): Collection
    {
        return $this->coordination->facilityAccounts($facilities->pluck('id'))->with('roles')->get();
    }

    /** Comptes par FOSA (identifiant de FOSA => comptes). */
    public function accountsByFacility(Collection $facilities, Collection $accounts): Collection
    {
        $sites = \App\Models\Site::whereIn('health_facility_id', $facilities->pluck('id'))->pluck('health_facility_id', 'id');

        return $accounts->groupBy(fn (User $user) => $sites[$user->roles->first()?->pivot?->scope_id] ?? null);
    }

    /** Statut affiché d'une FOSA (maquettes : Active, Inactive, En attente…). */
    public static function status(HealthFacility $facility): array
    {
        return match ($facility->validation_status) {
            HealthFacility::STATUS_PENDING => ['key' => 'pending', 'label' => 'En attente', 'tone' => 'info'],
            HealthFacility::STATUS_REFUSED => ['key' => 'refused', 'label' => 'Refusée', 'tone' => 'danger'],
            HealthFacility::STATUS_SUSPENDED => ['key' => 'suspended', 'label' => 'Suspendue', 'tone' => 'danger'],
            default => $facility->is_active
                ? ['key' => 'active', 'label' => 'Active', 'tone' => 'success']
                : ['key' => 'inactive', 'label' => 'Inactive', 'tone' => 'neutral'],
        };
    }

    /** « Adultes, FE, < 5 ans » (colonne Population cible). */
    public static function populationsShort(HealthFacility $facility): string
    {
        return $facility->targetPopulations->pluck('name')
            ->map(fn (string $name) => self::POPULATION_SHORT[$name] ?? $name)->join(', ') ?: '—';
    }

    /** Tableau de bord (maquette AdminProjet 01, mobile 05). */
    public function dashboard(User $user): array
    {
        $project = $this->project($user);
        $facilities = $this->facilities($project);
        $accounts = $this->accounts($facilities);
        $rows = $this->coordination->syncRows($facilities, $accounts);
        $active = $facilities->filter(fn (HealthFacility $facility) => self::status($facility)['key'] === 'active')->count();
        $toActivate = $accounts->filter(fn (User $account) => CoordinationService::accountStatus($account)['label'] === 'À activer')->count();
        $watch = $rows->whereIn('sync_status', ['late', 'never', 'failed']);

        return [
            'project' => $project,
            'stats' => [
                'active_facilities' => $active,
                'facilities' => $facilities->count(),
                'inactive_facilities' => $facilities->count() - $active,
                'accounts' => $accounts->count(),
                'accounts_to_activate' => $toActivate,
                'sync_failed' => $rows->where('sync_status', 'failed')->count(),
                'standard_list_products' => count($this->generator->publishedProductIds($project) ?? []),
            ],
            'sync' => $rows->sortBy('code')->values(),
            'watch' => $watch->count(),
            'last_sync_at' => $rows->pluck('last_contact_at')->filter()->max(),
        ];
    }

    /**
     * Liste Standard en consultation (maquette AdminProjet 04) : produits de
     * la liste du projet, « Retenu » pour le projet ou, si une FOSA est
     * choisie, pour cette FOSA.
     *
     * @return array{rows: Collection, pathologies: Collection, facility: ?HealthFacility, title: string}
     */
    public function standardList(Project $project, ?HealthFacility $facility = null): array
    {
        // Seule la version validée par la Coordination compte (jamais un brouillon en cours).
        $published = collect($this->generator->publishedProductIds($project) ?? []);
        $rows = $published->isEmpty() ? collect() : $this->wizard->standardListState($project)['rows']
            ->map(fn (array $row) => [...$row, 'retained' => $published->contains($row['product']->id)]);
        $missing = $published->diff($rows->pluck('product.id'));
        if ($missing->isNotEmpty()) {
            $rows = $rows->concat(\App\Models\Product::whereIn('id', $missing)->with(['baseUnit:id,name', 'dosageForm:id,name'])->get()
                ->map(fn ($product) => ['product' => $product, 'retained' => true, 'added' => true, 'pathology' => null]));
        }
        $rows = $rows->values();
        if ($facility) {
            $facilityIds = $this->facilities->standardList($facility)->pluck('id');
            $rows = $rows->filter(fn (array $row) => $row['retained'])
                ->map(fn (array $row) => [...$row, 'retained' => $facilityIds->contains($row['product']->id)])->values();
        }

        return [
            'rows' => $rows,
            'pathologies' => $rows->pluck('pathology')->filter()->unique()->sort()->values(),
            'facility' => $facility,
            'title' => collect([$project->organization?->name, trim(($project->donors->first()?->name ?? '').' '.$project->code)])->filter()->join(' · '),
        ];
    }
}
