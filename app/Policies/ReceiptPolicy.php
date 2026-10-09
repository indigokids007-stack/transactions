<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionScope;

class ReceiptPolicy
{
    public function view(User $user, Receipt $receipt): bool
    {
        return $receipt->user_id === $user->id || $user->canSeeEverything()
            || TransactionScope::apply(Transaction::query()->where('receipt_id', $receipt->id), $user)->exists();
    }
}
