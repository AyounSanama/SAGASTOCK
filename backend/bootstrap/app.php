<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUtf8Input;
use App\Http\Middleware\EnforceSagoPlatformBoundary;
use App\Http\Middleware\EnforceV1ModuleAvailability;
use App\Http\Middleware\EnsureIdempotentApiRequest;
use App\Http\Middleware\ApplyUserLocale;
use App\Http\Middleware\EnforceReadOnlyAccount;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnforcePasswordChange;
use App\Http\Middleware\ExtendMobileTokenLifetime;
use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Saisies toujours en UTF-8 (accents corrects), avant tout autre traitement.
        $middleware->prepend(EnsureUtf8Input::class);
        $middleware->append(AddSecurityHeaders::class);
        $middleware->append(EnforceSagoPlatformBoundary::class);
        $middleware->append(EnforceV1ModuleAvailability::class);
        // S-01 / S-04 : compte actif et mot de passe temporaire remplacé, à chaque requête.
        $middleware->web(append: [EnsureAccountIsActive::class, EnforcePasswordChange::class, ApplyUserLocale::class, EnforceReadOnlyAccount::class]);
        $middleware->api(append: [EnsureAccountIsActive::class, EnforcePasswordChange::class, EnforceReadOnlyAccount::class, ExtendMobileTokenLifetime::class]);
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
            'idempotency' => EnsureIdempotentApiRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
