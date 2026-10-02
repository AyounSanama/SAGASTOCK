<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ResolvesAuthenticatedUser;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * S-01 — Un compte désactivé, archivé, ou dont l'organisation est désactivée,
 * perd immédiatement l'accès : vérification à CHAQUE requête (pas seulement à
 * la connexion), jetons révoqués et session Web fermée.
 */
class EnsureAccountIsActive
{
    use ResolvesAuthenticatedUser;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authenticatedUser($request);
        if (! $user || ! ($reason = $this->blockingReason($user))) {
            return $next($request);
        }

        $user->tokens()->delete();
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $reason, 'code' => 'account_disabled'], 401);
        }

        return redirect()->route('login')->withErrors(['login' => $reason]);
    }

    public static function blockingReason(User $user): ?string
    {
        if (! $user->is_active || $user->trashed()) {
            return 'Ce compte est désactivé. Contactez votre administrateur.';
        }
        if ($user->organization_id && ! $user->organization()->where('is_active', true)->exists()) {
            return 'L’organisation de ce compte est désactivée.';
        }

        return null;
    }
}
