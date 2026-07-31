<?php

use App\Enums\TransactionType;
use App\Models\DimensionValue;
use App\Models\Transaction;
use Illuminate\Database\QueryException;

it('stores money in minor units and casts its type', function () {
    $transaction = Transaction::factory()->create([
        'type' => TransactionType::Expense,
        'amount_minor' => 120000,
        'currency' => 'UZS',
    ]);

    expect($transaction->fresh()->type)->toBe(TransactionType::Expense)
        ->and($transaction->fresh()->amount_minor)->toBe(120000);
});

it('attaches at most one value per dimension', function () {
    $transaction = Transaction::factory()->create();
    $value = DimensionValue::factory()->create();

    $transaction->dimensionValues()->attach($value, ['dimension_id' => $value->dimension_id]);
    $transaction->dimensionValues()->attach($value, ['dimension_id' => $value->dimension_id]);
})->throws(QueryException::class);

it('soft deletes', function () {
    $transaction = Transaction::factory()->create();

    $transaction->delete();

    expect(Transaction::count())->toBe(0)
        ->and(Transaction::withTrashed()->count())->toBe(1);
});
