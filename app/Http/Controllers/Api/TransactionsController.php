<?php

namespace App\Http\Controllers\Api;

use App\Actions\Transactions\CreateTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TransactionsController extends Controller
{
    public function __construct(private readonly CreateTransaction $createTransaction) {}

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $actor = $request->actor();
        $idempotencyKey = $request->idempotencyKey();
        $existing = $this->findByIdempotencyKey($idempotencyKey, $actor);

        if ($existing instanceof Transaction) {
            return $this->respond($existing, Response::HTTP_OK);
        }

        try {
            $transaction = $this->createTransaction->handle($request->toInput($actor), $actor);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->resolveRace($idempotencyKey, $actor, $exception);
        }

        return $this->respond($transaction, Response::HTTP_CREATED);
    }

    private function findByIdempotencyKey(?string $idempotencyKey, User $actor): ?Transaction
    {
        if ($idempotencyKey === null) {
            return null;
        }

        return Transaction::query()
            ->withTrashed()
            ->where('idempotency_key', $idempotencyKey)
            ->where('created_by', $actor->id)
            ->with(['category', 'user', 'department', 'dimensionValues.dimension'])
            ->first();
    }

    private function resolveRace(
        ?string $idempotencyKey,
        User $actor,
        UniqueConstraintViolationException $exception,
    ): JsonResponse {
        if ($idempotencyKey === null) {
            throw $exception;
        }

        $winner = $this->findByIdempotencyKey($idempotencyKey, $actor);

        if ($winner instanceof Transaction) {
            return $this->respond($winner, Response::HTTP_OK);
        }

        throw ValidationException::withMessages([
            'idempotency_key' => __('errors.idempotency_key_taken'),
        ]);
    }

    private function respond(Transaction $transaction, int $status): JsonResponse
    {
        return TransactionResource::make($transaction)->response()->setStatusCode($status);
    }
}
