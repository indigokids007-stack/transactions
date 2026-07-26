<?php

namespace App\Reports;

use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use App\Support\TransactionFilters;
use App\Support\TransactionScope;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

/**
 * What every report shares: where its rows come from, and how one aggregated row is
 * turned into the wire shape. The composition below is the security-critical part, so it
 * lives here once instead of in each report.
 *
 * @phpstan-type AggregateRow array{currency: string, type: string, amount_minor: int, amount: string, count: int}
 */
abstract class TransactionReport
{
    /**
     * The aggregation itself, run by the database. Loading rows to add them up in PHP
     * would both be slower and put the total outside the query the scope constrains.
     */
    protected const AGGREGATES = 'sum(transactions.amount_minor) as amount_minor, count(*) as count';

    /**
     * The authorization boundary first, the caller's filters on top of it: a filter only
     * ever removes rows from what the viewer may already see, so a client supplied
     * `user_id` or `department_id` cannot widen the report.
     *
     * @return Builder<Transaction>
     */
    protected function scopedQuery(User $viewer, TransactionFilters $filters): Builder
    {
        $query = Transaction::query();

        TransactionScope::apply($query, $viewer);

        return $filters->apply($query);
    }

    /**
     * Currency travels with every amount and never leaves it. There is no row anywhere in
     * a report that holds a sum over more than one currency.
     *
     * @return AggregateRow
     */
    protected function amounts(stdClass $row): array
    {
        $currency = (string) $row->currency;
        $amountMinor = (int) $row->amount_minor;

        return [
            'currency' => $currency,
            'type' => (string) $row->type,
            'amount_minor' => $amountMinor,
            'amount' => Money::toDecimal($amountMinor, $currency),
            'count' => (int) $row->count,
        ];
    }
}
