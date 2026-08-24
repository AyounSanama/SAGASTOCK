<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotentApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        $user = $request->user();
        if (! $key || ! $user) {
            return $next($request);
        }

        abort_unless(
            preg_match('/^[0-9a-fA-F-]{36}$/', $key) === 1,
            422,
            'La clé d’idempotence doit être un UUID valide.'
        );

        $stored = ApiIdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->first();

        if ($stored) {
            abort_unless(
                $stored->method === $request->method() &&
                $stored->path === '/'.$request->path(),
                409,
                'Cette clé d’idempotence a déjà été utilisée pour une autre opération.'
            );

            return response(
                $stored->response_body,
                $stored->response_status,
                ['Content-Type' => $stored->content_type ?: 'application/json']
            )->header('Idempotency-Replayed', 'true');
        }

        $response = $next($request);
        if ($response->getStatusCode() < 500) {
            ApiIdempotencyKey::query()->create([
                'user_id' => $user->id,
                'key' => $key,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'content_type' => $response->headers->get('Content-Type'),
                'expires_at' => now()->addDays(30),
            ]);
        }

        return $response;
    }
}
