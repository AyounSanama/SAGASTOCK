<?php

namespace App\Http\Middleware;

use App\Services\GovernanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceV1ModuleAvailability
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) return $next($request);

        $role = app(GovernanceService::class)->roleCode($user);
        if (! in_array($role, [GovernanceService::COORDINATION_ADMIN, GovernanceService::PROJECT_ADMIN], true)) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        if ($this->isInfrastructurePath($path) || $this->isAllowed($request, $role, $path)) return $next($request);

        $message = 'Ce module n’est pas disponible dans la version de test actuelle de PharmaCare.';
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'code' => 'module_not_available_v1'], 403)
            : response()->view('errors.403', ['message' => $message], 403);
    }

    private function isAllowed(Request $request, string $role, string $path): bool
    {
        if (str_starts_with($path, 'api/')) {
            return collect(config("pharmacare_v1.api_patterns.$role", []))->contains(fn ($pattern) => preg_match($pattern, $path) === 1);
        }

        if ($role === GovernanceService::COORDINATION_ADMIN
            && preg_match('#^organizations/[^/]+/missions(?:/|$)#', $path)) {
            return true;
        }
        if ($role === GovernanceService::COORDINATION_ADMIN && preg_match('#^organizations/[^/]+/projects(?:/|$)#', $path)) return true;
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
