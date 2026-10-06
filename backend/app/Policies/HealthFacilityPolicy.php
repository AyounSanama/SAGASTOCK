<?php

namespace App\Policies;

use App\Models\HealthFacility;
use App\Models\User;
use App\Services\CoordinationService;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Auth\Access\Response;

/**
 * AM-172 (niveau 3, lot 4) — Règles d'accès aux FOSA, communes au Web et à
 * l'API. FOSA hors périmètre : 404 ; action interdite : 403.
 */
class HealthFacilityPolicy
{
    public function __construct(
        private readonly UserScopeService $scopes,
        private readonly GovernanceService $governance,
    ) {}

    public function view(User $actor, HealthFacility $facility): Response
    {
        return $this->scopes->facilityIds($actor)->contains($facility->id) ? Response::allow() : Response::denyAsNotFound();
    }

    /** Modifier la FOSA, ses départements, pharmacies et sites ; l'archiver ou la restaurer. */
    public function update(User $actor, HealthFacility $facility): Response
    {
        if (! $actor->hasPermission('structures.manage') && ! $actor->hasPermission('health_facilities.manage')) {
            return Response::deny();
        }

        return $this->view($actor, $facility);
    }

    /** Valider, refuser, suspendre ou réactiver : Admin Coordination des projets de la FOSA. */
    public function coordinate(User $actor, HealthFacility $facility): Response
    {
        if ($this->governance->roleCode($actor) !== GovernanceService::COORDINATION_ADMIN || $actor->read_only) {
            return Response::deny();
        }

        return app(CoordinationService::class)->facilities($actor)->whereKey($facility->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
