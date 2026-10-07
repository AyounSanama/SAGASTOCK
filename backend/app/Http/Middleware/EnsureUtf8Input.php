<?php

namespace App\Http\Middleware;

use App\Support\Utf8;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toutes les saisies (Web et API) sont converties en UTF-8 valide avant
 * validation et enregistrement : un accent envoyé dans un autre encodage ne
 * peut plus être enregistré abîmé ni bloquer une réponse JSON.
 */
class EnsureUtf8Input
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->query->replace(Utf8::cleanArray($request->query->all()));
        $request->request->replace(Utf8::cleanArray($request->request->all()));
        if ($request->isJson()) {
            $request->json()->replace(Utf8::cleanArray($request->json()->all()));
        }

        return $next($request);
    }
}
