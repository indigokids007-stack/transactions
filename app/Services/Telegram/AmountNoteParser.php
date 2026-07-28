<?php

namespace App\Services\Telegram;

use App\Support\Money;

/**
 * A message opens with an amount and the rest of it is the note. A dot or a comma is a
 * thousands separator, never a decimal point. A space separates nothing. A `k` suffix
 * multiplies by a thousand and may carry one fractional part, so `1.5k` is 1500.
 *
 * Every rule errs toward refusal: a refused message costs one retype, while a silently
 * wrong number corrupts the ledger with nobody noticing.
 */
class AmountNoteParser
{
    /** Digits one separator stands for, and so the width a short last group is padded to. */
    private const GROUP_SIZE = 3;

    /**
     * The amount token is a run of digits and separators with an optional `k`, and only a
     * space or the end of the message may follow it, so `120000taksi` and `120'000` refuse
     * rather than split. The remainder alternates with the empty string so the group always
     * takes part in the match.
     */
    private const TOKEN_PATTERN = '/^(?<token>[0-9.,]+[kK]?)(?<remainder> .*|)$/';

    /**
     * A remainder made only of digits, spaces and separators is the rest of a number typed
     * in pieces, never a note, so `100 000`, `100 00` and `1 2 3` refuse.
     */
    private const NUMERIC_REMAINDER = '/^[0-9., ]+$/';

    /**
     * A remainder that opens with three consecutive digits is the rest of an amount typed
     * with spaces, whatever follows the digits: `50 000so'm`, `50 000-taksi`, `50 000k`.
     * Reading it as a note records a thousandth of what was meant. A genuine note that
     * opens with three digits, like `500km yo'l`, refuses with them; that trade is
     * deliberate, because nothing distinguishes it from a spaced group.
     */
    private const GROUP_OPENS_REMAINDER = '/^[0-9]{3}/';

    public function parse(string $text): ?ParsedEntry
    {
        $message = trim($this->flattenWhitespace($text));

        if (preg_match(self::TOKEN_PATTERN, $message, $matches) !== 1) {
            return null;
        }

        $note = trim($matches['remainder']);

        if (preg_match(self::NUMERIC_REMAINDER, $note) === 1) {
            return null;
        }

        if (preg_match(self::GROUP_OPENS_REMAINDER, $note) === 1) {
            return null;
        }

        $amount = $this->amountFromToken($matches['token']);

        if ($amount === null) {
            return null;
        }

        return new ParsedEntry($amount, $note === '' ? null : $note);
    }

    /**
     * Every Unicode whitespace character becomes an ASCII space before any other rule
     * runs, so no later rule can meet an invisible character. Bank apps and spreadsheets
     * paste amounts with U+00A0, U+202F, U+2009 or U+2007 as thousands separators, and
     * those must refuse exactly the way `100 000` refuses.
     */
    private function flattenWhitespace(string $text): string
    {
        return (string) preg_replace('/[\s\p{Z}]/u', ' ', $text);
    }

    private function amountFromToken(string $token): ?string
    {
        $suffixed = str_ends_with($token, 'k') || str_ends_with($token, 'K');
        $digits = $suffixed ? substr($token, 0, -1) : $token;
        $groups = explode('.', str_replace(',', '.', $digits));

        $amount = $suffixed
            ? $this->amountFromSuffix($groups)
            : $this->amountFromGroups($groups);

        if ($amount === null) {
            return null;
        }

        return $this->boundedAmount($amount);
    }

    /**
     * `100k`, `1.5k`. The suffix carries the multiplication by a thousand, so at most one
     * separator may follow it, holding a fraction of a thousand that is padded out to its
     * three digits. The digit string is built by concatenation, never by float arithmetic.
     *
     * @param  list<string>  $groups
     */
    private function amountFromSuffix(array $groups): ?string
    {
        if (count($groups) > 2) {
            return null;
        }

        $whole = $groups[0];
        $fraction = $groups[1] ?? '';

        if (! $this->isDigitRun($whole)) {
            return null;
        }

        if ($fraction !== '' && ! $this->isGroup($fraction)) {
            return null;
        }

        return $whole.str_pad($fraction, self::GROUP_SIZE, '0');
    }

    /**
     * `100000`, `100.000`, `12.50`, `1.250.000`. With exactly one separator a short final
     * group is shorthand and is padded out, so `12.50` is 12500. With two or more the
     * number is already written in groups and every group after the first must be a full
     * three digits, otherwise padding would turn the European `1.000,00` into a million.
     *
     * @param  list<string>  $groups
     */
    private function amountFromGroups(array $groups): ?string
    {
        $leading = $groups[0];
        $trailing = array_slice($groups, 1);

        if ($trailing === []) {
            return $this->isDigitRun($leading) ? $leading : null;
        }

        if (! $this->isGroup($leading)) {
            return null;
        }

        if (count($trailing) === 1) {
            return $this->isGroup($trailing[0])
                ? $leading.str_pad($trailing[0], self::GROUP_SIZE, '0')
                : null;
        }

        foreach ($trailing as $group) {
            if (! $this->isDigitRun($group)) {
                return null;
            }

            if (strlen($group) !== self::GROUP_SIZE) {
                return null;
            }
        }

        return $leading.implode('', $trailing);
    }

    /**
     * The ledger bound, applied to the fully built digit string, so `999999999999999k`
     * refuses for its width instead of overflowing after multiplication.
     */
    private function boundedAmount(string $digits): ?string
    {
        $amount = ltrim($digits, '0');

        if ($amount === '') {
            return null;
        }

        if (preg_match(Money::AMOUNT_PATTERN, $amount) !== 1) {
            return null;
        }

        return $amount;
    }

    private function isDigitRun(string $value): bool
    {
        return preg_match('/^[0-9]+$/', $value) === 1;
    }

    /** One group of a separated amount: one to three digits. */
    private function isGroup(string $value): bool
    {
        if (strlen($value) > self::GROUP_SIZE) {
            return false;
        }

        return $this->isDigitRun($value);
    }
}
