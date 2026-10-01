<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Site;
use App\Models\User;

class GovernanceService
{
    public const SITE_DELEGABLE_PERMISSIONS = [
        'catalog.view', 'batches.manage', 'stocks.view', 'stocks.manage',
        'stocks.adjust', 'transfers.manage', 'receipts.manage',
        'dispensations.view', 'dispensations.manage', 'inventories.manage',
        'orders.manage', 'reports.view', 'synchronization.manage',
    ];

    public const SAGO_ADMIN = 'sago_admin';

    /** @deprecated Utiliser SAGO_ADMIN. */
    public const OWNER = self::SAGO_ADMIN;

    public const COORDINATION_ADMIN = 'coordination_admin';

    public const PROJECT_ADMIN = 'project_admin';

    public const SITE_ADMIN = 'site_admin';

    public const SITE_USER = 'site_user';

    public const OFFICIAL_ROLES = [
        self::SAGO_ADMIN,
        self::COORDINATION_ADMIN,
        self::PROJECT_ADMIN,
        self::SITE_ADMIN,
        self::SITE_USER,
    ];

    private const LEGACY_ALIASES = [
        'owner' => self::SAGO_ADMIN,
        'platform_owner' => self::SAGO_ADMIN,
        'organization_admin' => self::COORDINATION_ADMIN,
        'project_coordinator' => self::PROJECT_ADMIN,
        'facility_manager' => self::SITE_ADMIN,
        'pharmacist' => self::SITE_USER,
        'clinician' => self::SITE_USER,
        'supervisor' => self::SITE_USER,
    ];

    public function canonicalCode(string $code): string
    {
        return self::LEGACY_ALIASES[$code] ?? $code;
    }

    public function roleCode(User $user): ?string
    {
        $codes = $user->roles()->pluck('roles.code')
            ->map(fn (string $code) => $this->canonicalCode($code));

        foreach (self::OFFICIAL_ROLES as $code) {
            if ($codes->contains($code)) {
                return $code;
            }
        }

        return null;
    }

    public function dashboard(User $user): string
    {
        return match ($this->roleCode($user)) {
            self::SAGO_ADMIN => 'platform',
            self::COORDINATION_ADMIN => 'coordination',
            self::PROJECT_ADMIN => 'project',
            self::SITE_ADMIN, self::SITE_USER => 'site',
            default => 'site',
        };
    }

    /**
     * Page d'entrée après authentification.
     *
     * L'Admin Coordination commence son travail dans la Configuration. Les
     * autres profils conservent le tableau de bord partagé comme accueil.
     */
    public function landingRoute(User $user): string
    {
        return $this->roleCode($user) === self::SAGO_ADMIN ? 'sago.dashboard' : 'dashboard';
    }

    /** @return list<string> */
    public function assignableCodes(User $actor): array
    {
        if ($actor->read_only) {
            return [];
        }

        return match ($this->roleCode($actor)) {
            self::SAGO_ADMIN => [self::COORDINATION_ADMIN],
            self::COORDINATION_ADMIN => array_values(array_filter([
                self::PROJECT_ADMIN,
                self::COORDINATION_ADMIN,
                config('pharmacare_v1.features.coordination_creates_site_admin') ? self::SITE_ADMIN : null,
            ])),
            // Comptes FOSA créés par l'Admin Projet (cahier des charges, section 3).
            self::PROJECT_ADMIN => [self::SITE_ADMIN, self::SITE_USER],
            self::SITE_ADMIN => [self::SITE_USER],
            default => [],
        };
    }

    /**
     * Les comptes d'une FOSA ne peuvent être créés qu'une fois la FOSA validée
     * par la Coordination (refus serveur, quelle que soit l'interface).
     */
    public function assertFacilityOpenForAccounts(string $siteId): void
    {
        $facility = Site::with('healthFacility')->find($siteId)?->healthFacility;
        abort_unless($facility, 404);
        abort_unless(
            $facility->validation_status === \App\Models\HealthFacility::STATUS_VALIDATED,
            422,
            'Les comptes de cette FOSA ne pourront être créés qu’après sa validation par la Coordination.',
        );
    }

    /** Un Admin Coordination créé par une Coordination est en lecture seule. */
    public function createsReadOnlyAccount(User $actor, Role $role): bool
    {
        return $this->roleCode($actor) === self::COORDINATION_ADMIN
            && $this->canonicalCode($role->code) === self::COORDINATION_ADMIN;
    }

    public function assignableCode(User $actor): ?string
    {
        return $this->assignableCodes($actor)[0] ?? null;
    }

    /**
     * Attribution d'un rôle. Seuls les rôles officiels créent des comptes, et
     * uniquement pour les rôles placés sous eux dans la matrice (assignableCodes) ;
     * un compte sans rôle officiel ne crée et ne modifie plus aucun compte.
     */
    public function canAssign(User $actor, Role $role, string $scopeType, ?string $scopeId): bool
    {
        if ($this->roleCode($actor) === null) {
            return false;
        }

        $targetCode = $this->canonicalCode($role->code);
        // Comptes FOSA : toujours rattachés à un site, jamais à la plateforme.
        if (in_array($targetCode, [self::SITE_ADMIN, self::SITE_USER], true) && $scopeType !== 'site') {
            return false;
        }
        if (! in_array($targetCode, $this->assignableCodes($actor), true)) {
            return false;
        }

        $expectedScope = match ($targetCode) {
            self::COORDINATION_ADMIN => 'mission',
            self::PROJECT_ADMIN => 'project',
            self::SITE_ADMIN, self::SITE_USER => 'site',
            default => null,
        };
        if ($scopeType !== $expectedScope || ! $scopeId) {
            return false;
        }

        return app(UserScopeService::class)->allowsScope($actor, $scopeType, $scopeId);
    }

    /**
     * Modification, réinitialisation, archivage ou restauration d'un compte :
     * uniquement par un rôle officiel, sur un compte de son périmètre dont le rôle
     * est placé sous le sien, et jamais sur son propre compte (géré par le Profil).
     */
    public function canManageUser(User $actor, User $target): bool
    {
        $actorCode = $this->roleCode($actor);
        if ($actorCode === null || $actor->read_only || $actor->is($target)) {
            return false;
        }
        $targetCode = $this->roleCode($target);
        // Ancien compte sans rôle officiel : seul l'Admin Sago peut le traiter.
        $allowed = $targetCode === null
            ? $actorCode === self::SAGO_ADMIN
            : in_array($targetCode, $this->assignableCodes($actor), true);

        return $allowed && app(UserScopeService::class)->canAccess($actor, $target);
    }

    public function assertCanManageUser(User $actor, User $target): void
    {
        abort_unless($this->canManageUser($actor, $target), 403, 'Vous ne pouvez pas gérer ce compte.');
    }

    public function siteBelongsToProject(string $siteId, string $projectId): bool
    {
        return Site::whereKey($siteId)
            ->whereHas('healthFacility.projects', fn ($query) => $query->whereKey($projectId))
            ->exists();
    }
}
