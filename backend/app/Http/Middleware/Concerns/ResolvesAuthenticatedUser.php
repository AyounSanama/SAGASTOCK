<?php

namespace App\Http\Middleware\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Les middlewares globaux (et ceux ajoutés au groupe API) s'exécutent AVANT
 * le middleware de route `auth:sanctum`. `$request->user()` n'y voit que la
 * session Web : sans résolution explicite du jeton Bearer, leurs contrôles
 * ne s'appliqueraient pas aux vrais appels mobiles.
 */
trait ResolvesAuthenticatedUser
{
    protected function authenticatedUser(Request $request): ?User
    {
        return $request->user() ?? ($request->bearerToken() ? $request->user('sanctum') : null);
    }
}
