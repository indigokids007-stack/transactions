<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('app.url', 'https://cara.example.test');
    config()->set('services.telegram.webhook_secret', 'hook-secret');
    config()->set('services.telegram.bot_token', 'bot-token');
});

it('registers the webhook and succeeds when telegram accepts it', function () {
    Http::fake(['*' => Http::response(['ok' => true, 'result' => true, 'description' => 'Webhook was set'])]);

    test()->artisan('telegram:set-webhook')
        ->expectsOutputToContain('Webhook was set')
        ->assertExitCode(0);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/botbot-token/setWebhook'
        && $request->data()['url'] === 'https://cara.example.test/telegram/webhook'
        && $request->data()['secret_token'] === 'hook-secret');
});

it('fails loudly when telegram refuses the webhook', function () {
    Http::fake(['*' => Http::response(['ok' => false, 'description' => 'bad webhook'], 400)]);

    test()->artisan('telegram:set-webhook')
        ->expectsOutputToContain('bad webhook')
        ->assertExitCode(1);
});

it('refuses to register a blank webhook secret instead of registering an unprotected webhook', function () {
    config()->set('services.telegram.webhook_secret', '');
    Http::fake();

    test()->artisan('telegram:set-webhook')
        ->expectsOutputToContain('TELEGRAM_WEBHOOK_SECRET')
        ->assertExitCode(1);

    Http::assertNothingSent();
});
