<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ResolvesAuthenticatedUser;
use App\Services\GovernanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceV1ModuleAvailability
{
    use ResolvesAuthenticatedUser;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authenticatedUser($request);
        if (! $user) return $next($request);

        $role = app(GovernanceService::class)->roleCode($user);
        $path = trim($request->path(), '/');

        // Fonctions masquées en V1 pour tous les rôles (code conservé).
        if (! config('pharmacare_v1.features.clinical_validation')
            && preg_match('#^(?:api/v1/)?organizations/[^/]+/prescriptions/[^/]+/validate$#', $path)) {
            return $this->refuse($request);
        }

        // FOSA : menu « Produits » masqué (P-07). L'API catalogue reste ouverte :
        // le mobile en a besoin pour la dispensation, les entrées et l'inventaire.
        if (in_array($role, [GovernanceService::SITE_ADMIN, GovernanceService::SITE_USER], true) && $path === 'products') {
            return $this->refuse($request);
        }

        if (! in_array($role, [GovernanceService::COORDINATION_ADMIN, GovernanceService::PROJECT_ADMIN], true)) {
            return $next($request);
        }

        // Admin Projet : catalogue en consultation seule (codification réservée à la Coordination).
        if ($role === GovernanceService::PROJECT_ADMIN && ! $request->isMethodSafe()
            && preg_match('#^(?:api/v1/)?organizations/[^/]+/catalog/(?:products|references)(?:/|$)#', $path)) {
            return $this->refuse($request);
        }

        if ($this->isInfrastructurePath($path) || $this->isAllowed($request, $role, $path)) return $next($request);

        return $this->refuse($request);
    }

    private function refuse(Request $request): Response
    {
        $message = 'Ce module n’est pas disponible dans la version de test actuelle de PharmaCare.';
        return $request->expectsJson() || $request->is('api/*')
            ? response()->json(['message' => $message, 'code' => 'module_not_available_v1'], 403)
            : response()->view('errors.403', ['message' => $message], 403);
    }

    private function isAllowed(Request $request, string $role, string $path): bool
    {
        if (str_starts_with($path, 'api/')) {
            // Comptes créés depuis « Ma Coordination » (mobile) : rôles
            // proposés et création seule, sans le module Utilisateurs.
            if ($role === GovernanceService::COORDINATION_ADMIN
                && (($path === 'api/v1/users' && $request->isMethod('post'))
                    || ($path === 'api/v1/assignable-roles' && $request->isMethodSafe()))) {
                return true;
            }

            return collect(config("pharmacare_v1.api_patterns.$role", []))->contains(fn ($pattern) => preg_match($pattern, $path) === 1);
        }

        if ($role === GovernanceService::COORDINATION_ADMIN
            && preg_match('#^organizations/[^/]+/missions(?:/|$)#', $path)) {
            return true;
        }
        if ($role === GovernanceService::COORDINATION_ADMIN && preg_match('#^organizations/[^/]+/projects(?:/|$)#', $path)) return true;
        // Comptes créés depuis « Ma Coordination » : création seule, sans
        // ouvrir le module Utilisateurs (absent du menu V1 de la Coordination).
        if ($role === GovernanceService::COORDINATION_ADMIN && $path === 'users' && $request->isMethod('post')) return true;
        if (preg_match('#^organizations/[^/]+/catalog$#', $path)) {
            return $request->query('section', 'lists') === 'lists';
        }
        if (preg_match('#^organizations/[^/]+/catalog/lists(?:/|$)#', $path)) return true;
        if (preg_match('#^organizations/[^/]+/catalog(?:/|$)#', $path)) return false;
        $root = explode('/', $path)[0] ?? '';
        return in_array($root, config("pharmacare_v1.web_paths.$role", []), true);
    }

    private function isInfrastructurePath(string $path): bool
    {
        return $path === '' || preg_match('#^(?:login|logout|forgot-password|reset-password|change-password|up|sanctum)(?:/|$)#', $path) === 1;
    }
}
