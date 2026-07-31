<?php

namespace App\Reports;

enum SummaryGroupBy: string
{
    case Category = 'category';
    case User = 'user';
    case Currency = 'currency';
    case Dimension = 'dimension';
}
