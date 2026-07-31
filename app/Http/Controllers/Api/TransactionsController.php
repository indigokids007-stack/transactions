<?php

namespace App\Http\Controllers\Api;

use App\Actions\Transactions\CreateTransaction;
use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\TransactionFilterRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\TransactionRevisionResource;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionScope;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TransactionsController extends Controller
{
    /** How many rows one page of the ledger holds. */
    private const PER_PAGE = 50;

    public function __construct(
        private readonly CreateTransaction $createTransaction,
        private readonly UpdateTransaction $updateTransaction,
        private readonly DeleteTransaction $deleteTransaction,
    ) {}

    public function index(TransactionFilterRequest $request): AnonymousResourceCollection
    {
        $query = Transaction::query()->with(['category', 'user', 'department', 'dimensionValues.dimension']);

        TransactionScope::apply($query, $request->actor());
        $request->filters()->apply($query);

        $query->orderByDesc('transactions.occurred_on')->orderByDesc('transactions.id');

        return TransactionResource::collection($query->cursorPaginate(self::PER_PAGE));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $updated = $this->updateTransaction->handle(
            $transaction,
            $request->changes(),
            $request->submittedDimensionValues(),
            $request->actor(),
        );

        return $this->respond($updated, Response::HTTP_OK);
    }

    public function destroy(Request $request, Transaction $transaction): HttpResponse
    {
        Gate::authorize('delete', $transaction);

        /** @var User $actor */
        $actor = $request->user();

        $this->deleteTransaction->handle($transaction, $actor);

        return response()->noContent();
    }

    public function revisions(Transaction $transaction): AnonymousResourceCollection
    {
        $revisions = $transaction->revisions()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return TransactionRevisionResource::collection($revisions);
    }

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
