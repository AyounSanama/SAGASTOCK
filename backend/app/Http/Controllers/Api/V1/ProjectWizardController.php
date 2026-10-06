<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\ProjectWizardController as WebProjectWizardController;
use App\Http\Requests\SaveProjectWizardIdentityRequest;
use App\Http\Requests\SaveProjectWizardStandardListRequest;
use App\Http\Requests\SaveProjectWizardSupplyRequest;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\GovernanceService;
use App\Services\ProjectWizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Niveau 2 — Assistant « Créer un projet / programme » (mobile). Mêmes règles
 * que le Web (ProjectWizardService et requêtes partagées) ; en ligne seulement.
 */
class ProjectWizardController extends Controller
{
    public function __construct(
        private ProjectWizardService $wizard,
        private AuditService $audit,
    ) {}

    /** Missions, bailleurs et valeurs des listes déroulantes des étapes 1, 2 et 4. */
    public function options(Request $request): JsonResponse
    {
        abort_unless(app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN, 403);
        $missions = $this->wizard->missions($request->user());
        abort_if($missions->isEmpty(), 403, 'Aucune coordination n’est rattachée à votre compte.');

        return response()->json([
            'missions' => $missions->map(fn (Mission $mission) => [
                'id' => $mission->id,
                'name' => $mission->name,
                'country' => $mission->country?->name,
                'organization_id' => $mission->organization_id,
                'organization_name' => $mission->organization?->name,
            ])->values(),
            'donors' => $this->wizard->donors($missions),
            'types' => Project::TYPES,
            'national_program_default_donor' => Project::NATIONAL_PROGRAM_DEFAULT_DONOR,
            'safety_stock_options' => HealthFacility::SAFETY_STOCK_OPTIONS,
            'can_add_donor' => $request->user()->hasPermission('funding.manage'),
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('configure', $project);

        return response()->json($this->payload($project));
    }

    public function store(SaveProjectWizardIdentityRequest $request): JsonResponse
    {
        $project = $this->wizard->saveIdentity(null, $request->validated(), $request->user()->id);
        $this->audit->record($request, 'project.created', $project, [], $project->only(['organization_id', 'mission_id', 'code', 'name', 'type', 'status']));

        return response()->json($this->payload($project), 201);
    }

    public function updateIdentity(SaveProjectWizardIdentityRequest $request, Project $project): JsonResponse
    {
        $old = $project->only(['mission_id', 'code', 'name', 'type', 'implementing_partner']);
        $project = $this->wizard->saveIdentity($project, $request->validated(), $request->user()->id);
        $this->audit->record($request, 'project.updated', $project, $old, $project->only(array_keys($old)));

        return response()->json($this->payload($project));
    }

    /** Étape 3 : choix et liste ; avec refresh=1, la liste suit les choix envoyés sans rien enregistrer. */
    public function standardList(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('configure', $project);
        $input = $request->boolean('refresh') ? $request->only(['care_level_ids', 'target_population_ids', 'pathology_ids', 'listed', 'retained', 'added']) : null;
        $state = $this->wizard->standardListState($project, $input);

        return response()->json([
            'options' => $this->wizard->standardListOptions($project),
            'care_level_ids' => $state['care_level_ids'],
            'target_population_ids' => $state['target_population_ids'],
            'pathology_ids' => $state['pathology_ids'],
            'total' => $state['total'],
            'retained_count' => $state['retained_count'],
            'products' => $state['rows']->map(fn (array $row) => [
                'id' => $row['product']->id,
                'code' => $row['product']->code,
                'name' => trim($row['product']->name.' '.$row['product']->strength),
                'packaging' => $row['product']->packaging ?: collect([$row['product']->dosageForm?->name, $row['product']->baseUnit?->name])->filter()->join(', ') ?: null,
                'pathology' => $row['pathology'],
                'retained' => $row['retained'],
                'added' => $row['added'],
            ])->values(),
            'addable' => $this->wizard->addableProducts($project, $state['rows']->pluck('product.id'))
                ->map(fn ($product) => ['id' => $product->id, 'code' => $product->code, 'name' => trim($product->name.' '.$product->strength)])->values(),
        ]);
    }

    public function updateStandardList(SaveProjectWizardStandardListRequest $request, Project $project): JsonResponse
    {
        $result = $this->wizard->saveStandardList($project, $request->validated(), $request->isDraft());
        if ($result['saved']) {
            $this->audit->record($request, 'project.standard_list.configured', $project, [], ['retained_products' => $result['retained']]);
        }

        return response()->json($this->payload($project));
    }

    public function updateSupply(SaveProjectWizardSupplyRequest $request, Project $project): JsonResponse
    {
        $old = $project->only(['status', 'order_period_months', 'delivery_lead_time_months', 'safety_stock_months']);
        $project = $this->wizard->saveSupply($project, $request->validated(), ! $request->isDraft(), $request->user()->id);
        $this->audit->record($request, $request->isDraft() ? 'project.updated' : 'project.activated', $project, $old, $project->only(array_keys($old)));

        return response()->json($this->payload($project));
    }

    public function storeDonor(Request $request): JsonResponse
    {
        $mission = Mission::whereIn('id', $this->wizard->missions($request->user())->pluck('id'))
            ->findOrFail($request->input('mission_id'));
        $data = $request->validate(...WebProjectWizardController::donorRules($mission));
        $donor = $this->wizard->createDonor($mission, $data);
        $this->audit->record($request, 'donor.created', $donor, [], $donor->only(['organization_id', 'code', 'name']));

        return response()->json($donor->only(['id', 'organization_id', 'code', 'name']), 201);
    }

    private function payload(Project $project): array
    {
        $project->loadMissing('donors:id');

        return [
            'project' => [
                ...$project->only(['id', 'mission_id', 'type', 'implementing_partner', 'code', 'name', 'status', 'status_label', 'order_period_months', 'delivery_lead_time_months', 'safety_stock_months']),
                'donor_id' => $project->donors->first()?->id,
                ...collect(['starts_on', 'ends_on', 'inventory_date', 'order_submission_date', 'order_receipt_date'])
                    ->mapWithKeys(fn (string $field) => [$field => $project->{$field}?->format('Y-m-d')])->all(),
            ],
            'summary' => $this->wizard->summary($project),
            'resume_step' => $this->wizard->resumeStep($project),
        ];
    }
}
