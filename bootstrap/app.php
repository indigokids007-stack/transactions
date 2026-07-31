<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

        // The framework's default sends every unauthenticated caller to a `login` route,
        // which this application does not define: the only login it has is the panel's.
        // An API caller is sent nowhere at all, so it is told 401 instead of being handed
        // a redirect to a sign-in page it cannot use.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*')
            ? null
            : route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An API route has no HTML to fall back on. Without this, an unauthenticated
        // caller that did not send `Accept: application/json` is redirected to a `login`
        // route this application never defines, and a plain 401 arrives as a 500.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
