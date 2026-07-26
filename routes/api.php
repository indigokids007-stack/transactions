<?php

use App\Http\Controllers\Api\BootstrapController;
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

    Route::post('transactions', [TransactionsController::class, 'store'])->name('transactions.store');
});
