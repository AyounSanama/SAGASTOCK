<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response|JsonResponse
    {
        if (! $request->user()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Authentification requise.'], 401)
                : redirect()->route('login');
        }

        $allowed = collect(explode('|', $permission))
            ->contains(fn (string $candidate) => $request->user()->hasPermission($candidate));

        if (! $allowed) {
            if ($request->hasSession()) {
                $request->session()->flash('error', 'Vous n’êtes pas autorisé à accéder à cette page.');
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Action non autorisée.'], 403)
                : response()->view('errors.403', ['message' => 'Vous n’êtes pas autorisé à accéder à cette page.'], 403);
        }

        return $next($request);
    }
}
