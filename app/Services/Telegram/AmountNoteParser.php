<?php

namespace App\Services\Telegram;

use App\Support\Money;

class AmountNoteParser
{
    /**
     * A message opens with an amount and the rest of it is the note.
     *
     * A dot or a comma is a thousands separator, never a decimal point. One separator may
     * be followed by a short group, which is padded out to three digits, so `12.50` is
     * twelve and a half thousand. Two or more separators mean the number is already
     * grouped, and then every group after the first has to be a full three digits, so
     * `1.000,00` is refused rather than read as a million. A `k` suffix says the same
     * thing another way, and there the fraction is decimal, so `1.5k` is 1500.
     *
     * A space separates nothing, so what follows the amount is the note, digits included:
     * `1000 2 kishi` is a thousand with the note `2 kishi`. A note that is only digits and
     * spaces is not a note but the rest of a number typed with spaces, and `100 000` is
     * refused rather than recorded as a hundred.
     */
    private const PATTERN = '/^\s*(?<digits>\d[\d.,]*)(?<suffix>[kK]?)(?<note>\s.*|)$/su';

    /** Digits one separator stands for, and so the width a short last group is padded to. */
    private const GROUP_SIZE = 3;

    /** A "note" made only of digits and spaces is the tail of a number, not a note. */
    private const NUMERIC_NOTE = '/^[\d\s.,]+$/u';

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

        if ($note !== '' && preg_match(self::NUMERIC_NOTE, $note) === 1) {
            return null;
        }

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
     * `100.000`, `1.250.000`, `12.50`.
     *
     * @param  list<string>  $groups
     */
    private function fromGroups(array $groups): ?string
    {
        $leading = array_shift($groups);

        if ($leading === null || $this->group($leading) === null) {
            return null;
        }

        // One separator, so a short group is shorthand and is padded out: `12.50` is 12500.
        if (count($groups) === 1) {
            return $this->group($groups[0]) === null
                ? null
                : $leading.str_pad($groups[0], self::GROUP_SIZE, '0');
        }

        // More than one separator, so the number is already written in groups. A short
        // group here means the message is in some other format, and padding it would turn
        // the European `1.000,00` into a million.
        foreach ($groups as $group) {
            if (strlen($group) !== self::GROUP_SIZE || $this->digits($group) === null) {
                return null;
            }
        }

        return $leading.implode('', $groups);
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
