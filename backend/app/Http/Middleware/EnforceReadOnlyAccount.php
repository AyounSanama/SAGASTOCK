<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ResolvesAuthenticatedUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compte en lecture seule : toute requête d'écriture est refusée, quelle que
 * soit la route, sauf la gestion de son propre compte (profil, mot de passe,
 * langue, notifications, appareils, déconnexion).
 */
class EnforceReadOnlyAccount
{
    use ResolvesAuthenticatedUser;

    private const ACCOUNT_PATHS = [
        'logout', 'profile', 'profile/*',
        'api/v1/auth/logout', 'api/v1/auth/password', 'api/v1/auth/profile', 'api/v1/auth/locale', 'api/v1/auth/theme', 'api/v1/auth/devices/*',
        'api/v1/notifications/*',
        // État technique du téléphone, pas une donnée métier.
        'api/v1/sync/report',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authenticatedUser($request);
        if ($user?->read_only && ! $request->isMethodSafe() && ! $request->is(...self::ACCOUNT_PATHS)) {
            $message = 'Compte en lecture seule : cette action n’est pas autorisée.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $message], 403);
            }
            abort(403, $message);
        }

        return $next($request);
    }
}
