<?php

it('answers the health endpoint', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'database' => 'ok']);
});

/**
 * The container healthcheck watches this endpoint, so an endpoint that answered `ok` on
 * the strength of PHP running would report a healthy container whose every database
 * backed route returns 500 — which is exactly what shipped.
 */
it('fails the health endpoint when the database cannot be reached', function () {
    /** @var array<string, mixed> $pgsql */
    $pgsql = config('database.connections.pgsql');
    $original = config('database.default');

    config([
        'database.connections.unreachable' => [...$pgsql, 'host' => '127.0.0.1', 'port' => 1],
        'database.default' => 'unreachable',
    ]);

    // Restored before the test ends whatever the assertions do, since `RefreshDatabase`
    // rolls back on whichever connection is default by then and would otherwise fail
    // teardown against the unreachable one instead of reporting this test's result.
    try {
        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJson(['status' => 'error', 'database' => 'unavailable']);
    } finally {
        config(['database.default' => $original]);
    }
});
