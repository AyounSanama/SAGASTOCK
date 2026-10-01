<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserLocale
{
    /** Langue du dernier utilisateur connecté, reprise par les pages publiques (connexion). */
    public const COOKIE = 'pharmacare_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('pharmacare_languages.translated_locales', ['fr']);
        $remembered = $request->cookie(self::COOKIE);
        $locale = $request->user()?->preferred_locale ?? $remembered;
        App::setLocale(in_array($locale, $supported, true) ? $locale : 'fr');

        $response = $next($request);

        // Relue après la requête : un changement de langue vient d'être enregistré.
        $current = $request->user()?->preferred_locale;
        if (in_array($current, $supported, true) && $current !== $remembered) {
            $response->headers->setCookie(cookie(self::COOKIE, $current, 60 * 24 * 365));
        }

        return $response;
    }
}
