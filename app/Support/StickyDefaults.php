<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\User;

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
                'type' => null,
                'currency' => config('money.default'),
                'category_id' => null,
                'dimension_values' => [],
            ];
        }

        return [
            'type' => $transaction->type->value,
            'currency' => $transaction->currency,
            'category_id' => $transaction->category_id,
            'dimension_values' => $transaction->dimensionValues
                ->mapWithKeys(fn ($value) => [$value->pivot->getAttribute('dimension_id') => $value->id])
                ->all(),
        ];
    }
}
