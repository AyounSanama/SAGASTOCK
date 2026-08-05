<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ModuleActivation;
use App\Models\SetupProgress;
use App\Services\ConfigurationWorkflowService;
use App\Services\UserScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControlCenterController extends Controller
{
    public function __construct(
        private readonly UserScopeService $scopes,
    ) {}

    public function show(Request $request): View
    {
        $progress = $request->filled(ConfigurationWorkflowService::REQUEST_KEY)
            ? SetupProgress::query()
                ->where('workflow_id', $request->input(ConfigurationWorkflowService::REQUEST_KEY))
                ->where('created_by', $request->user()->id)
                ->firstOrFail()
            : SetupProgress::query()
                ->where('created_by', $request->user()->id)
                ->latest('updated_at')
                ->first();

        $baseQuery = $this->scopes->organizations($request->user());
        $organization = ($progress?->scope_type === 'organization' && $progress->scope_id
            ? (clone $baseQuery)->whereKey($progress->scope_id)
            : $baseQuery->oldest())
            ->with([
                'missions' => fn ($query) => $query->where('is_active', true),
                'projects' => fn ($query) => $query->where('is_active', true),
                'healthFacilities' => fn ($query) => $query->where('is_active', true)->with([
                    'sites' => fn ($site) => $site->where('is_active', true),
                ]),
                'standardLists.latestVersion',
            ])
            ->first();

        $activations = ModuleActivation::query()
            ->where('target_type', 'organization')
            ->when(
                $organization,
                fn ($query) => $query->where('target_id', $organization->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('is_enabled', true)
            ->pluck('module_code');

        $moduleLabels = collect(ConfigurationWizardController::MODULES);
        $featureLabels = collect(ConfigurationWizardController::FEATURES);

        return view('control-center.index', [
            'activeModule' => 'configuration',
            'progress' => $progress,
            'organization' => $organization,
            'mission' => $organization?->missions->first(),
            'project' => $organization?->projects->first(),
            'facility' => $organization?->healthFacilities->first(),
            'site' => $organization?->healthFacilities->first()?->sites->first(),
            'standardList' => $organization?->standardLists->first(),
            'canAddOrganization' => $this->scopes->isPlatform($request->user()),
            'enabledModules' => $activations
                ->reject(fn ($code) => str_starts_with($code, 'feature:'))
                ->map(fn ($code) => $moduleLabels->get($code, $code))
                ->values(),
            'enabledFeatures' => $activations
                ->filter(fn ($code) => str_starts_with($code, 'feature:'))
                ->map(fn ($code) => $featureLabels->get(str_replace('feature:', '', $code), $code))
                ->values(),
        ]);
    }
}
