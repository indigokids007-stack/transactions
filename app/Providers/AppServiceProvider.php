<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Models\User;
use App\Policies\TransactionPolicy;
use App\Support\TransactionScope;
use Illuminate\Support\Facades\Gate;
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
