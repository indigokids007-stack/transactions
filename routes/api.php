<?php

use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\TelegramAuthController;
use App\Http\Controllers\Api\TransactionsController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => ['status' => 'ok'])->name('health');

Route::post('auth/telegram', [TelegramAuthController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('auth.telegram');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('me', fn (Request $request) => UserResource::make(
        $request->user()->loadMissing(['department', 'managedDepartments'])
    ))->name('me');

    Route::get('bootstrap', BootstrapController::class)->name('bootstrap');

    Route::get('transactions', [TransactionsController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [TransactionsController::class, 'store'])->name('transactions.store');

    Route::patch('transactions/{transaction}', [TransactionsController::class, 'update'])
        ->whereNumber('transaction')
        ->name('transactions.update');

    Route::delete('transactions/{transaction}', [TransactionsController::class, 'destroy'])
        ->whereNumber('transaction')
        ->name('transactions.destroy');

    Route::get('transactions/{transaction}/revisions', [TransactionsController::class, 'revisions'])
        ->whereNumber('transaction')
        ->name('transactions.revisions');

    Route::get('reports/summary', [ReportsController::class, 'summary'])->name('reports.summary');
    Route::get('reports/trend', [ReportsController::class, 'trend'])->name('reports.trend');
});
