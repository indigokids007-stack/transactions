<?php

use App\Models\Category;
use App\Models\EntryDraft;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Telegram being unreachable is different from Telegram saying no, and only the second was
 * handled. A timeout or a DNS failure raised `ConnectionException` out of the client and
 * failed the whole webhook request, so Telegram never got its 200 and redelivered the
 * update. On the message path that wrote another draft every time. On the confirm path the
 * transaction had already been committed and the draft already deleted, so the redelivery
 * answered "expired" for an entry that had in fact been saved.
 */
beforeEach(function () {
    config()->set('services.telegram.webhook_secret', 'hook-secret');
    Sleep::fake();
});

function telegramIsUnreachable(): void
{
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
}

it('accepts the update and writes one draft when telegram cannot be reached', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    telegramIsUnreachable();

    sendUpdate(textUpdate(111, '120000 taksi'))->assertOk();

    expect(EntryDraft::count())->toBe(1);
});

it('keeps the saved transaction and accepts the update when the confirmation reply cannot be sent', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    $category = Category::factory()->create();
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
    sendUpdate(textUpdate(111, '120000 taksi'));
    $draft = EntryDraft::sole();

    telegramIsUnreachable();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(1)
        ->and(EntryDraft::count())->toBe(0);
});

/**
 * Counted in the fake rather than through `Http::assertSentCount()`, which only records
 * pairs that produced a response and so never sees an attempt that threw.
 */
it('retries a call that cannot be delivered before giving up', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();

    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    sendUpdate(textUpdate(111, '120000 taksi'))->assertOk();

    expect($attempts)->toBe(3);
});

it('still swallows a refusal by telegram rather than failing the update', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    Http::fake(['*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);

    sendUpdate(textUpdate(111, '120000 taksi'))->assertOk();

    expect(EntryDraft::count())->toBe(1);
});
