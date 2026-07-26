<?php

namespace App\Reports;

use App\Models\User;
use App\Support\TransactionFilters;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * @phpstan-import-type AggregateRow from TransactionReport
 *
 * @phpstan-type SummaryGroupRow array{key: string|null, label: string, currency: string, type: string, amount_minor: int, amount: string, count: int}
 */
class SummaryReport extends TransactionReport
{
    /**
     * Two aggregations over the same scoped and filtered rows: the totals per currency and
     * type, and the same numbers cut by the requested dimension of analysis. Because both
     * start from the same builder and no grouping drops a row, the groups of a currency
     * and type add up to that currency and type's total.
     *
     * @return array{totals: array<int, AggregateRow>, groups: array<int, SummaryGroupRow>}
     */
    public function build(User $viewer, TransactionFilters $filters, SummaryGrouping $grouping): array
    {
        $query = $this->scopedQuery($viewer, $filters);

        return [
            'totals' => $this->totals((clone $query)->toBase()),
            'groups' => $this->groups((clone $query)->toBase(), $grouping),
        ];
    }

    /** @return array<int, AggregateRow> */
    private function totals(Builder $query): array
    {
        $rows = $query
            ->select(['transactions.currency', 'transactions.type'])
            ->selectRaw(self::AGGREGATES)
            ->groupBy('transactions.currency', 'transactions.type')
            ->orderBy('transactions.currency')
            ->orderBy('transactions.type')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            /** @var stdClass $row */
            $totals[] = $this->amounts($row);
        }

        return $totals;
    }

    /** @return array<int, SummaryGroupRow> */
    private function groups(Builder $query, SummaryGrouping $grouping): array
    {
        $grouping->join($query);

        $keyColumn = $grouping->keyColumn();
        $labelColumn = $grouping->labelColumn();

        $rows = $query
            ->select([
                "{$keyColumn} as key",
                "{$labelColumn} as label",
                'transactions.currency',
                'transactions.type',
            ])
            ->selectRaw(self::AGGREGATES)
            ->groupBy($keyColumn, $labelColumn, 'transactions.currency', 'transactions.type')
            ->orderBy('transactions.currency')
            ->orderBy('transactions.type')
            ->orderByRaw('sum(transactions.amount_minor) desc')
            ->orderBy($keyColumn)
            ->get();

        $groups = [];

        foreach ($rows as $row) {
            /** @var stdClass $row */
            $groups[] = [
                'key' => $row->key === null ? null : (string) $row->key,
                'label' => (string) ($row->label ?? __('reports.unassigned')),
                ...$this->amounts($row),
            ];
        }

        return $groups;
    }
}
