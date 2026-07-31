<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Post a Telegram update at the webhook the way Telegram does, secret header included.
 *
 * @param  array<string, mixed>  $update
 */
function sendUpdate(array $update, string $secret = 'hook-secret'): TestResponse
{
    return test()->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)
        ->postJson('/telegram/webhook', $update);
}

/** @return array<string, mixed> */
function textUpdate(int $telegramId, string $text): array
{
    return [
        'update_id' => 1,
        'message' => [
            'message_id' => 10,
            'chat' => ['id' => $telegramId],
            'from' => ['id' => $telegramId, 'first_name' => 'Alisher', 'language_code' => 'uz'],
            'text' => $text,
        ],
    ];
}

/** @return array<string, mixed> */
function callbackUpdate(int $telegramId, string $data): array
{
    return [
        'update_id' => 2,
        'callback_query' => [
            'id' => 'cb-1',
            'from' => ['id' => $telegramId, 'first_name' => 'Alisher', 'language_code' => 'uz'],
            'message' => ['message_id' => 42, 'chat' => ['id' => $telegramId]],
            'data' => $data,
        ],
    ];
}

/**
 * Build a signed Telegram `initData` query string, the way the Telegram client does.
 *
 * @param  array<string, string>  $overrides
 */
function buildInitData(array $overrides = [], string $botToken = 'test-bot-token'): string
{
    $fields = array_merge([
        'auth_date' => (string) now()->timestamp,
        'query_id' => 'AAA',
        'user' => json_encode(['id' => 111, 'first_name' => 'Alisher', 'language_code' => 'uz']),
    ], $overrides);

    ksort($fields);

    $checkString = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
    $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $fields['hash'] = hash_hmac('sha256', $checkString, $secret);

    return http_build_query($fields);
}
