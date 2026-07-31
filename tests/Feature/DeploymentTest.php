<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * The seam the per-route tests cannot see. `phpunit.xml` forces its own `DB_*` values, so
 * every test in this suite reaches Postgres no matter what the shipped configuration says,
 * and the served application can be pointed somewhere else entirely without a single test
 * going red. What is checked here is the configuration a deployment actually boots from.
 *
 * The complement to this file is `make smoke`, which asks the running container the same
 * question over real HTTP. Neither replaces the other: this one runs in CI-less isolation,
 * that one proves the process serving requests agrees.
 *
 * @return array<string, string>
 */
function envTemplate(): array
{
    $lines = file(base_path('.env.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    return collect($lines === false ? [] : $lines)
        ->reject(fn (string $line) => str_starts_with(trim($line), '#'))
        ->filter(fn (string $line) => str_contains($line, '='))
        ->mapWithKeys(function (string $line) {
            [$key, $value] = explode('=', $line, 2);

            return [trim($key) => trim($value, " \t\"")];
        })
        ->all();
}

it('ships an env template that points the served application at postgres', function () {
    $template = envTemplate();

    expect($template['DB_CONNECTION'] ?? null)->toBe('pgsql')
        ->and($template['DB_HOST'] ?? null)->toBe('db')
        ->and($template['DB_PORT'] ?? null)->toBe('5432');
});

it('ships an env template whose credentials match the compose database', function () {
    $template = envTemplate();
    $compose = (string) file_get_contents(base_path('docker-compose.yml'));

    expect($compose)->toContain('POSTGRES_DB: '.($template['DB_DATABASE'] ?? ''))
        ->and($compose)->toContain('POSTGRES_USER: '.($template['DB_USERNAME'] ?? ''))
        ->and($compose)->toContain('POSTGRES_PASSWORD: '.($template['DB_PASSWORD'] ?? ''));
});

it('ships an env template that does not leak stack traces by default', function () {
    $template = envTemplate();

    expect($template['APP_DEBUG'] ?? null)->toBe('false')
        ->and($template['APP_ENV'] ?? null)->toBe('production')
        ->and($template['LOG_LEVEL'] ?? null)->not->toBe('debug');
});

it('answers a database backed route for an authenticated caller', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/transactions')->assertOk();
});

/**
 * `getJson()` would send `Accept: application/json` and pass either way. A caller that
 * does not ask for JSON used to be redirected to a `login` route this application never
 * defines, which turned every unauthenticated API request into a 500.
 */
it('refuses an unauthenticated api caller with 401 whatever it accepts', function () {
    $this->get('/api/transactions', ['Accept' => '*/*'])
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});
