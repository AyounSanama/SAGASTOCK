<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConfigurationFlowType;
use App\Http\Controllers\Controller;
use App\Models\SetupProgress;
use App\Services\ConfigurationWorkflowService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfigurationWorkflowController extends Controller
{
    private const STEPS = [
        1 => ['key' => 'organization', 'label' => 'Organisation / ONG'],
        2 => ['key' => 'mission', 'label' => 'Mission'],
        3 => ['key' => 'projects', 'label' => 'Projets'],
        4 => ['key' => 'donors', 'label' => 'Bailleurs'],
        5 => ['key' => 'programs', 'label' => 'Programmes'],
        6 => ['key' => 'modules', 'label' => 'Modules'],
        7 => ['key' => 'features', 'label' => 'Fonctionnalités'],
        8 => ['key' => 'standard-lists', 'label' => 'Listes standards'],
        9 => ['key' => 'facilities', 'label' => 'Formations sanitaires'],
        10 => ['key' => 'sites', 'label' => 'Sites de dispensation'],
        11 => ['key' => 'users-access', 'label' => 'Utilisateurs et accès'],
        12 => ['key' => 'summary', 'label' => 'Résumé et validation'],
    ];

    public function __construct(
        private readonly ConfigurationWorkflowService $workflows,
        private readonly UserScopeService $scopes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = SetupProgress::query()
            ->where('created_by', $request->user()->id)
            ->latest('updated_at')
            ->get()
            ->map(fn (SetupProgress $progress) => $this->serialize($progress));

        $initial = $items->firstWhere('flow_type', ConfigurationFlowType::InitialConfiguration->value);

        return response()->json([
            'configuration_complete' => ($initial['status'] ?? null) === 'completed',
            'active_workflow' => $items->firstWhere('status', 'active'),
            'workflows' => $items->values(),
            'flow_types' => collect(ConfigurationFlowType::cases())
                ->reject(fn ($type) => $type === ConfigurationFlowType::NewOrganization
                    && ! $request->user()?->hasPermission('organizations.manage'))
                ->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'start_step' => $type->startStep(),
                ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'flow_type' => ['required', Rule::enum(ConfigurationFlowType::class)],
        ]);
        $type = ConfigurationFlowType::from($data['flow_type']);
        if ($type === ConfigurationFlowType::NewOrganization) {
            abort_unless($request->user()?->hasPermission('organizations.manage'), 403);
        }
        $progress = $this->workflows->start($request, $type);

        return response()->json([
            'message' => $type->label().' démarré avec succès.',
            'workflow' => $this->serialize($progress->fresh()),
        ], 201);
    }

    public function show(Request $request, string $workflow): JsonResponse
    {
        return response()->json([
            'workflow' => $this->serialize($this->owned($request, $workflow)),
        ]);
    }

    public function draft(Request $request, string $workflow): JsonResponse
    {
        $data = $request->validate([
            'step' => ['required', 'integer', 'between:1,12'],
            'data' => ['required', 'array'],
        ]);
        $progress = $this->owned($request, $workflow);
        $this->workflows->saveDraft($progress, (int) $data['step'], $data['data']);

        return response()->json([
            'message' => 'Brouillon enregistré.',
            'workflow' => $this->serialize($progress->fresh()),
        ]);
    }

    private function owned(Request $request, string $workflow): SetupProgress
    {
        return SetupProgress::query()
            ->where('workflow_id', $workflow)
            ->where('created_by', $request->user()->id)
            ->firstOrFail();
    }

    private function serialize(SetupProgress $progress): array
    {
        $states = $this->workflows->states($progress);
        $type = $progress->flow_type instanceof ConfigurationFlowType
            ? $progress->flow_type
            : ConfigurationFlowType::from($progress->flow_type);

        return [
            'id' => $progress->workflow_id,
            'flow_type' => $type->value,
            'label' => $type->label(),
            'start_step' => $progress->start_step,
            'current_step' => $progress->current_step,
            'completed_steps' => array_values($progress->completed_steps ?? []),
            'progress_percent' => (int) round(count($progress->completed_steps ?? []) / 12 * 100),
            'status' => $progress->workflow_status,
            'version' => $progress->version,
            'context' => $progress->context ?? [],
            'drafts' => $progress->drafts ?? [],
            'steps' => collect(self::STEPS)->map(fn ($step, $number) => [
                'number' => $number,
                ...$step,
                'state' => $states[(string) $number] ?? 'not_started',
            ])->values(),
            'updated_at' => $progress->updated_at?->toIso8601String(),
            'completed_at' => $progress->completed_at?->toIso8601String(),
        ];
    }
}
