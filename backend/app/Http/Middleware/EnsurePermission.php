<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsurePermission {
    public function handle(Request $request, Closure $next, string $permission): Response|JsonResponse {
        if (! $request->user() || ! $request->user()->hasPermission($permission)) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }
        return $next($request);
    }
}
