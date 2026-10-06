<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveHealthFacilityRequest;
use App\Models\HealthFacility;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\HealthFacilityConfigurationService;
use App\Services\HealthFacilityManagementService;
use App\Services\ProjectAdminWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Niveau 5 — Espace Admin Projet sur le téléphone (maquettes mobiles AdminProjet
 * 05 à 08). Mêmes données et mêmes règles que le Web.
 */
class ProjectAdminController extends Controller
{
    public function __construct(
        private ProjectAdminWorkspaceService $workspace,
        private HealthFacilityManagementService $management,
        private HealthFacilityConfigurationService $configuration,
        private AuditService $audit,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $board = $this->workspace->dashboard($request->user());

        return response()->json([
            'project' => $this->project($board['project']),
            'stats' => $board['stats'],
            'sync' => $board['sync'],
            'watch' => $board['watch'],
            'last_sync_at' => $board['last_sync_at'],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function facilities(Request $request): JsonResponse
    {
        $project = $this->workspace->project($request->user());
        $facilities = $this->workspace->facilities($project);
        $accounts = $this->workspace->accountsByFacility($facilities, $this->workspace->accounts($facilities));

        return response()->json([
            'project' => $this->project($project),
            'facilities' => $facilities->map(fn (HealthFacility $facility) => [
                'id' => $facility->id, 'name' => $facility->name, 'code' => $facility->code,
                'category' => $facility->facilityCategory?->name,
                'category_code' => $facility->facilityCategory?->code,
                'care_level' => $facility->careLevel?->name,
                'populations' => ProjectAdminWorkspaceService::populationsShort($facility),
                'accounts_count' => ($accounts[$facility->id] ?? collect())->count(),
                'status' => ProjectAdminWorkspaceService::status($facility),
                'refusal_reason' => $facility->refusal_reason,
            ])->values(),
        ]);
    }

    /** Valeurs proposées pour la fiche FOSA (configuration validée par la Coordination). */
    public function options(Request $request): JsonResponse
    {
        $project = $this->workspace->project($request->user());
        $options = $this->configuration->options($project);

        return response()->json([
            'project' => $this->project($project),
            'care_levels' => $options['care_levels']->map(fn ($level) => $level->only(['id', 'name', 'depth']))->values(),
            'facility_categories' => $options['facility_categories']->map(fn ($category) => $category->only(['id', 'code', 'name']))->values(),
            'target_populations' => collect($options['target_populations'])->map(fn ($item) => collect($item)->only(['id', 'name']))->values(),
            'pathologies' => collect($options['pathologies'])->map(fn ($item) => collect($item)->only(['id', 'name']))->values(),
            'supply_defaults' => $options['supply_defaults'],
            'safety_stock_options' => HealthFacility::SAFETY_STOCK_OPTIONS,
        ]);
    }

    public function show(Request $request, HealthFacility $facility): JsonResponse
    {
        Gate::authorize('update', $facility);

        return response()->json(['facility' => $this->facility($facility)]);
    }

    public function store(SaveHealthFacilityRequest $request): JsonResponse
    {
        $facility = $this->management->declare($request, $request->organization());
        $this->audit->record($request, 'facility.created', $facility, [], $facility->toArray());

        return response()->json(['facility' => $this->facility($facility->fresh())], 201);
    }

    public function update(SaveHealthFacilityRequest $request, HealthFacility $facility): JsonResponse
    {
        $old = $facility->toArray();
        $this->management->update($request, $facility);
        $this->audit->record($request, 'facility.updated', $facility, $old, $facility->fresh()->toArray());

        return response()->json(['facility' => $this->facility($facility->fresh())]);
    }

    public function preview(Request $request): JsonResponse
    {
        return app(\App\Http\Controllers\Web\ProjectAdminController::class)->preview($request);
    }

    public function standardList(Request $request): JsonResponse
    {
        $project = $this->workspace->project($request->user());
        $facilities = $this->workspace->facilities($project)->where('validation_status', '!==', HealthFacility::STATUS_REFUSED)->values();
        $facility = $request->filled('facility') ? $facilities->firstWhere('id', $request->string('facility')->toString()) : null;
        abort_if($request->filled('facility') && ! $facility, 404);
        $list = $this->workspace->standardList($project, $facility);

        return response()->json([
            'title' => $list['title'],
            'facility_id' => $facility?->id,
            'facilities' => $facilities->map(fn (HealthFacility $item) => $item->only(['id', 'name', 'code']))->values(),
            'pathologies' => $list['pathologies'],
            'products' => $list['rows']->map(fn (array $row) => [
                'id' => $row['product']->id,
                'code' => $row['product']->code,
                'name' => trim($row['product']->name.' '.$row['product']->strength),
                'packaging' => $row['product']->packaging ?: collect([$row['product']->dosageForm?->name, $row['product']->baseUnit?->name])->filter()->join(', ') ?: null,
                'pathology' => $row['pathology'],
                'retained' => $row['retained'],
            ])->values(),
        ]);
    }

    private function project(Project $project): array
    {
        return [
            'id' => $project->id, 'code' => $project->code, 'name' => $project->name,
            'organization_id' => $project->organization_id,
            'organization' => $project->implementing_partner ?: $project->organization?->name,
            'country' => $project->mission?->country?->name,
            'mission' => $project->mission?->name,
            'donor' => $project->donors->first()?->name ?? ($project->type === 'national_program' ? Project::NATIONAL_PROGRAM_DEFAULT_DONOR : null),
            'starts_on' => $project->starts_on?->format('Y-m-d'), 'ends_on' => $project->ends_on?->format('Y-m-d'),
            'order_period_months' => $project->order_period_months,
            'delivery_lead_time_months' => $project->delivery_lead_time_months,
            'safety_stock_months' => $project->safety_stock_months,
            'inventory_date' => $project->inventory_date?->format('Y-m-d'),
            'order_submission_date' => $project->order_submission_date?->format('Y-m-d'),
            'order_receipt_date' => $project->order_receipt_date?->format('Y-m-d'),
        ];
    }

    private function facility(HealthFacility $facility): array
    {
        $facility->loadMissing(['targetPopulations:id', 'pathologies:id']);

        return [
            ...$facility->only(['id', 'name', 'code', 'care_level_id', 'facility_category_id', 'is_active', 'validation_status', 'refusal_reason',
                'order_period_months', 'delivery_lead_time_months']),
            'safety_stock_months' => $facility->safety_stock_months,
            'validation_status_label' => $facility->validation_status_label,
            'status' => ProjectAdminWorkspaceService::status($facility),
            'target_population_ids' => $facility->targetPopulations->pluck('id')->values(),
            'pathology_ids' => $facility->pathologies->pluck('id')->values(),
            'inventory_date' => $facility->inventory_date?->format('Y-m-d'),
            'order_submission_date' => $facility->order_submission_date?->format('Y-m-d'),
            'order_receipt_date' => $facility->order_receipt_date?->format('Y-m-d'),
            'standard_list_count' => $this->management->standardList($facility)->count(),
        ];
    }
}
