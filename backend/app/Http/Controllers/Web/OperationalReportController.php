<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\OperationalReportService;
use App\Services\UserScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Rapports opérationnels : mêmes chiffres que l'écran mobile. */
class OperationalReportController extends Controller
{
    public function __construct(
        private readonly UserScopeService $scopes,
        private readonly OperationalReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        $organizations = $this->scopes->organizations($request->user())->orderBy('name')->get();
        $organization = $request->filled('organization_id')
            ? $organizations->firstWhere('id', $request->string('organization_id')->toString())
            : ($organizations->firstWhere('id', $request->user()->organization_id) ?? $organizations->first());
        abort_if(! $organization, $request->filled('organization_id') ? 404 : 403);

        return view('reports.index', [
            'organizations' => $organizations,
            'organization' => $organization,
            'report' => $this->reports->summary($request->user(), $organization),
        ]);
    }
}
