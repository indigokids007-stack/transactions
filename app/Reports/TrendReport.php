<?php

namespace App\Reports;

use App\Models\User;
use App\Support\TransactionFilters;
use Carbon\CarbonImmutable;
use stdClass;

/**
 * @phpstan-import-type AggregateRow from TransactionReport
 *
 * @phpstan-type TrendPoint array{period: string, currency: string, type: string, amount_minor: int, amount: string, count: int}
 */
class TrendReport extends TransactionReport
{
    /**
     * One point per period, currency and type, cut by the database. The interval reaches
     * the query as a bound parameter holding an enum case's value, so nothing the client
     * typed is ever spliced into the statement.
     *
     * @return array{points: array<int, TrendPoint>}
     */
    public function build(User $viewer, TransactionFilters $filters, TrendInterval $interval): array
    {
        $rows = $this->scopedQuery($viewer, $filters)
            ->toBase()
            ->selectRaw(
                'date_trunc(cast(? as text), cast(transactions.occurred_on as timestamp)) as period',
                [$interval->value],
            )
            ->addSelect(['transactions.currency', 'transactions.type'])
            ->selectRaw(self::AGGREGATES)
            ->groupBy('period', 'transactions.currency', 'transactions.type')
            ->orderBy('period')
            ->orderBy('transactions.currency')
            ->orderBy('transactions.type')
            ->get();

        $points = [];

        foreach ($rows as $row) {
            /** @var stdClass $row */
            $points[] = [
                'period' => CarbonImmutable::parse((string) $row->period)->toDateString(),
                ...$this->amounts($row),
            ];
        }

        return ['points' => $points];
    }
}
