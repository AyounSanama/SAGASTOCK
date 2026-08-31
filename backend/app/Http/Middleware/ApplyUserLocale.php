<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('pharmacare_languages.translated_locales', ['fr']);
        $locale = $request->user()?->preferred_locale;
        App::setLocale(in_array($locale, $supported, true) ? $locale : 'fr');

        return $next($request);
    }
}
