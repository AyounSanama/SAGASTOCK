<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\StockMovement;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $context = $this->dashboard->build($user);
        $stats = $context['stats'];
        $widgets = $context['widgets'];
        $organizationIds = $context['organizationIds'];
        $siteIds = $context['siteIds'];

        $activities = $user->hasPermission('audit.view')
            ? AuditLog::with('user')->latest()->limit(8)->get()
            : AuditLog::with('user')->where('user_id', $user->id)->latest()->limit(8)->get();
        $recentMovements = $user->hasPermission('stocks.view')
            ? StockMovement::with(['product', 'site'])->whereIn('site_id', $siteIds)->latest('validated_at')->limit(6)->get()
            : collect();
        $organizations = $user->hasPermission('organizations.view')
            ? Organization::whereIn('id', $organizationIds)->orderBy('name')->limit(6)->get()
            : collect();

        return view('dashboard.home', compact('stats', 'widgets', 'activities', 'recentMovements', 'organizations'));
    }
}
