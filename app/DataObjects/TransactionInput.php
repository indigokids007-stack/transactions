<?php

namespace App\DataObjects;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;

readonly class TransactionInput
{
    /** @param  array<int, int>  $dimensionValues */
    public function __construct(
        public int $userId,
        public TransactionType $type,
        public string $amount,
        public string $currency,
        public CarbonImmutable $occurredOn,
        public int $categoryId,
        public ?string $note = null,
        public array $dimensionValues = [],
        public ?string $idempotencyKey = null,
    ) {}
}
