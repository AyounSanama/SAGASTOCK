<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OperationalReportService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalReportController extends Controller
{
    public function __construct(
        private readonly UserScopeService $scopes,
        private readonly OperationalReportService $reports,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);

        return response()->json($this->reports->summary($request->user(), $organization));
    }
}
