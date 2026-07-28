<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('telegram/webhook', TelegramWebhookController::class)
    ->middleware(VerifyTelegramWebhook::class)
    ->name('telegram.webhook');
