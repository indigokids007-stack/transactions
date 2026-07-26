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
     * Each branch is wrapped in its own group, so whatever a caller composes on top —
     * a filter today, a report or an export tomorrow — lands beside the boundary
     * instead of inside it. Without the group the manager's `or` would swallow any
     * following `and`, since `and` binds tighter in SQL.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public static function apply(Builder $query, User $viewer): Builder
    {
        if ($viewer->canSeeEverything()) {
            return $query;
        }

        // A manager reads the departments they manage and, on top of that, whatever they
        // wrote themselves: a record they authored stays reachable even when it is
        // charged to a department outside their remit. Writing is not widened with it.
        if ($viewer->role === UserRole::Manager) {
            $departmentIds = $viewer->managedDepartments()->pluck('departments.id');

            return $query->where(fn (Builder $scoped) => $scoped
                ->whereIn('transactions.department_id', $departmentIds)
                ->orWhere('transactions.user_id', $viewer->id));
        }

        return $query->where(fn (Builder $scoped) => $scoped->where('transactions.user_id', $viewer->id));
    }
}
