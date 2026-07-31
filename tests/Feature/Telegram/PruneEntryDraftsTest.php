<?php

use App\Models\EntryDraft;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;

function draft(User $user, string $expiresAt): EntryDraft
{
    return EntryDraft::create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'payload' => ['amount' => '1000'],
        'expires_at' => $expiresAt,
    ]);
}

it('deletes only the drafts that have expired', function () {
    $user = User::factory()->create();
    $stale = draft($user, now()->subMinute()->toDateTimeString());
    $live = draft($user, now()->addMinute()->toDateTimeString());

    test()->artisan('transactions:prune-drafts')
        ->expectsOutputToContain('Deleted 1 expired entry draft(s).')
        ->assertSuccessful();

    expect(EntryDraft::pluck('id')->all())->toBe([$live->id])
        ->and(EntryDraft::find($stale->id))->toBeNull();
});

it('prunes drafts hourly', function () {
    $prune = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command ?? '', 'transactions:prune-drafts'));

    expect($prune)->not->toBeNull()
        ->and($prune->expression)->toBe('0 * * * *');
});
