<?php

use App\Actions\Drafts\ConfirmDraft;
use App\Enums\CategoryAppliesTo;
use App\Models\Category;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\EntryDraft;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config()->set('services.telegram.webhook_secret', 'hook-secret');
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
});

it('writes the transaction only on confirmation', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '120000 taksi'));
    $draft = EntryDraft::sole();

    expect(Transaction::count())->toBe(0);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    $transaction = Transaction::sole();

    expect($transaction->amount_minor)->toBe(120000)
        ->and($transaction->note)->toBe('taksi')
        ->and($transaction->user_id)->toBe($user->id)
        ->and($transaction->idempotency_key)->toBe($draft->id)
        ->and(EntryDraft::count())->toBe(0);
});

it('does not double write when confirmation is tapped twice', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));
    $draftId = EntryDraft::sole()->id;

    sendUpdate(callbackUpdate(111, 'd:'.substr($draftId, 0, 8).':ok'));
    sendUpdate(callbackUpdate(111, 'd:'.substr($draftId, 0, 8).':ok'));

    expect(Transaction::count())->toBe(1);
});

it('refuses an expired draft', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();
    $draft->update(['expires_at' => now()->subMinute()]);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(0);
});

it('refuses a draft belonging to another user', function () {
    User::factory()->create(['telegram_id' => 111]);
    User::factory()->create(['telegram_id' => 999]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(999, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(0)
        ->and(EntryDraft::count())->toBe(1);
});

it('cancels a draft', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':x'))->assertOk();

    expect(EntryDraft::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

it('changes the category of a draft without writing', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    $other = Category::factory()->create(['name' => 'Ovqat']);
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':c:'.$other->id))->assertOk();

    expect($draft->fresh()->payload['category_id'])->toBe($other->id)
        ->and(Transaction::count())->toBe(0);
});

/**
 * The draft the bot would build for `$user`, written straight to the table so a payload
 * the buttons cannot produce can still be pushed at the confirm step.
 *
 * @param  array<string, mixed>  $payload
 */
function draftFor(User $user, array $payload): EntryDraft
{
    return EntryDraft::create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'payload' => [
            'type' => 'expense',
            'amount' => '1000',
            'currency' => 'UZS',
            'occurred_on' => now()->toDateString(),
            'category_id' => null,
            'note' => null,
            'dimension_values' => [],
            ...$payload,
        ],
        'expires_at' => now()->addHour(),
    ]);
}

function saveFailedPrefix(): string
{
    return Str::before(__('bot.save_failed', [], 'uz'), ':reason');
}

it('does not write twice when the same draft reaches the action twice', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    $category = Category::factory()->create();
    $draft = draftFor($user, ['category_id' => $category->id]);

    $first = app(ConfirmDraft::class)->handle($draft, $user);
    $second = app(ConfirmDraft::class)->handle($draft, $user);

    expect(Transaction::count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($first->idempotency_key)->toBe($draft->id);
});

/**
 * The buttons prompt for a missing required dimension before they ever offer to save, so
 * this rule is checked where that prompt cannot stand in for it: at the action itself,
 * which is the surface a future caller would reach past the controller.
 */
it('refuses through the action when a required dimension has no value', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    $category = Category::factory()->create();
    $branch = Dimension::factory()->create(['is_required' => true]);
    $draft = draftFor($user, ['category_id' => $category->id]);

    expect(fn () => app(ConfirmDraft::class)->handle($draft, $user))
        ->toThrow(ValidationException::class, __('errors.dimension_required', ['dimension' => $branch->name]));

    expect(Transaction::count())->toBe(0)
        ->and(EntryDraft::count())->toBe(1);
});

it('refuses to write a draft whose category rejects its type', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create(['applies_to' => CategoryAppliesTo::Income]);
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(0)
        ->and(EntryDraft::count())->toBe(1);

    Http::assertSent(fn ($request) => ($request->data()['text'] ?? null) === __('bot.save_failed', [
        'reason' => __('errors.category_rejects_type'),
    ], 'uz'));
});

it('refuses to write a draft whose dimension value belongs to another dimension', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    $category = Category::factory()->create();
    $branch = Dimension::factory()->create(['is_required' => true]);
    $project = Dimension::factory()->create();
    $projectValue = DimensionValue::factory()->create(['dimension_id' => $project->id]);

    $draft = draftFor($user, [
        'category_id' => $category->id,
        'dimension_values' => [$branch->id => $projectValue->id],
    ]);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(0);

    Http::assertSent(fn ($request) => ($request->data()['text'] ?? null) === __('bot.save_failed', [
        'reason' => __('errors.dimension_value_invalid'),
    ], 'uz'));
});

it('refuses to write a draft whose category has been deactivated since', function () {
    User::factory()->create(['telegram_id' => 111]);
    $category = Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();
    $category->update(['is_active' => false]);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(0);

    Http::assertSent(fn ($request) => is_string($request->data()['text'] ?? null) && str_starts_with($request->data()['text'], saveFailedPrefix()));
});

it('keeps every button inside the telegram callback data limit', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->count(3)->create();
    $dimension = Dimension::factory()->create(['is_required' => true]);
    DimensionValue::factory()->count(3)->create(['dimension_id' => $dimension->id]);

    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'));
    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':cat'));

    $lengths = collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0]->data()['reply_markup'] ?? null)
        ->filter(fn ($markup) => is_string($markup))
        ->flatMap(fn (string $markup) => collect(json_decode($markup, true)['inline_keyboard'])->flatten(1))
        ->pluck('callback_data')
        ->filter()
        ->map(fn (string $data) => strlen($data));

    expect($lengths->count())->toBeGreaterThan(6)
        ->and($lengths->max())->toBeLessThanOrEqual(64);
});

it('refuses a draft id made of like wildcards', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));

    sendUpdate(callbackUpdate(111, 'd:%%%%%%%%:ok'))->assertOk();
    sendUpdate(callbackUpdate(111, 'd:________:ok'))->assertOk();

    expect(Transaction::count())->toBe(0)
        ->and(EntryDraft::count())->toBe(1);
});

it('prompts for the missing dimension by name instead of trying to save', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    $branch = Dimension::factory()->create(['is_required' => true]);
    DimensionValue::factory()->create(['dimension_id' => $branch->id]);
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    Http::assertSent(fn ($request) => ($request->data()['text'] ?? null) === __('bot.choose_dimension', [
        'dimension' => $branch->name,
    ], 'uz'));
});

it('asks for required dimensions before showing the preview', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    $branch = Dimension::factory()->create(['key' => 'branch', 'is_required' => true]);
    $value = DimensionValue::factory()->create(['dimension_id' => $branch->id]);
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(0);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).":dim:{$branch->id}:{$value->id}"));
    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::sole()->dimensionValues->pluck('id')->all())->toBe([$value->id]);
});
