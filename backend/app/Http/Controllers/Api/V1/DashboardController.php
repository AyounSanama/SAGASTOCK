<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockMovement;
use App\Models\Organization;
use App\Models\OrganizationEffectiveConfiguration;
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
        if (app(GovernanceService::class)->roleCode($user) === GovernanceService::SAGO_ADMIN) {
            $organizations = Organization::query()->get();
            $configurationTotal = OrganizationEffectiveConfiguration::where('status', 'active')->count();
            $syncedTotal = OrganizationEffectiveConfiguration::where('status', 'active')->where('synchronization_status', 'synced')->count();
            $stats = [
                'organizations' => $organizations->count(),
                'organizations_active' => $organizations->where('is_active', true)->count(),
                'interventions' => OrganizationEffectiveConfiguration::count(),
                'pending_sync' => OrganizationEffectiveConfiguration::where('synchronization_status', 'pending')->count(),
                'compliance' => $configurationTotal ? (int) round(($syncedTotal / $configurationTotal) * 100) : 0,
            ];
            $widgets = [
                ['key'=>'organizations','label'=>'Organisations enregistrées','caption'=>'Toutes les organisations','icon'=>'organizations','route'=>'/organizations','color'=>'blue','value'=>$stats['organizations']],
                ['key'=>'organizations_active','label'=>'Organisations actives','caption'=>'Actives actuellement','icon'=>'organizations','route'=>'/organizations','color'=>'cyan','value'=>$stats['organizations_active']],
                ['key'=>'interventions','label'=>'Interventions','caption'=>'Configurations historisées','icon'=>'activity_logs','route'=>'/standards/history','color'=>'purple','value'=>$stats['interventions']],
                ['key'=>'pending_sync','label'=>'En attente de synchronisation','caption'=>'Configurations à synchroniser','icon'=>'synchronization','route'=>'/standards/history','color'=>'orange','value'=>$stats['pending_sync']],
                ['key'=>'compliance','label'=>'Taux global de conformité','caption'=>'Configurations synchronisées','icon'=>'reports','route'=>'/standards','color'=>'blue','value'=>$stats['compliance']],
            ];
            $activities = OrganizationEffectiveConfiguration::with('organization:id,name')->latest('effective_at')->limit(8)->get()->map(fn ($item) => [
                'id'=>$item->id, 'event'=>$item->intervention_action === 'restored' ? 'Configuration restaurée' : 'Configuration appliquée',
                'organization'=>$item->organization?->name, 'category'=>$item->configuration_category,
                'version'=>'v1.'.max(0,$item->configuration_version - 1), 'status'=>$item->synchronization_status,
                'created_at'=>$item->effective_at,
            ]);
            return response()->json([
                'dashboard'=>'sago', 'widgets'=>$widgets, 'navigation'=>app(ApplicationNavigationService::class)->mobileItems($user),
                'stats'=>$stats, 'activities'=>$activities, 'recent_movements'=>[],
            ]);
        }
        $context = $this->dashboard->build($user);
        $activities = ($user->hasPermission('audit.view') ? AuditLog::query()->withoutHealthData() : AuditLog::where('user_id', $user->id))
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
            'recent_projects' => $context['recentProjects'],
            'recent_movements' => $movements,
        ]);
    }
}
