<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('prunes expired sanctum tokens daily', function () {
    $prune = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command ?? '', 'sanctum:prune-expired'));

    expect($prune)->not->toBeNull()
        ->and($prune->expression)->toBe('0 0 * * *');
});
