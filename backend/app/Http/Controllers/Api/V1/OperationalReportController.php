<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dispensation;
use App\Models\Inventory;
use App\Models\Organization;
use App\Models\Receipt;
use App\Models\StockBalance;
use App\Models\SupplyOrder;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalReportController extends Controller
{
    public function __construct(private readonly UserScopeService $scopes) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
        $siteIds = $this->scopes->sites($request->user())->where('organization_id', $organization->id)->pluck('id');

        return response()->json([
            'generated_at' => now()->toISOString(),
            'stock' => [
                'available_quantity' => (float) StockBalance::whereIn('site_id', $siteIds)->sum('theoretical_quantity'),
                'reserved_quantity' => (float) StockBalance::whereIn('site_id', $siteIds)->sum('reserved_quantity'),
                'lots' => StockBalance::whereIn('site_id', $siteIds)->where('theoretical_quantity', '>', 0)->count(),
            ],
            'receipts' => [
                'total' => Receipt::whereIn('site_id', $siteIds)->count(),
                'validated' => Receipt::whereIn('site_id', $siteIds)->where('status', 'validated')->count(),
            ],
            'inventories' => [
                'total' => Inventory::whereIn('site_id', $siteIds)->count(),
                'validated' => Inventory::whereIn('site_id', $siteIds)->where('status', 'validated')->count(),
                'open' => Inventory::whereIn('site_id', $siteIds)->whereIn('status', ['draft', 'counting', 'submitted'])->count(),
            ],
            'orders' => [
                'total' => SupplyOrder::whereIn('requesting_site_id', $siteIds)->count(),
                'pending' => SupplyOrder::whereIn('requesting_site_id', $siteIds)->whereIn('status', ['submitted', 'approved', 'preparing'])->count(),
            ],
            'dispensations' => [
                'total' => Dispensation::whereIn('site_id', $siteIds)->count(),
                'this_month' => Dispensation::whereIn('site_id', $siteIds)->whereBetween('dispensed_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            ],
        ]);
    }
}
