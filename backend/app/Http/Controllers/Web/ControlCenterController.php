<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SetupProgress;
use App\Models\PlatformStandard;
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

        return view('control-center.index', [
            'activeModule' => 'configuration',
            'progress' => $progress,
            'configurationStats' => [
                'organizations' => $this->scopes->organizations($request->user())->count(),
                'standards' => PlatformStandard::count(),
            ],
        ]);
    }
}
