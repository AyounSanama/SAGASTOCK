<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProjectMedicalConfigurationRequest;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\CareLevelHierarchyService;
use App\Services\GovernanceService;
use App\Services\ProjectMedicalConfigurationService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** AM-112 — Configuration médicale d'un projet (écriture Coordination, lecture périmètre projet). */
class ProjectMedicalConfigurationController extends Controller
{
    public function __construct(
        private UserScopeService $scopes,
        private ProjectMedicalConfigurationService $configuration,
        private AuditService $audit,
    ) {}

    public function show(Request $request, Project $project): View
    {
        abort_unless($this->scopes->projects($request->user())->whereKey($project->id)->exists(), 404);
        $project->load('organization:id,name', 'mission.country:id,name');

        return view('projects.medical-configuration', [
            'project' => $project,
            'configuration' => $this->configuration->get($project),
            'options' => $this->configuration->options($project),
            'levels' => CareLevelHierarchyService::DEPTH_LABELS,
            'canManage' => app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN
                && $request->user()->hasPermission('standard_lists.manage'),
        ]);
    }

    public function update(SaveProjectMedicalConfigurationRequest $request, Project $project): RedirectResponse
    {
        $old = $this->configuration->get($project);
        $new = $this->configuration->save($project, $request->validated());
        $this->audit->record($request, 'project.medical_configuration.updated', $project, $this->summary($old), $this->summary($new));

        return back()->with('status', 'Configuration médicale enregistrée.');
    }

    private function summary(array $configuration): array
    {
        return [
            'care_level_ids' => collect($configuration['care_levels'])->pluck('id')->all(),
            'target_population_ids' => collect($configuration['target_populations'])->pluck('id')->all(),
            'pathologies' => collect($configuration['pathologies'])->map(fn ($row) => [$row['id'] => $row['target_population_ids']])->all(),
        ];
    }
}
