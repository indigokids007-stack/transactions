<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The list filters, expressed as builder constraints and nothing else, so the same
 * object drives the list endpoint, the reports and the export.
 */
readonly class TransactionFilters
{
    /** @param  array<int, int>  $dimensions  dimension id => dimension value id */
    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?TransactionType $type = null,
        public ?int $categoryId = null,
        public ?string $currency = null,
        public ?int $userId = null,
        public ?int $departmentId = null,
        public array $dimensions = [],
    ) {}

    /**
     * Every constraint here narrows. A filter is applied on top of the caller's scope
     * and can only ever remove rows from it, which is what keeps a client supplied
     * `user_id` or `department_id` from reaching anything the caller may not see.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function apply(Builder $query): Builder
    {
        if ($this->from instanceof CarbonImmutable) {
            $query->where('transactions.occurred_on', '>=', $this->from->toDateString());
        }

        if ($this->to instanceof CarbonImmutable) {
            $query->where('transactions.occurred_on', '<=', $this->to->toDateString());
        }

        if ($this->type instanceof TransactionType) {
            $query->where('transactions.type', $this->type);
        }

        if ($this->categoryId !== null) {
            $query->whereIn('transactions.category_id', $this->categoryBranch($this->categoryId));
        }

        if ($this->currency !== null) {
            $query->where('transactions.currency', $this->currency);
        }

        if ($this->userId !== null) {
            $query->where('transactions.user_id', $this->userId);
        }

        if ($this->departmentId !== null) {
            $query->where('transactions.department_id', $this->departmentId);
        }

        foreach ($this->dimensions as $dimensionId => $valueId) {
            $query->whereHas(
                'dimensionValues',
                fn (Builder $values) => $values
                    ->where('transaction_dimension_values.dimension_id', $dimensionId)
                    ->whereKey($valueId)
            );
        }

        return $query;
    }

    /**
     * Picking a parent category means picking the branch under it, otherwise a filter
     * on a grouping category would silently return nothing.
     *
     * @return array<int, int>
     */
    private function categoryBranch(int $categoryId): array
    {
        $children = [];

        foreach (Category::query()->pluck('parent_id', 'id') as $id => $parentId) {
            if ($parentId !== null) {
                $children[(int) $parentId][] = (int) $id;
            }
        }

        $branch = [];
        $pending = [$categoryId];

        while ($pending !== []) {
            $current = (int) array_pop($pending);

            if (in_array($current, $branch, true)) {
                continue;
            }

            $branch[] = $current;
            $pending = array_merge($pending, $children[$current] ?? []);
        }

        return $branch;
    }
}
