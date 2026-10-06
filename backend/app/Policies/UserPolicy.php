<?php

namespace App\Policies;

use App\Models\User;
use App\Services\CoordinationService;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Auth\Access\Response;

/**
 * AM-172 (niveau 3, lot 4) — Règles d'accès aux comptes, communes au Web et à
 * l'API. Compte hors périmètre : 404 (son existence n'est pas révélée) ;
 * action interdite sur un compte visible : 403.
 */
class UserPolicy
{
    public function __construct(
        private readonly UserScopeService $scopes,
        private readonly GovernanceService $governance,
    ) {}

    public function view(User $actor, User $target): Response
    {
        return $this->scopes->canAccess($actor, $target) ? Response::allow() : Response::denyAsNotFound();
    }

    /** Modifier ou réinitialiser le mot de passe. */
    public function update(User $actor, User $target): Response
    {
        return $this->manage($actor, $target, 'users.update_site_admin');
    }

    public function resetPassword(User $actor, User $target): Response
    {
        return $this->manage($actor, $target, 'users.update_site_admin');
    }

    /** Archiver : jamais son propre compte (géré par le Profil). */
    public function delete(User $actor, User $target): Response
    {
        $visible = $this->view($actor, $target);
        if ($visible->denied()) {
            return $visible;
        }
        $delegation = $this->siteAccountDelegation($actor, $target, 'users.suspend_site_admin');
        if ($delegation->denied()) {
            return $delegation;
        }
        if ($actor->is($target)) {
            return Response::denyWithStatus(422, 'Vous ne pouvez pas archiver votre propre compte.');
        }

        return $this->governanceRule($actor, $target);
    }

    public function restore(User $actor, User $target): Response
    {
        return $this->manage($actor, $target, 'users.update_site_admin');
    }

    /**
     * Suspendre ou réactiver un compte depuis « Ma Coordination » : Admins Projet
     * et Coordination en lecture seule de ses projets, comptes de ses FOSA.
     */
    public function setActive(User $actor, User $target): Response
    {
        if ($this->governance->roleCode($actor) !== GovernanceService::COORDINATION_ADMIN) {
            return Response::deny();
        }
        if ($actor->read_only || $actor->is($target)) {
            return Response::deny('Vous ne pouvez pas gérer ce compte.');
        }

        return app(CoordinationService::class)->manageableAccounts($actor)->whereKey($target->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private function manage(User $actor, User $target, string $delegatedPermission): Response
    {
        $visible = $this->view($actor, $target);
        if ($visible->denied()) {
            return $visible;
        }
        $delegation = $this->siteAccountDelegation($actor, $target, $delegatedPermission);

        return $delegation->denied() ? $delegation : $this->governanceRule($actor, $target);
    }

    private function governanceRule(User $actor, User $target): Response
    {
        return $this->governance->canManageUser($actor, $target)
            ? Response::allow()
            : Response::deny('Vous ne pouvez pas gérer ce compte.');
    }

    /**
     * Sans « users.manage », la permission déléguée ne vaut que pour un Admin
     * Site dont tous les sites sont dans le périmètre de l'acteur.
     */
    private function siteAccountDelegation(User $actor, User $target, string $permission): Response
    {
        if ($actor->hasPermission('users.manage')) {
            return Response::allow();
        }
        if (! $actor->hasPermission($permission) || $this->governance->roleCode($target) !== GovernanceService::SITE_ADMIN) {
            return Response::deny();
        }
        $targetSiteIds = $target->roles()->wherePivot('scope_type', 'site')->pluck('role_user.scope_id');
        $actorSiteIds = $this->scopes->siteIds($actor);

        return $targetSiteIds->isNotEmpty() && $targetSiteIds->every(fn (string $id) => $actorSiteIds->contains($id))
            ? Response::allow()
            : Response::deny();
    }
}
