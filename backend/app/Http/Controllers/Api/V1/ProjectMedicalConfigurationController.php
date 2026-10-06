<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProjectMedicalConfigurationRequest;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\CareLevelHierarchyService;
use App\Services\ProjectMedicalConfigurationService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** AM-112 — Configuration médicale d'un projet, même chemin que le Web. */
class ProjectMedicalConfigurationController extends Controller
{
    public function __construct(
        private UserScopeService $scopes,
        private ProjectMedicalConfigurationService $configuration,
        private AuditService $audit,
    ) {}

    public function show(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);
        $canManage = $request->user()->can('configure', $project);

        return response()->json([
            'configuration' => $this->configuration->get($project),
            'options' => $canManage ? $this->configuration->options($project) : null,
            'levels' => CareLevelHierarchyService::DEPTH_LABELS,
            'can_manage' => $canManage,
        ]);
    }

    public function update(SaveProjectMedicalConfigurationRequest $request, Project $project): JsonResponse
    {
        $old = $this->configuration->get($project);
        $new = $this->configuration->save($project, $request->validated());
        $this->audit->record($request, 'project.medical_configuration.updated', $project, [
            'care_levels' => collect($old['care_levels'])->pluck('id')->all(),
            'target_populations' => collect($old['target_populations'])->pluck('id')->all(),
        ], [
            'care_levels' => collect($new['care_levels'])->pluck('id')->all(),
            'target_populations' => collect($new['target_populations'])->pluck('id')->all(),
        ]);

        return response()->json(['configuration' => $new]);
    }
}
