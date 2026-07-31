<?php

use App\Models\Department;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The database half of the rule that reference data a transaction points at is retired by
 * deactivating it, never by deleting it. Before this, deleting a department nulled
 * `department_id` on every transaction ever charged to it, and deleting a dimension or one
 * of its values took the pivot rows recording the choice with it. Neither wrote a
 * revision, and a revision snapshot stores bare value ids, so nothing could say what had
 * been lost.
 *
 * The failing delete runs inside `DB::transaction()` so it rolls back to a savepoint. A
 * bare failing statement would poison the surrounding `RefreshDatabase` transaction and
 * every assertion after it would report on that instead of on the record.
 */
function refuseDelete(callable $delete): void
{
    expect(fn () => DB::transaction($delete))->toThrow(QueryException::class);
}

it('refuses to delete a department a transaction was charged to', function () {
    $department = Department::factory()->create();
    $transaction = Transaction::factory()->create(['department_id' => $department->id]);

    refuseDelete(fn () => $department->delete());

    expect(Department::whereKey($department->id)->exists())->toBeTrue()
        ->and($transaction->fresh()->department_id)->toBe($department->id);
});

it('refuses to delete a department a soft deleted transaction was charged to', function () {
    $department = Department::factory()->create();
    $transaction = Transaction::factory()->create(['department_id' => $department->id]);
    $transaction->delete();

    refuseDelete(fn () => $department->delete());

    expect($department->fresh()->isReferencedByLedger())->toBeTrue();
});

it('still deletes a department no transaction was ever charged to', function () {
    $department = Department::factory()->create();

    expect($department->isReferencedByLedger())->toBeFalse();

    $department->delete();

    expect(Department::whereKey($department->id)->exists())->toBeFalse();
});

it('refuses to delete a dimension a transaction recorded a value for', function () {
    $dimension = Dimension::factory()->create();
    $value = DimensionValue::factory()->for($dimension)->create();
    $transaction = Transaction::factory()->create();
    $transaction->syncDimensionValues([$dimension->id => $value->id]);

    refuseDelete(fn () => $dimension->delete());

    expect(Dimension::whereKey($dimension->id)->exists())->toBeTrue()
        ->and($transaction->fresh()->dimensionValues)->toHaveCount(1);
});

it('refuses to delete a dimension value a transaction recorded', function () {
    $dimension = Dimension::factory()->create();
    $value = DimensionValue::factory()->for($dimension)->create();
    $transaction = Transaction::factory()->create();
    $transaction->syncDimensionValues([$dimension->id => $value->id]);

    refuseDelete(fn () => $value->delete());

    expect(DimensionValue::whereKey($value->id)->exists())->toBeTrue()
        ->and($value->fresh()->isReferencedByLedger())->toBeTrue();
});

it('still deletes a dimension and its values while no transaction has used them', function () {
    $dimension = Dimension::factory()->create();
    $value = DimensionValue::factory()->for($dimension)->create();

    expect($dimension->isReferencedByLedger())->toBeFalse()
        ->and($value->isReferencedByLedger())->toBeFalse();

    $dimension->delete();

    expect(Dimension::whereKey($dimension->id)->exists())->toBeFalse()
        ->and(DimensionValue::whereKey($value->id)->exists())->toBeFalse();
});
