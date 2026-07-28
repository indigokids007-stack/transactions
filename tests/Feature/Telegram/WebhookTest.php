<?php

use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\EntryDraft;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.telegram.webhook_secret', 'hook-secret');
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
});

it('rejects an update without the secret header', function () {
    sendUpdate(textUpdate(111, '1000'), 'wrong')->assertStatus(403);
});

it('creates a draft and writes nothing when a known user sends an amount', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();

    sendUpdate(textUpdate(111, '120000 taksi'))->assertOk();

    expect(EntryDraft::count())->toBe(1)
        ->and(Transaction::count())->toBe(0)
        ->and(EntryDraft::sole()->payload['note'])->toBe('taksi');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage'));
});

it('answers with a hint when the message has no amount', function () {
    User::factory()->create(['telegram_id' => 111]);

    sendUpdate(textUpdate(111, 'salom'))->assertOk();

    expect(EntryDraft::count())->toBe(0);
});

it('creates a pending user when registration is open and refuses to record', function () {
    Setting::put('registration_open', true);

    sendUpdate(textUpdate(222, '1000'))->assertOk();

    expect(User::where('telegram_id', 222)->exists())->toBeTrue()
        ->and(EntryDraft::count())->toBe(0);
});

it('creates nothing when registration is closed', function () {
    Setting::put('registration_open', false);

    sendUpdate(textUpdate(333, '1000'))->assertOk();

    expect(User::where('telegram_id', 333)->exists())->toBeFalse();
});

it('rejects an update that carries no secret header at all', function () {
    test()->postJson('/telegram/webhook', textUpdate(111, '1000'))->assertStatus(403);

    expect(EntryDraft::count())->toBe(0);
});

it('rejects every update while no secret is configured', function () {
    config()->set('services.telegram.webhook_secret', null);

    sendUpdate(textUpdate(111, '1000'), '')->assertStatus(403);
});

it('turns a blocked user away without recording anything', function () {
    User::factory()->create(['telegram_id' => 111, 'status' => UserStatus::Blocked]);
    Category::factory()->create();

    sendUpdate(textUpdate(111, '1000'))->assertOk();

    expect(EntryDraft::count())->toBe(0);

    Http::assertSent(fn ($request) => ($request->data()['text'] ?? null) === __('bot.blocked', [], 'uz'));
});

it('answers an update it has nothing to do with and records nothing', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();

    sendUpdate(['update_id' => 5])->assertOk();
    sendUpdate(['update_id' => 6, 'message' => ['message_id' => 1, 'chat' => ['id' => 111], 'from' => ['id' => 111]]])->assertOk();
    sendUpdate(['update_id' => 7, 'edited_message' => ['text' => '1000']])->assertOk();
    sendUpdate(callbackUpdate(111, 'salom'))->assertOk();
    sendUpdate(callbackUpdate(111, 'd:zzzzzzzz:ok'))->assertOk();
    sendUpdate(callbackUpdate(111, 'd:1234567:ok'))->assertOk();

    expect(EntryDraft::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendMessage'));
});
