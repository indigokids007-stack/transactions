<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionScope;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return TransactionScope::apply(Transaction::query()->whereKey($transaction->getKey()), $user)->exists();
    }

    /**
     * Writing is narrower than reading, and deliberately not derived from it. An owner
     * sees the whole ledger and may not touch any of it; a manager sees a department
     * and may still only edit what they wrote themselves.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Owner) {
            return false;
        }

        return $transaction->user_id === $user->id;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
