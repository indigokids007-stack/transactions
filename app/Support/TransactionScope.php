<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TransactionScope
{
    /**
     * The single authorization boundary for reading transactions. It is expressed as a
     * constraint on the query, never as a filter on a loaded collection, so that a
     * record outside the scope is invisible to counts, aggregates and route model
     * binding alike.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public static function apply(Builder $query, User $viewer): Builder
    {
        if ($viewer->canSeeEverything()) {
            return $query;
        }

        if ($viewer->role === UserRole::Manager) {
            $departmentIds = $viewer->managedDepartments()->pluck('departments.id');

            return $query->whereIn('transactions.department_id', $departmentIds);
        }

        return $query->where('transactions.user_id', $viewer->id);
    }
}
