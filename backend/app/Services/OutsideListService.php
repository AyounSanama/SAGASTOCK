<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Receipt;
use App\Models\Site;
use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Niveau 7 (décision du 09/10) — article hors Liste Standard livré par un
 * tiers : il n'entre qu'avec l'origine « Autre » et un motif, sa ligne est
 * marquée « hors liste » et l'entrée est signalée à la Coordination.
 * Commun aux réceptions Web et API.
 */
class OutsideListService
{
    public function __construct(private readonly AllowedProductsService $allowed) {}

    /** @return Collection<int, string> produits de la Liste Standard du site */
    public function standardProductIds(Organization $organization, Site $site): Collection
    {
        return $this->allowed->query($organization, collect([$site->id]))->pluck('id')->values();
    }

    /**
     * Produits hors liste de l'entrée (id => nom), après contrôle de la règle.
     *
     * @param  Collection<int, string>  $productIds
     * @return Collection<string, string>
     */
    public function check(Organization $organization, Site $site, Collection $productIds, array $origin, ?string $reason): Collection
    {
        $outside = $organization->products()->whereIn('id', $productIds->filter()->unique())
            ->whereNotIn('id', $this->standardProductIds($organization, $site))->pluck('name', 'id');
        if ($outside->isEmpty()) {
            return $outside;
        }
        if (($origin['origin_type'] ?? null) !== StockOriginService::TYPE_OTHER) {
            throw ValidationException::withMessages(['items' => '« '.$outside->first().' » est hors Liste Standard : il n’entre qu’avec l’origine « Autre (livré par un tiers) ».']);
        }
        if (mb_strlen(trim((string) $reason)) < 5) {
            throw ValidationException::withMessages(['outside_list_reason' => 'Indiquez le motif de l’article hors liste.']);
        }

        return $outside;
    }

    /** Signale l'entrée aux Admins Coordination de la FOSA (aucune donnée patient). */
    public function signal(Site $site, Receipt $receipt, Collection $outside): void
    {
        if ($outside->isEmpty()) {
            return;
        }
        $coordinators = User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('roles.code', 'coordination_admin')
            ->where('role_user.scope_type', 'mission')->where('role_user.scope_id', $site->healthFacility?->mission_id))->get();
        Notification::send($coordinators, new OperationalNotification([
            'title' => 'Article hors liste reçu',
            'message' => "{$site->healthFacility?->name} : {$outside->values()->join(', ')} reçu hors Liste Standard (livré par un tiers, réception {$receipt->reference}).",
            'category' => 'outside_list',
            'action_path' => '/coordination',
        ]));
    }
}
