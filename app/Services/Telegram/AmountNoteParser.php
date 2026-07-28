<?php

namespace App\Services\Telegram;

use App\Support\Money;

class AmountNoteParser
{
    /**
     * A message opens with an amount and the rest of it is the note.
     *
     * A dot or a comma is a thousands separator, never a decimal point: `12.50` is twelve
     * and a half thousand, because the last group is padded out to three digits, so a
     * typed amount always lands on a whole som. A `k` suffix says the same thing another
     * way, and there the fraction is decimal, so `1.5k` is 1500. A space separates
     * nothing: `100 000` does not parse at all, because reading it as 100 with the note
     * `000` would record a thousandth of what the person meant.
     */
    private const PATTERN = '/^\s*(?<digits>\d[\d.,]*)(?<suffix>[kK]?)(?<note>\s+\D.*|\s*)$/su';

    /** Digits one separator stands for, and so the width the last group is padded to. */
    private const GROUP_SIZE = 3;

    public function parse(string $text): ?ParsedEntry
    {
        if (preg_match(self::PATTERN, $text, $matches) !== 1) {
            return null;
        }

        $amount = $this->amount($matches['digits'], $matches['suffix'] !== '');

        if ($amount === null || preg_match(Money::AMOUNT_PATTERN, $amount) !== 1 || (float) $amount <= 0) {
            return null;
        }

        $note = trim($matches['note']);

        return new ParsedEntry($amount, $note === '' ? null : $note);
    }

    /** The amount in whole units, or null when the groups are not a shape anyone means. */
    private function amount(string $digits, bool $thousands): ?string
    {
        $groups = explode('.', str_replace(',', '.', $digits));

        if ($thousands) {
            return $this->fromSuffix($groups);
        }

        if (count($groups) === 1) {
            return $this->digits($groups[0]);
        }

        return $this->fromGroups($groups);
    }

    /**
     * `100k`, `1.5k`. The suffix carries the multiplication, so what follows the separator
     * is a decimal fraction of a thousand and there can only be one of them.
     *
     * @param  list<string>  $groups
     */
    private function fromSuffix(array $groups): ?string
    {
        if (count($groups) > 2) {
            return null;
        }

        $whole = $this->digits($groups[0]);
        $fraction = $groups[1] ?? '';

        if ($whole === null || ($fraction !== '' && $this->group($fraction) === null)) {
            return null;
        }

        return $whole.str_pad($fraction, self::GROUP_SIZE, '0');
    }

    /**
     * `100.000`, `1.250.000`, `12.50`. Every group between the first separator and the
     * last is a full group, and the last one is padded out, so `12.50` reads as 12500 the
     * way `12.5k` does.
     *
     * @param  list<string>  $groups
     */
    private function fromGroups(array $groups): ?string
    {
        $last = array_pop($groups);

        if ($last === null || $this->group($last) === null) {
            return null;
        }

        $leading = array_shift($groups);

        if ($leading === null || $this->group($leading) === null) {
            return null;
        }

        foreach ($groups as $middle) {
            if ($this->group($middle) === null || strlen($middle) !== self::GROUP_SIZE) {
                return null;
            }
        }

        return $leading.implode('', $groups).str_pad($last, self::GROUP_SIZE, '0');
    }

    /** One group of a separated amount: digits, and never wider than a group. */
    private function group(string $group): ?string
    {
        return strlen($group) > self::GROUP_SIZE ? null : $this->digits($group);
    }

    private function digits(string $value): ?string
    {
        return preg_match('/^\d+$/', $value) === 1 ? $value : null;
    }
}
