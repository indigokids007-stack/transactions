<?php

namespace App\Actions\Drafts;

use App\Models\Category;
use App\Models\EntryDraft;
use App\Models\User;
use App\Services\Telegram\ParsedEntry;
use App\Support\StickyDefaults;
use App\Validation\TransactionFieldValidator;
use Illuminate\Support\Str;

/**
 * A parsed message becomes a draft and nothing else. Everything the transaction still
 * needs comes from the sticky defaults, so the staff member only ever types the amount,
 * and the row is written later, on confirmation.
 */
class StartDraft
{
    /** How long a preview stays tappable. */
    private const LIFETIME_IN_MINUTES = 60;

    public function handle(User $user, ParsedEntry $entry): EntryDraft
    {
        $defaults = StickyDefaults::for($user);

        return EntryDraft::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'payload' => [
                'type' => $defaults['type'],
                'amount' => $entry->amount,
                'currency' => $defaults['currency'],
                'occurred_on' => now()->toDateString(),
                'category_id' => $this->categoryId($defaults['category_id'] ?? null),
                'note' => $entry->note,
                'dimension_values' => TransactionFieldValidator::dimensionValues($defaults['dimension_values'] ?? null),
            ],
            'expires_at' => now()->addMinutes(self::LIFETIME_IN_MINUTES),
        ]);
    }

    /**
     * The category the last entry used, as long as it is still active. A staff member
     * with no history, or whose last category has since been retired, opens on the first
     * category instead and can change it from the preview.
     */
    private function categoryId(mixed $sticky): ?int
    {
        $query = Category::query()->where('is_active', true);

        if (is_numeric($sticky) && (clone $query)->whereKey((int) $sticky)->exists()) {
            return (int) $sticky;
        }

        $first = $query->orderBy('sort')->orderBy('name')->value('id');

        return is_numeric($first) ? (int) $first : null;
    }
}
