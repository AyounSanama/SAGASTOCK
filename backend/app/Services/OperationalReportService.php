<?php

namespace App\Services;

use App\Models\Dispensation;
use App\Models\Inventory;
use App\Models\Organization;
use App\Models\Receipt;
use App\Models\StockBalance;
use App\Models\SupplyOrder;
use App\Models\User;

/**
 * Rapport opérationnel d'une organisation, limité aux sites du compte.
 * Même calcul pour l'écran mobile (API) et la page Web.
 */
class OperationalReportService
{
    public function __construct(private readonly UserScopeService $scopes) {}

    public function summary(User $user, Organization $organization): array
    {
        $siteIds = $this->scopes->sites($user)->where('organization_id', $organization->id)->pluck('id');

        return [
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
        ];
    }
}
