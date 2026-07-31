<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * Only the login route was throttled. Everything behind the token was open, including the
 * export, which streams a whole scoped history in one request on a single worker server.
 */
it('refuses an eleventh export inside the minute', function () {
    Sanctum::actingAs(User::factory()->create());

    foreach (range(1, 10) as $ignored) {
        $this->get('/api/exports/transactions')->assertOk();
    }

    $this->get('/api/exports/transactions')->assertStatus(429);
});

it('leaves the rest of the authenticated surface alone at that rate', function () {
    Sanctum::actingAs(User::factory()->create());

    foreach (range(1, 11) as $ignored) {
        $this->getJson('/api/transactions')->assertOk();
    }
});

/**
 * The group and webhook ceilings are asserted on the route definition rather than by
 * sending 121 and 301 requests, which would buy the same confidence for a much slower
 * suite. Removing either middleware entry still turns this red.
 */
it('throttles every authenticated api route as a group', function () {
    $routes = ['me', 'bootstrap', 'transactions.index', 'transactions.store', 'reports.summary'];

    foreach ($routes as $name) {
        expect(Route::getRoutes()->getByName($name)?->gatherMiddleware())->toContain('throttle:api');
    }
});

it('throttles the telegram webhook', function () {
    expect(Route::getRoutes()->getByName('telegram.webhook')?->gatherMiddleware())
        ->toContain('throttle:telegram-webhook');
});

/**
 * The middleware assertions above prove a route is throttled, not what it is throttled to,
 * so the numbers themselves were pinned by nothing. This resolves each named limiter the
 * way `ThrottleRequests` does and reads the ceiling it hands back.
 */
it('pins the ceiling each named limiter allows', function () {
    $request = Request::create('/api/transactions');
    $request->setUserResolver(fn () => User::factory()->create());

    $ceiling = fn (string $name): int => RateLimiter::limiter($name)($request)->maxAttempts;

    expect($ceiling('api'))->toBe(120)
        ->and($ceiling('exports'))->toBe(10)
        ->and($ceiling('telegram-webhook'))->toBe(300);
});
