<?php

use App\Http\Controllers\Api\TelegramAuthController;
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => ['status' => 'ok'])->name('health');

Route::post('auth/telegram', [TelegramAuthController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('auth.telegram');
