<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ResolvesAuthenticatedUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * S-04 — Tant que le mot de passe temporaire n'est pas remplacé, le serveur
 * refuse tout, sauf le changement de mot de passe, la déconnexion et la
 * lecture du compte (Web et API, quelle que soit l'interface).
 */
class EnforcePasswordChange
{
    use ResolvesAuthenticatedUser;

    private const API_ALLOWED = ['api/v1/auth/password', 'api/v1/auth/logout', 'api/v1/auth/me', 'api/v1/health'];

    private const WEB_ALLOWED = ['profile', 'profile/password', 'profile/locale', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authenticatedUser($request);
        if (! $user?->must_change_password) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        if (in_array($path, self::API_ALLOWED, true) || in_array($path, self::WEB_ALLOWED, true)
            || str_starts_with($path, 'build/') || str_starts_with($path, 'css/') || str_starts_with($path, 'js/')) {
            return $next($request);
        }

        $message = 'Vous devez remplacer votre mot de passe temporaire avant de continuer.';
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message, 'code' => 'password_change_required'], 403);
        }

        return redirect()->route('profile.show')->with('status', $message);
    }
}
