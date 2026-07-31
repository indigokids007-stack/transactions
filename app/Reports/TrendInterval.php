<?php

namespace App\Reports;

/**
 * The whitelist of periods a trend may be cut into. The report reads the interval from a
 * case of this enum, never from what the client sent, which is what keeps the value
 * `date_trunc` receives out of the client's reach.
 */
enum TrendInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
}
