<?php

namespace App\Actions\Transactions;

use App\Enums\RevisionAction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteTransaction
{
    public function __construct(private readonly RecordRevision $recordRevision) {}

    /**
     * The revision is written before the row is soft deleted so its snapshot holds the
     * transaction as it stood, not as it looks once withdrawn.
     */
    public function handle(Transaction $transaction, User $actor): void
    {
        DB::transaction(function () use ($transaction, $actor): void {
            $this->recordRevision->handle($transaction, RevisionAction::Deleted, $actor);

            $transaction->delete();
        });
    }
}
