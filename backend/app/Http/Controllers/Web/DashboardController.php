<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationEffectiveConfiguration;
use App\Models\StockMovement;
use App\Services\GovernanceService;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (app(GovernanceService::class)->roleCode($user) === GovernanceService::SAGO_ADMIN) {
            $organizations = Organization::query()->with('countries')
                ->orderBy('name')->get();
            $activities = AuditLog::with('user')->withoutHealthData()->latest()->limit(8)->get();
            $countries = $organizations->flatMap->countries->unique('id');
            $interventions = OrganizationEffectiveConfiguration::with(['organization:id,name,geographic_access_type', 'appliedBy:id,name'])
                ->latest('effective_at')->limit(8)->get();
            $configurationTotal = OrganizationEffectiveConfiguration::where('status', 'active')->count();
            $syncedTotal = OrganizationEffectiveConfiguration::where('status', 'active')->where('synchronization_status', 'synced')->count();
            return view('dashboard.sago', [
                'organizations' => $organizations,
                'activities' => $activities,
                'sagoStats' => [
                    'active' => $organizations->where('is_active', true)->count(),
                    'inactive' => $organizations->where('is_active', false)->count(),
                    'single' => $organizations->where('geographic_access_type', 'single_country')->count(),
                    'multi' => $organizations->where('geographic_access_type', 'multi_country')->count(),
                    'countries' => $countries->count(),
                    'organizations_total' => $organizations->count(),
                    'interventions' => OrganizationEffectiveConfiguration::count(),
                    'pending_sync' => OrganizationEffectiveConfiguration::where('synchronization_status', 'pending')->count(),
                    'compliance' => $configurationTotal > 0 ? (int) round(($syncedTotal / $configurationTotal) * 100) : 0,
                ],
                'interventions' => $interventions,
            ]);
        }
        // AM-172 : tableau de bord de la Coordination (maquette Coordination 07).
        if (app(GovernanceService::class)->roleCode($user) === GovernanceService::COORDINATION_ADMIN
            && ($missionId = app(\App\Services\UserScopeService::class)->coordinationMissionIds($user)->first())) {
            $mission = \App\Models\Mission::with(['country', 'organization'])->findOrFail($missionId);

            return view('dashboard.coordination', [
                'mission' => $mission,
                'board' => app(\App\Services\CoordinationService::class)->dashboard($user, $mission),
            ]);
        }
        $context = $this->dashboard->build($user);
        $stats = $context['stats'];
        $widgets = $context['widgets'];
        $organizationIds = $context['organizationIds'];
        $siteIds = $context['siteIds'];

        $activities = $user->hasPermission('audit.view')
            ? AuditLog::with('user')->withoutHealthData()->latest()->limit(8)->get()
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
