<?php

namespace App\Http\Middleware;

use App\Services\GovernanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(
            $request->user()
                && app(GovernanceService::class)->roleCode($request->user()) === strtolower($role),
            403,
        );

        return $next($request);
    }
}
