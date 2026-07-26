<?php

namespace App\Actions\Transactions;

use App\Enums\RevisionAction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateTransaction
{
    /**
     * The columns an edit may reach. `user_id` and `department_id` are deliberately
     * absent: who a transaction belongs to, and the department snapshot taken from
     * them, are settled at creation and never re-decided by an editor.
     */
    private const UPDATABLE = ['type', 'amount_minor', 'currency', 'occurred_on', 'category_id', 'note'];

    public function __construct(private readonly RecordRevision $recordRevision) {}

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<int, int>|null  $dimensionValues  null when the caller left the dimensions alone
     */
    public function handle(Transaction $transaction, array $changes, ?array $dimensionValues, User $actor): Transaction
    {
        return DB::transaction(function () use ($transaction, $changes, $dimensionValues, $actor): Transaction {
            $transaction->fill(Arr::only($changes, self::UPDATABLE))->save();

            if ($dimensionValues !== null) {
                $transaction->syncDimensionValues($dimensionValues);
                $transaction->load('dimensionValues');
            }

            $this->recordRevision->handle($transaction, RevisionAction::Updated, $actor);

            return $transaction->load(['category', 'user', 'department', 'dimensionValues.dimension']);
        });
    }
}
