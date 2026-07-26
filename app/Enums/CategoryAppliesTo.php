<?php

namespace App\Enums;

enum CategoryAppliesTo: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Both = 'both';
}
