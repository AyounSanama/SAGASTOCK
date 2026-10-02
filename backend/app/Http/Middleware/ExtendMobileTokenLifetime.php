<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * S-03 — Jeton mobile de 30 jours, prolongé à chaque échange avec le serveur
 * (synchronisation). Un téléphone resté plus de 30 jours sans connexion doit se
 * reconnecter ; ses opérations en attente restent sur le téléphone.
 * La révocation (compte, organisation, FOSA) reste immédiate (S-01).
 */
class ExtendMobileTokenLifetime
{
    public const LIFETIME_DAYS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $token = $request->bearerToken() ? $request->user('sanctum')?->currentAccessToken() : null;
        // Une écriture au plus par jour et par jeton.
        if ($token instanceof PersonalAccessToken && $response->getStatusCode() < 400
            && (! $token->expires_at || $token->expires_at->lt(now()->addDays(self::LIFETIME_DAYS - 1)))) {
            $token->forceFill(['expires_at' => now()->addDays(self::LIFETIME_DAYS)])->save();
        }

        return $response;
    }
}
