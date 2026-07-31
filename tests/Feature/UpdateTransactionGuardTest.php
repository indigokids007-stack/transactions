<?php

use App\Actions\Transactions\UpdateTransaction;
use App\Models\Transaction;
use App\Models\TransactionRevision;
use App\Support\Money;

/**
 * `CreateTransaction` asserts its amount invariants whoever calls it; the update path
 * filled `amount_minor` straight from the caller's array and asserted nothing. There is
 * one caller today and it validates, but that same asymmetry is what let the bot write
 * around the field rules before it was closed.
 */
function updateWith(Transaction $transaction, array $changes): Transaction
{
    return app(UpdateTransaction::class)->handle($transaction, $changes, null, $transaction->user);
}

it('refuses an amount that is not positive', function () {
    $transaction = Transaction::factory()->create();

    expect(fn () => updateWith($transaction, ['amount_minor' => 0]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => updateWith($transaction, ['amount_minor' => -500]))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses an amount past the column ceiling', function () {
    $transaction = Transaction::factory()->create();

    expect(fn () => updateWith($transaction, ['amount_minor' => Money::MAX_MINOR + 1]))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses an amount that is not an integer number of minor units', function () {
    $transaction = Transaction::factory()->create();

    expect(fn () => updateWith($transaction, ['amount_minor' => '12.34']))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses an unsupported currency', function () {
    $transaction = Transaction::factory()->create();

    expect(fn () => updateWith($transaction, ['currency' => 'XYZ', 'amount_minor' => 5000]))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a currency change that does not carry the amount it applies to', function () {
    $transaction = Transaction::factory()->create(['currency' => 'UZS']);

    expect(fn () => updateWith($transaction, ['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class);
});

it('writes nothing and records no revision when it refuses', function () {
    $transaction = Transaction::factory()->create(['amount_minor' => 120000]);

    expect(fn () => updateWith($transaction, ['amount_minor' => 0, 'note' => 'tampered']))
        ->toThrow(InvalidArgumentException::class);

    expect($transaction->fresh()->amount_minor)->toBe(120000)
        ->and($transaction->fresh()->note)->toBeNull()
        ->and(TransactionRevision::count())->toBe(0);
});

it('still applies an amount inside the bounds', function () {
    $transaction = Transaction::factory()->create(['amount_minor' => 120000]);

    updateWith($transaction, ['amount_minor' => Money::MAX_MINOR]);

    expect($transaction->fresh()->amount_minor)->toBe(Money::MAX_MINOR);
});

it('still applies a change that leaves the amount alone', function () {
    $transaction = Transaction::factory()->create();

    updateWith($transaction, ['note' => 'taksi']);

    expect($transaction->fresh()->note)->toBe('taksi');
});
