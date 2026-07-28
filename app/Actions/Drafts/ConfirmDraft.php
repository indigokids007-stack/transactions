<?php

namespace App\Actions\Drafts;

use App\Actions\Transactions\CreateTransaction;
use App\DataObjects\TransactionInput;
use App\Enums\TransactionType;
use App\Models\EntryDraft;
use App\Models\Transaction;
use App\Models\User;
use App\Validation\TransactionFieldValidator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * The only place the bot writes. The payload goes through the same rules an HTTP create
 * goes through before `CreateTransaction` sees it, so a draft cannot store a category
 * that rejects its type or leave a required dimension empty, and the draft uuid is the
 * idempotency key, so tapping confirm twice cannot produce two rows.
 */
class ConfirmDraft
{
    public function __construct(
        private readonly CreateTransaction $createTransaction,
        private readonly TransactionFieldValidator $validator,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(EntryDraft $draft, User $user): Transaction
    {
        // The controller only ever looks a draft up inside the caller's own drafts, so this
        // cannot fire from there. It is asserted here for the same reason the field rules
        // are: the action is the write, and it should not depend on its caller having
        // checked who owns what.
        if ($draft->user_id !== $user->id) {
            throw new AuthorizationException('This draft belongs to another user.');
        }

        $written = $this->writtenFor($draft);

        if ($written instanceof Transaction) {
            $draft->delete();

            return $written;
        }

        /** @var array<string, mixed> $payload */
        $payload = $draft->payload;

        $data = [
            'type' => $payload['type'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'occurred_on' => $payload['occurred_on'] ?? null,
            'category_id' => $payload['category_id'] ?? null,
            'note' => $payload['note'] ?? null,
            'dimension_values' => TransactionFieldValidator::dimensionValues($payload['dimension_values'] ?? null),
        ];

        $this->validator->forCreate($data)->validate();

        $transaction = $this->write($draft, $user, $data);

        $draft->delete();

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(EntryDraft $draft, User $user, array $data): Transaction
    {
        $input = new TransactionInput(
            userId: $user->id,
            departmentId: $user->department_id,
            type: TransactionType::from($this->string($data, 'type')),
            amount: $this->string($data, 'amount'),
            currency: $this->string($data, 'currency'),
            occurredOn: CarbonImmutable::parse($this->string($data, 'occurred_on')),
            categoryId: (int) $this->string($data, 'category_id'),
            note: is_string($data['note']) ? $data['note'] : null,
            dimensionValues: TransactionFieldValidator::dimensionValues($data['dimension_values']),
            idempotencyKey: $draft->id,
        );

        try {
            return $this->createTransaction->handle($input, $user);
        } catch (UniqueConstraintViolationException $exception) {
            $winner = $this->writtenFor($draft);

            if (! $winner instanceof Transaction) {
                throw $exception;
            }

            return $winner;
        }
    }

    /** The row this draft already produced, if a tap got there first. */
    private function writtenFor(EntryDraft $draft): ?Transaction
    {
        return Transaction::query()
            ->withTrashed()
            ->where('idempotency_key', $draft->id)
            ->with(['category', 'department', 'dimensionValues.dimension'])
            ->first();
    }

    /** @param array<string, mixed> $data */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }
}
