<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;

/**
 * Niveau 7 — Règles d'accès aux dossiers patients, communes au Web et à l'API.
 * La fiche patient (identité, santé) est réservée aux comptes de la FOSA
 * (`patients.view`) ; dossier hors périmètre : 404 ; action interdite : 403.
 */
class PatientPolicy
{
    public function __construct(private readonly UserScopeService $scopes) {}

    /** Voir l'identité des patients (listes d'ordonnances et de dispensations comprises). */
    public function viewIdentity(User $actor): bool
    {
        return $actor->hasPermission('patients.view');
    }

    /**
     * Sans fiche patient : le patient d'une ordonnance ou d'une dispensation
     * est remplacé par son seul code (aucun nom, âge, téléphone ni allergie,
     * en JSON comme dans les vues).
     */
    public static function withoutIdentity($model)
    {
        $patient = $model->getRelationValue('patient');
        $model->setRelation('patient', $patient ? (new Patient)->forceFill(['id' => $patient->id, 'code' => $patient->code]) : null);
        // Entête de la sortie : âge et sexe sont des données du patient.
        foreach (['patient_age_years', 'patient_sex'] as $field) {
            if (array_key_exists($field, $model->getAttributes())) {
                $model->setAttribute($field, null);
            }
        }

        return $model;
    }

    public function view(User $actor, Patient $patient): Response
    {
        if (! $this->viewIdentity($actor)) {
            return Response::deny();
        }

        return $this->scope($actor)->whereKey($patient->id)->exists() ? Response::allow() : Response::denyAsNotFound();
    }

    /** Modifier, archiver ou restaurer le dossier. */
    public function update(User $actor, Patient $patient): Response
    {
        return $actor->hasPermission('patients.manage') ? $this->view($actor, $patient) : Response::deny();
    }

    /**
     * Patients du périmètre : rattachés à un site du compte, ou ayant une
     * ordonnance ou une dispensation sur l'un de ces sites.
     */
    public function scope(User $actor): Builder
    {
        $siteIds = $this->scopes->siteIds($actor);

        return Patient::query()->where(fn (Builder $query) => $query->whereIn('site_id', $siteIds)
            ->orWhereHas('prescriptions', fn (Builder $q) => $q->whereIn('site_id', $siteIds))
            ->orWhereHas('dispensations', fn (Builder $q) => $q->whereIn('site_id', $siteIds)));
    }
}
