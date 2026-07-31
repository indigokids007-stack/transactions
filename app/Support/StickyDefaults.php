<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Models\User;
use App\Validation\TransactionFieldValidator;

class StickyDefaults
{
    /** @return array<string, mixed> */
    public static function for(User $user): array
    {
        $transaction = Transaction::where('user_id', $user->id)
            ->latest('id')
            ->with('dimensionValues')
            ->first();

        if (! $transaction instanceof Transaction) {
            return [
                'type' => TransactionType::Expense->value,
                'currency' => config('money.default'),
                'category_id' => null,
                'dimension_values' => (object) [],
            ];
        }

        $lastUsed = TransactionFieldValidator::dimensionValues(
            $transaction->dimensionValues
                ->mapWithKeys(fn (DimensionValue $value) => [$value->pivot->getAttribute('dimension_id') => $value->id])
                ->all()
        );

        // Only what is still choosable is carried forward. A value the admin has since
        // retired, or one whose whole dimension was switched off, would otherwise be
        // copied into every new draft this person starts, and every one of those drafts
        // would refuse to save with no button anywhere to change it.
        $dimensionValues = TransactionFieldValidator::activeDimensionValues($lastUsed);

        return [
            'type' => $transaction->type->value,
            'currency' => $transaction->currency,
            'category_id' => $transaction->category_id,
            'dimension_values' => $dimensionValues === [] ? (object) [] : $dimensionValues,
        ];
    }
}
