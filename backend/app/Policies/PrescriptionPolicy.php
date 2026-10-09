<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Auth\Access\Response;

/**
 * Niveau 7 — Règles d'accès aux ordonnances, communes au Web et à l'API :
 * consultation dans le périmètre des sites du compte ; validation clinique
 * (hors V1) réservée à `prescriptions.validate`.
 */
class PrescriptionPolicy
{
    public function __construct(private readonly UserScopeService $scopes) {}

    public function view(User $actor, Prescription $prescription): Response
    {
        if (! $actor->hasPermission('prescriptions.view')) {
            return Response::deny();
        }

        return $this->scopes->siteIds($actor)->contains($prescription->site_id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function validate(User $actor, Prescription $prescription): Response
    {
        return $actor->hasPermission('prescriptions.validate') ? $this->view($actor, $prescription) : Response::deny();
    }
}
