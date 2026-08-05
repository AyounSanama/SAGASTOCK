<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockMovement;
use App\Services\ApplicationNavigationService;
use App\Services\DashboardService;
use App\Services\GovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $this->dashboard->build($user);
        $activities = ($user->hasPermission('audit.view') ? AuditLog::query() : AuditLog::where('user_id', $user->id))
            ->latest()->limit(8)->get(['id', 'event', 'created_at']);
        $movements = $user->hasPermission('stocks.view')
            ? StockMovement::with(['product:id,name', 'site:id,name'])->whereIn('site_id', $context['siteIds'])->latest('validated_at')->limit(6)->get()
            : collect();

        return response()->json([
            'dashboard' => app(GovernanceService::class)->dashboard($user),
            'widgets' => $context['widgets'],
            'navigation' => app(ApplicationNavigationService::class)->mobileItems($user),
            'stats' => $context['stats'],
            'activities' => $activities,
            'recent_movements' => $movements,
        ]);
    }
}
