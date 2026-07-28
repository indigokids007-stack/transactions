<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['active' => EnsureUserIsActive::class]);

        // Telegram posts the webhook with no session behind it; its own shared secret
        // header is what authenticates the call.
        $middleware->validateCsrfTokens(except: ['telegram/webhook']);

        // A deactivated caller must be turned away before a scoped binding resolves,
        // otherwise the 404 it would get for a record outside its scope tells it apart
        // from the 403 it gets for one inside.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
