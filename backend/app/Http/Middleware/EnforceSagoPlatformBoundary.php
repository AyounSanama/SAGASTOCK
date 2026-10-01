<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ResolvesAuthenticatedUser;
use App\Services\GovernanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSagoPlatformBoundary
{
    use ResolvesAuthenticatedUser;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authenticatedUser($request);
        if (! $user || app(GovernanceService::class)->roleCode($user) !== GovernanceService::SAGO_ADMIN) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $operationalPath = preg_match(
            '#^(?:api/v1/)?(?:missions|projects|funding|health-facilities|dispensing-sites|users|standard-lists|products|stocks|receipts|dispensations|inventories|orders|reports|synchronization|settings|project-settings|site-settings|activity-log-local)(?:/|$)#',
            $path,
        ) || preg_match('#^(?:api/v1/)?organizations/(?!archived(?:/|$))[^/]+/.+#', $path)
            || preg_match('#^configuration/(?!(?:organization|platform-standards)(?:/|$))#', $path);

        if (! $operationalPath) {
            return $next($request);
        }

        return $request->expectsJson() || $request->is('api/*')
            ? response()->json(['message' => 'Ce domaine opérationnel est réservé aux administrateurs des organisations.'], 403)
            : response()->view('errors.403', [
                'message' => 'Ce domaine opérationnel est réservé aux administrateurs des organisations.',
            ], 403);
    }
}
