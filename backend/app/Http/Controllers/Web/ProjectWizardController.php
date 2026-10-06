<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProjectWizardIdentityRequest;
use App\Http\Requests\SaveProjectWizardStandardListRequest;
use App\Http\Requests\SaveProjectWizardSupplyRequest;
use App\Models\Mission;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\GovernanceService;
use App\Services\ProjectWizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Niveau 2 — Assistant « Créer un projet / programme » en 4 étapes (Coordination, Web). */
class ProjectWizardController extends Controller
{
    public function __construct(
        private ProjectWizardService $wizard,
        private AuditService $audit,
    ) {}

    /** Étapes 1-2 d'un nouveau projet. */
    public function create(Request $request): View
    {
        abort_unless(app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN, 403);
        $missions = $this->wizard->missions($request->user());
        abort_if($missions->isEmpty(), 403, 'Aucune coordination n’est rattachée à votre compte.');

        return $this->identityView($request, null, $missions);
    }

    public function store(SaveProjectWizardIdentityRequest $request): RedirectResponse
    {
        $project = $this->wizard->saveIdentity(null, $request->validated(), $request->user()->id);
        $this->audit->record($request, 'project.created', $project, [], $project->only(['organization_id', 'mission_id', 'code', 'name', 'type', 'status']));

        return $this->next($request, $project, 'standard-list');
    }

    public function show(Request $request, Project $project, string $step): View
    {
        Gate::authorize('configure', $project);

        return match ($step) {
            'identity' => $this->identityView($request, $project, $this->wizard->missions($request->user())),
            'standard-list' => $this->standardListView($request, $project),
            'supply' => view('projects.wizard.supply', ['project' => $project, 'summary' => $this->wizard->summary($project)]),
        };
    }

    public function updateIdentity(SaveProjectWizardIdentityRequest $request, Project $project): RedirectResponse
    {
        $old = $project->only(['mission_id', 'code', 'name', 'type', 'implementing_partner']);
        $project = $this->wizard->saveIdentity($project, $request->validated(), $request->user()->id);
        $this->audit->record($request, 'project.updated', $project, $old, $project->only(array_keys($old)));

        return $this->next($request, $project, 'standard-list');
    }

    public function updateStandardList(SaveProjectWizardStandardListRequest $request, Project $project): RedirectResponse
    {
        $result = $this->wizard->saveStandardList($project, $request->validated(), $request->isDraft());
        if ($result['saved']) {
            $this->audit->record($request, 'project.standard_list.configured', $project, [], ['retained_products' => $result['retained']]);
        }

        return $this->next($request, $project, 'supply');
    }

    public function updateSupply(SaveProjectWizardSupplyRequest $request, Project $project): RedirectResponse
    {
        $wasActive = $project->status === 'active';
        $old = $project->only(['status', 'order_period_months', 'delivery_lead_time_months', 'safety_stock_months']);
        $project = $this->wizard->saveSupply($project, $request->validated(), ! $request->isDraft(), $request->user()->id);
        $this->audit->record($request, $request->isDraft() ? 'project.updated' : 'project.activated', $project, $old, $project->only(array_keys($old)));

        return redirect()->route('modules.missions')->with('status', match (true) {
            $request->isDraft() => 'Brouillon du projet '.$project->code.' enregistré.',
            $wasActive => 'Projet '.$project->code.' mis à jour.',
            default => 'Projet '.$project->code.' créé : il est « Actif ». Créez maintenant le compte de son Admin Projet dans « Comptes de la coordination ».',
        });
    }

    /** DEC-08 — « Appliquer à toutes les FOSA » : action explicite et confirmée, jamais automatique. */
    public function applySupplyToFacilities(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('configure', $project);
        $request->validate(['confirm' => ['required', 'accepted']], ['confirm.accepted' => 'Confirmez l’application des paramètres du projet à toutes ses FOSA.']);
        $updated = app(\App\Services\HealthFacilityConfigurationService::class)->applyProjectSupplyToFacilities($project, $request->user());
        $this->audit->record($request, 'project.supply_applied_to_facilities', $project, [], ['facilities_updated' => $updated]);

        return back()->with('status', $updated > 0
            ? "Paramètres du projet appliqués à {$updated} FOSA (historisés)."
            : 'Toutes les FOSA ont déjà les paramètres du projet.');
    }

    /** « + Ajouter un bailleur » : bailleur ajouté au référentiel et sélectionné dans l'étape 2. */
    public function storeDonor(Request $request): JsonResponse
    {
        $mission = Mission::whereIn('id', $this->wizard->missions($request->user())->pluck('id'))
            ->findOrFail($request->input('mission_id'));
        $data = $request->validate(...self::donorRules($mission));
        $donor = $this->wizard->createDonor($mission, $data);
        $this->audit->record($request, 'donor.created', $donor, [], $donor->only(['organization_id', 'code', 'name']));

        return response()->json($donor->only(['id', 'organization_id', 'code', 'name']), 201);
    }

    /** Règles communes Web / API de « + Ajouter un bailleur ». */
    public static function donorRules(Mission $mission): array
    {
        return [[
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('donors')->where('organization_id', $mission->organization_id)],
        ], [
            'code.unique' => 'Ce sigle est déjà utilisé par un autre bailleur.',
            'code.alpha_dash' => 'Le sigle ne contient que des lettres, chiffres, tirets et tirets bas.',
        ], ['name' => 'nom du bailleur', 'code' => 'sigle']];
    }

    private function identityView(Request $request, ?Project $project, $missions): View
    {
        return view('projects.wizard.identity', [
            'project' => $project?->load('donors:id'),
            'missions' => $missions,
            'mission' => $project?->mission ?? $missions->first(),
            'donors' => $this->wizard->donors($missions),
            'canAddDonor' => $request->user()->hasPermission('funding.manage'),
            'summary' => $this->wizard->summary($project),
            'step' => old('_step', $request->query('step') === 'donor' ? 'donor' : 'identity'),
        ]);
    }

    private function standardListView(Request $request, Project $project): View
    {
        $input = $request->boolean('refresh') ? $request->only(['care_level_ids', 'target_population_ids', 'pathology_ids', 'listed', 'retained', 'added']) : null;
        $state = $this->wizard->standardListState($project, $input);
        $options = $this->wizard->standardListOptions($project);

        return view('projects.wizard.standard-list', [
            'project' => $project,
            'state' => $state,
            'careLevels' => $options['care_levels'],
            'populations' => $options['target_populations'],
            'pathologies' => $options['pathologies'],
            'addable' => $this->wizard->addableProducts($project, $state['rows']->pluck('product.id')),
            'canAddService' => $request->user()->hasPermission('standard_lists.manage'),
            'summary' => $this->wizard->summary($project),
        ]);
    }

    /** Enchaîne sur l'étape suivante, ou revient à « Ma Coordination » pour un brouillon. */
    private function next(Request $request, Project $project, string $step): RedirectResponse
    {
        if ($request->input('intent') === 'draft') {
            return redirect()->route('modules.missions')->with('status', 'Brouillon du projet '.$project->code.' enregistré.');
        }

        return redirect()->route('projects.wizard.show', [$project, $step]);
    }
}
