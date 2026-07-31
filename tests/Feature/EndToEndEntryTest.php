<?php

use App\Models\Category;
use App\Models\EntryDraft;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('services.telegram.webhook_secret', 'hook-secret');
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
});

it('records a bot entry and shows it in the report of the same staff member', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create(['name' => 'Taksi']);

    sendUpdate(textUpdate(111, '120000 taksi'));
    $draft = EntryDraft::sole();
    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'));

    expect(Transaction::count())->toBe(1);

    Sanctum::actingAs($user);

    $this->getJson('/api/reports/summary?from='.today()->toDateString()
        .'&to='.today()->toDateString().'&group_by=category')
        ->assertOk()
        ->assertJsonPath('groups.0.label', 'Taksi')
        ->assertJsonPath('groups.0.amount_minor', 120000);

    $this->getJson('/api/transactions')->assertOk()->assertJsonCount(1, 'data');
});

it('hides that transaction from an unrelated staff member', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '5000'));
    sendUpdate(callbackUpdate(111, 'd:'.substr(EntryDraft::sole()->id, 0, 8).':ok'));

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/transactions')->assertOk()->assertJsonCount(0, 'data');
});
