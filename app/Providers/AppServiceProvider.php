<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Models\User;
use App\Policies\TransactionPolicy;
use App\Support\TransactionScope;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Transaction::class, TransactionPolicy::class);

        $this->bindTransactionsWithinTheCallersScope();
        $this->defineRateLimits();
    }

    /**
     * Named rather than inline `throttle:120,1` strings, because the export sits inside the
     * authenticated group and carries a second, tighter ceiling. Two inline throttles
     * derive the same cache key from the same request and would spend one allowance twice;
     * a named limiter mixes its own name into the key, so the two count separately.
     *
     * Each API limit is keyed on the authenticated user, so an office sharing one address
     * is not rationed collectively.
     */
    private function defineRateLimits(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Keyed by address: Telegram is the only caller that should reach this, and an
        // update it refuses to deliver is one it will send again later.
        RateLimiter::for('telegram-webhook', fn (Request $request) => Limit::perMinute(300)
            ->by($request->ip()));
    }

    /**
     * Resolving `{transaction}` through the scope is what turns a record the caller may
     * not see into a 404 instead of a 403: the row is never loaded, so no endpoint can
     * confirm that it exists.
     */
    private function bindTransactionsWithinTheCallersScope(): void
    {
        Route::bind('transaction', function (string $value): Transaction {
            $viewer = request()->user();

            if (! $viewer instanceof User) {
                abort(404);
            }

            return TransactionScope::apply(Transaction::query(), $viewer)
                ->with(['category', 'user', 'department', 'dimensionValues.dimension'])
                ->findOrFail($value);
        });
    }
}
