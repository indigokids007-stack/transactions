<?php

namespace App\Reports;

use App\Models\Dimension;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Str;

/**
 * The dimension of analysis a caller asked for, resolved once and never again. A key that
 * names no dimension resolves to nothing at all, and `dimension:<key>` becomes an integer
 * id here, before anything the client typed could reach a query.
 */
readonly class SummaryGrouping
{
    public const DEFAULT = SummaryGroupBy::Category->value;

    private const DIMENSION_PREFIX = 'dimension:';

    private function __construct(
        public SummaryGroupBy $groupBy,
        private ?int $dimensionId = null,
    ) {}

    public static function resolve(string $groupBy): ?self
    {
        if (str_starts_with($groupBy, self::DIMENSION_PREFIX)) {
            return self::forDimensionKey(Str::after($groupBy, self::DIMENSION_PREFIX));
        }

        $resolved = SummaryGroupBy::tryFrom($groupBy);

        if ($resolved === null || $resolved === SummaryGroupBy::Dimension) {
            return null;
        }

        return new self($resolved);
    }

    /** The column the rows are grouped on, and the key each group is reported under. */
    public function keyColumn(): string
    {
        return match ($this->groupBy) {
            SummaryGroupBy::Category => 'categories.id',
            SummaryGroupBy::User => 'users.id',
            SummaryGroupBy::Currency => 'transactions.currency',
            SummaryGroupBy::Dimension => 'dimension_values.id',
        };
    }

    public function labelColumn(): string
    {
        return match ($this->groupBy) {
            SummaryGroupBy::Category => 'categories.name',
            SummaryGroupBy::User => 'users.name',
            SummaryGroupBy::Currency => 'transactions.currency',
            SummaryGroupBy::Dimension => 'dimension_values.name',
        };
    }

    public function join(Builder $query): void
    {
        match ($this->groupBy) {
            SummaryGroupBy::Category => $query->join('categories', 'categories.id', '=', 'transactions.category_id'),
            SummaryGroupBy::User => $query->join('users', 'users.id', '=', 'transactions.user_id'),
            SummaryGroupBy::Currency => $query,
            SummaryGroupBy::Dimension => $this->joinDimension($query),
        };
    }

    /**
     * The pivot is restricted to one dimension inside the join condition, and its primary
     * key holds one row per transaction and dimension. A transaction carrying values for
     * several dimensions therefore still contributes exactly one row, and one that carries
     * no value for this dimension survives the left join into the unassigned bucket, so
     * the groups still add up to the totals.
     */
    private function joinDimension(Builder $query): Builder
    {
        return $query
            ->leftJoin('transaction_dimension_values', fn (JoinClause $join) => $join
                ->on('transaction_dimension_values.transaction_id', '=', 'transactions.id')
                ->where('transaction_dimension_values.dimension_id', $this->dimensionId))
            ->leftJoin('dimension_values', 'dimension_values.id', '=', 'transaction_dimension_values.dimension_value_id');
    }

    private static function forDimensionKey(string $key): ?self
    {
        $dimensionId = Dimension::query()->where('key', $key)->value('id');

        if ($dimensionId === null) {
            return null;
        }

        return new self(SummaryGroupBy::Dimension, (int) $dimensionId);
    }
}
