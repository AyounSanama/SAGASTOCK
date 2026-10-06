<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Auth\Access\Response;

/**
 * AM-172 (niveau 3, lot 4) — Règles d'accès aux projets / programmes, communes
 * au Web et à l'API. Projet hors périmètre : 404 ; action interdite : 403.
 */
class ProjectPolicy
{
    public function __construct(
        private readonly UserScopeService $scopes,
        private readonly GovernanceService $governance,
    ) {}

    public function view(User $actor, Project $project): Response
    {
        return $this->scopes->projects($actor)->whereKey($project->id)->exists() ? Response::allow() : Response::denyAsNotFound();
    }

    /** Modifier, archiver, appliquer l'approvisionnement aux FOSA (permission vérifiée par la route). */
    public function update(User $actor, Project $project): Response
    {
        return $this->view($actor, $project);
    }

    public function delete(User $actor, Project $project): Response
    {
        return $this->view($actor, $project);
    }

    /** Un projet archivé n'est restauré par la Coordination que dans ses missions. */
    public function restore(User $actor, Project $project): Response
    {
        if ($this->governance->roleCode($actor) === GovernanceService::COORDINATION_ADMIN
            && ! $this->scopes->coordinationMissionIds($actor)->contains($project->mission_id)) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    /** Configuration médicale et Liste Standard du projet : Coordination uniquement (cahier des charges). */
    public function configure(User $actor, Project $project): Response
    {
        $visible = $this->view($actor, $project);
        if ($visible->denied()) {
            return $visible;
        }

        return $this->governance->roleCode($actor) === GovernanceService::COORDINATION_ADMIN
            && $actor->hasPermission('standard_lists.manage')
            ? Response::allow()
            : Response::deny();
    }
}
