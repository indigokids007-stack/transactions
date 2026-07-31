<?php

use App\Models\Category;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\EntryDraft;
use App\Models\Transaction;
use App\Models\User;
use App\Support\StickyDefaults;
use Illuminate\Support\Facades\Http;

/**
 * A value the admin retires must not follow a staff member around. The sticky defaults
 * copy the last entry's dimension values into every new draft, and the bot only prompts
 * for a dimension whose key is absent, so a key holding a retired value was never asked
 * about, carried no button, and failed the write every time. Resending the amount rebuilt
 * an identical draft. With no mini app there was no way out of it at all.
 */
beforeEach(function () {
    config()->set('services.telegram.webhook_secret', 'hook-secret');
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
});

function transactionUsing(User $user, Dimension $dimension, DimensionValue $value): Transaction
{
    $transaction = Transaction::factory()->for($user)->create();
    $transaction->syncDimensionValues([$dimension->id => $value->id]);

    return $transaction;
}

it('stops carrying a value forward once it is deactivated', function () {
    $user = User::factory()->create();
    $dimension = Dimension::factory()->create();
    $value = DimensionValue::factory()->for($dimension)->create();
    transactionUsing($user, $dimension, $value);

    expect(StickyDefaults::for($user)['dimension_values'])->toBe([$dimension->id => $value->id]);

    $value->update(['is_active' => false]);

    expect(StickyDefaults::for($user)['dimension_values'])->toEqual((object) []);
});

it('stops carrying a value forward once its dimension is deactivated', function () {
    $user = User::factory()->create();
    $dimension = Dimension::factory()->create();
    $value = DimensionValue::factory()->for($dimension)->create();
    transactionUsing($user, $dimension, $value);

    $dimension->update(['is_active' => false]);

    expect(StickyDefaults::for($user)['dimension_values'])->toEqual((object) []);
});

it('starts a new draft without the retired value the last entry used', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    $dimension = Dimension::factory()->create();
    $value = DimensionValue::factory()->for($dimension)->create();
    transactionUsing($user, $dimension, $value);
    $value->update(['is_active' => false]);

    sendUpdate(textUpdate(111, '5000 taksi'))->assertOk();

    expect(EntryDraft::sole()->payload['dimension_values'])->toBe([]);
});

/**
 * The recoverable case: the dimension is still required, so dropping the retired value
 * puts the draft back on the asking path instead of refusing it.
 */
it('asks again for a required dimension whose held value was retired', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    $category = Category::factory()->create();
    $dimension = Dimension::factory()->create(['is_required' => true]);
    $retired = DimensionValue::factory()->for($dimension)->create(['is_active' => false]);
    DimensionValue::factory()->for($dimension)->create(['name' => 'Hali ham bor']);

    $draft = draftFor($user, [
        'category_id' => $category->id,
        'dimension_values' => [$dimension->id => $retired->id],
    ]);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    Http::assertSent(fn ($request) => ($request->data()['text'] ?? null) === __('bot.choose_dimension', [
        'dimension' => $dimension->name,
    ]));

    expect(Transaction::count())->toBe(0)
        ->and($draft->fresh()->payload['dimension_values'])->toBe([]);
});

/**
 * The case with no prompt path at all: a dimension that is not required is never asked
 * about, so a retired value there could only ever refuse the save.
 */
it('saves a draft whose retired value belongs to a dimension that is not required', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    $category = Category::factory()->create();
    $dimension = Dimension::factory()->create(['is_required' => false]);
    $retired = DimensionValue::factory()->for($dimension)->create(['is_active' => false]);

    $draft = draftFor($user, [
        'category_id' => $category->id,
        'dimension_values' => [$dimension->id => $retired->id],
    ]);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::sole()->dimensionValues)->toHaveCount(0)
        ->and(EntryDraft::count())->toBe(0);
});

it('saves a draft whose held value belongs to a dimension that was switched off', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    $category = Category::factory()->create();
    $dimension = Dimension::factory()->create(['is_required' => true, 'is_active' => false]);
    $value = DimensionValue::factory()->for($dimension)->create();

    $draft = draftFor($user, [
        'category_id' => $category->id,
        'dimension_values' => [$dimension->id => $value->id],
    ]);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::sole()->dimensionValues)->toHaveCount(0);
});

it('leaves a draft holding a value that is still active exactly as it was', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    $category = Category::factory()->create();
    $dimension = Dimension::factory()->create(['is_required' => true]);
    $value = DimensionValue::factory()->for($dimension)->create();

    $draft = draftFor($user, [
        'category_id' => $category->id,
        'dimension_values' => [$dimension->id => $value->id],
    ]);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::sole()->dimensionValues->pluck('id')->all())->toBe([$value->id]);
});
