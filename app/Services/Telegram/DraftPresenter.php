<?php

namespace App\Services\Telegram;

use App\Models\Category;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\EntryDraft;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Lang;

/**
 * Turns a draft into the message the staff member reads and the buttons they tap. Every
 * string comes from `lang/<locale>/bot.php`; a locale without that file falls back to
 * Uzbek so a user whose Telegram is set to another language still reads sentences.
 */
class DraftPresenter
{
    private const FALLBACK_LOCALE = 'uz';

    /** How many choice buttons one row holds. */
    private const BUTTONS_PER_ROW = 2;

    /** Enough choices to pick from without sending Telegram an unusable wall of buttons. */
    private const MAX_BUTTONS = 30;

    public function preview(EntryDraft $draft, User $user): string
    {
        /** @var array<string, mixed> $payload */
        $payload = $draft->payload;
        $currency = is_string($payload['currency'] ?? null) ? $payload['currency'] : (string) config('money.default');
        $categoryId = is_numeric($payload['category_id'] ?? null) ? (int) $payload['category_id'] : null;
        $note = is_string($payload['note'] ?? null) ? $payload['note'] : null;

        return $this->render($user, is_string($payload['type'] ?? null) ? $payload['type'] : '', [
            'amount' => $this->amount((string) ($payload['amount'] ?? '0'), $currency),
            'category' => $categoryId === null ? null : Category::find($categoryId)?->name,
            'department' => $user->department?->name,
            'date' => $this->date((string) ($payload['occurred_on'] ?? '')),
            'note' => $note,
        ]);
    }

    public function saved(Transaction $transaction, User $user): string
    {
        $summary = $this->render($user, $transaction->type->value, [
            'amount' => $this->amount(
                Money::toDecimal($transaction->amount_minor, $transaction->currency),
                $transaction->currency,
            ),
            'category' => $transaction->category->name,
            'department' => $transaction->department?->name,
            'date' => $transaction->occurred_on->format('d.m.Y'),
            'note' => $transaction->note,
        ]);

        return $this->line('saved', $user)."\n".$summary;
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    public function keyboard(EntryDraft $draft, User $user): array
    {
        $rows = [
            [$this->button($this->line('confirm', $user), DraftCallback::build($draft, DraftCallback::CONFIRM))],
            [$this->button($this->line('change_category', $user), DraftCallback::build($draft, DraftCallback::CHOOSE_CATEGORY))],
        ];

        $miniAppUrl = config('services.telegram.mini_app_url');

        if (is_string($miniAppUrl) && str_starts_with($miniAppUrl, 'https://')) {
            $rows[] = [['text' => $this->line('open_app', $user), 'web_app' => ['url' => $miniAppUrl]]];
        }

        $rows[] = [$this->button($this->line('cancel', $user), DraftCallback::build($draft, DraftCallback::CANCEL))];

        return $rows;
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    public function categoryKeyboard(EntryDraft $draft, User $user): array
    {
        $buttons = Category::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->limit(self::MAX_BUTTONS)
            ->get()
            ->map(fn (Category $category) => $this->button(
                $category->name,
                DraftCallback::build($draft, DraftCallback::SET_CATEGORY, $category->id),
            ))
            ->all();

        return $this->rows($buttons, $draft, $user);
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    public function dimensionKeyboard(EntryDraft $draft, Dimension $dimension, User $user): array
    {
        $buttons = $dimension->values()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->limit(self::MAX_BUTTONS)
            ->get()
            ->map(fn (DimensionValue $value) => $this->button(
                $value->name,
                DraftCallback::build($draft, DraftCallback::SET_DIMENSION, $dimension->id, $value->id),
            ))
            ->all();

        return $this->rows($buttons, $draft, $user);
    }

    /** @param array<string, mixed> $replace */
    public function line(string $key, ?User $user = null, array $replace = []): string
    {
        $line = Lang::get("bot.{$key}", $replace, $this->locale($user));

        return is_string($line) ? $line : $key;
    }

    /**
     * @param  array<string, string|null>  $fields
     */
    private function render(User $user, string $type, array $fields): string
    {
        $lines = [$this->line("type.{$type}", $user)];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $lines[] = $this->line($key, $user).': '.$value;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $buttons
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function rows(array $buttons, EntryDraft $draft, User $user): array
    {
        $rows = array_chunk($buttons, self::BUTTONS_PER_ROW);

        $rows[] = [$this->button($this->line('cancel', $user), DraftCallback::build($draft, DraftCallback::CANCEL))];

        return $rows;
    }

    /** @return array<string, mixed> */
    private function button(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    private function amount(string $amount, string $currency): string
    {
        $exponent = Money::isSupported($currency) ? Money::exponent($currency) : 0;

        return number_format((float) $amount, $exponent, ',', ' ').' '.$currency;
    }

    private function date(string $date): ?string
    {
        if ($date === '') {
            return null;
        }

        return CarbonImmutable::parse($date)->format('d.m.Y');
    }

    private function locale(?User $user): string
    {
        $locale = $user?->locale;

        if (is_string($locale) && $locale !== '' && Lang::has('bot.saved', $locale)) {
            return $locale;
        }

        return self::FALLBACK_LOCALE;
    }
}
