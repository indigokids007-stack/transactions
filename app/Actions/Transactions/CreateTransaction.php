<?php

namespace App\Actions\Transactions;

use App\DataObjects\TransactionInput;
use App\Enums\RevisionAction;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CreateTransaction
{
    public function __construct(private readonly RecordRevision $recordRevision) {}

    public function handle(TransactionInput $input, User $actor): Transaction
    {
        return DB::transaction(function () use ($input, $actor): Transaction {
            $transaction = Transaction::create([
                'user_id' => $input->userId,
                'department_id' => $actor->department_id,
                'type' => $input->type,
                'amount_minor' => Money::toMinor($input->amount, $input->currency),
                'currency' => $input->currency,
                'occurred_on' => $input->occurredOn,
                'category_id' => $input->categoryId,
                'note' => $input->note,
                'created_by' => $actor->id,
                'idempotency_key' => $input->idempotencyKey,
            ]);

            $transaction->dimensionValues()->sync($this->pivotPayload($input));

            $this->recordRevision->handle($transaction, RevisionAction::Created, $actor);

            return $transaction->load(['category', 'user', 'department', 'dimensionValues.dimension']);
        });
    }

    /** @return array<int, array<string, int>> */
    private function pivotPayload(TransactionInput $input): array
    {
        $payload = [];

        foreach ($input->dimensionValues as $dimensionId => $valueId) {
            $payload[$valueId] = ['dimension_id' => $dimensionId];
        }

        return $payload;
    }
}
