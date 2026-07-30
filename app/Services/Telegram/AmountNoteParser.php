<?php

namespace App\Services\Telegram;

use App\Support\Money;

/**
 * A message opens with an amount and the rest of it is the note. A dot or a comma is a
 * thousands separator, never a decimal point. A space separates nothing. A `k` suffix or a
 * magnitude word multiplies the amount and may carry one fractional part, so `1.5k` and
 * `1.5 ming` are both 1500.
 *
 * Every rule errs toward refusal: a refused message costs one retype, while a silently
 * wrong number corrupts the ledger with nobody noticing.
 */
class AmountNoteParser
{
    /** Digits one separator stands for, and so the width a short last group is padded to. */
    private const GROUP_SIZE = 3;

    /**
     * What multiplies an amount, mapped to the zeros it appends. `k` is the glued shorthand
     * for a thousand; the words are what people type in Uzbek and in Russian, and `30 ming`
     * is as ordinary as `30k`.
     */
    private const MAGNITUDES = [
        'k' => 3,
        'ming' => 3,
        'mingta' => 3,
        'тыс' => 3,
        'тысяч' => 3,
        'тысяча' => 3,
        'mln' => 6,
        'млн' => 6,
        'million' => 6,
        'millon' => 6,
        'миллион' => 6,
    ];

    /**
     * A remainder made only of digits, spaces and separators is the rest of a number typed
     * in pieces, never a note, so `100 000`, `100 00` and `1 2 3` refuse.
     */
    private const NUMERIC_REMAINDER = '/^[0-9., ]+$/';

    /**
     * A note whose first word is only digits is a number typed in pieces whose tail is being
     * read as a note: `100 00 obed` means 10000, and recording 100 with the note `00 obed`
     * loses two orders of magnitude. Two or more digits is always that shape, and so is a
     * bare zero, which counts nothing. One to nine stays a count, so `1000 2 kishi` and
     * `50000 2 kishi obed` still record. `50000 12 kishi` refusing is the accepted cost.
     */
    private const DIGIT_WORD_OPENS_NOTE = '/^(?:[0-9]{2,}|0)(?: |$)/';

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

        if (preg_match($this->tokenPattern(), $message, $matches) !== 1) {
            return null;
        }

        $note = trim($matches['remainder']);

        if (preg_match(self::NUMERIC_REMAINDER, $note) === 1) {
            return null;
        }

        if (preg_match(self::DIGIT_WORD_OPENS_NOTE, $note) === 1) {
            return null;
        }

        if (preg_match(self::GROUP_OPENS_REMAINDER, $note) === 1) {
            return null;
        }

        if ($this->magnitudeOpensNote($note)) {
            return null;
        }

        $amount = $this->amountFromToken($matches['token'], $matches['magnitude'] ?? '');

        if ($amount === null) {
            return null;
        }

        if ($this->noteOutweighs($note, $amount)) {
            return null;
        }

        return new ParsedEntry($amount, $note === '' ? null : $note);
    }

    /**
     * The amount token is a run of digits and separators, optionally multiplied by `k` glued
     * to it or by a magnitude word that may stand apart from it, and only a space or the end
     * of the message may follow, so `120000taksi` and `2mlnsom` refuse rather than split. The
     * remainder alternates with the empty string so the group always takes part in the match.
     */
    private function tokenPattern(): string
    {
        return '/^(?<token>[0-9.,]+)(?<magnitude>k| *(?:'.$this->magnitudeAlternation().'))?(?<remainder> .*|)$/iu';
    }

    /**
     * A note opening with a magnitude the token could not swallow is a magnitude this grammar
     * cannot read: `2 mln.`, `30 тыс.`, `30 minglab`, `50 mingga`. Recording the bare leading
     * count would be the very disaster the magnitudes exist to prevent, so it refuses. `k` is
     * left out on purpose, because notes legitimately open with `kishi`, `kg` and `kartoshka`.
     */
    private function magnitudeOpensNote(string $note): bool
    {
        return preg_match('/^(?:'.$this->magnitudeAlternation().')/iu', $note) === 1;
    }

    /**
     * Both magnitude rules are built from the one table, so a word can never be added to a
     * rule and forgotten in the other. Longest first, so the alternation reads the way it
     * matches rather than leaning on backtracking.
     */
    private function magnitudeAlternation(): string
    {
        $words = array_values(array_diff(array_keys(self::MAGNITUDES), ['k']));

        usort($words, static fn (string $word, string $other): int => strlen($other) <=> strlen($word));

        return implode('|', $words);
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

    private function amountFromToken(string $token, string $magnitude): ?string
    {
        $zeros = self::MAGNITUDES[mb_strtolower(trim($magnitude))] ?? 0;
        $groups = explode('.', str_replace(',', '.', $token));

        $amount = $zeros === 0
            ? $this->amountFromGroups($groups)
            : $this->amountFromMagnitude($groups, $zeros);

        if ($amount === null) {
            return null;
        }

        return $this->boundedAmount($amount);
    }

    /**
     * `100k`, `1.5k`, `30 ming`, `1.5 mln`. The magnitude carries the multiplication, so at
     * most one separator may follow the whole part, holding a fraction of the magnitude that
     * is padded out to its zeros. The digit string is built by concatenation, never by float
     * arithmetic, and the bound is applied to the built string, so `999999999999 mln`
     * refuses for its width instead of overflowing.
     *
     * @param  list<string>  $groups
     */
    private function amountFromMagnitude(array $groups, int $zeros): ?string
    {
        if (count($groups) > 2) {
            return null;
        }

        $whole = $groups[0];
        $fraction = $groups[1] ?? '';

        if (! $this->isDigitRun($whole)) {
            return null;
        }

        if ($fraction !== '' && (! $this->isDigitRun($fraction) || strlen($fraction) > $zeros)) {
            return null;
        }

        return $whole.str_pad($fraction, $zeros, '0');
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

    /**
     * `2 kishi 30000` is a message written the wrong way round: the leading count lands in
     * the ledger and the real amount sits in the note. Any number in the note larger than
     * the amount is that shape, so it refuses and the bot's amount-first hint does the
     * teaching, exactly as it already does for `taksi 120000`. Hunting for the real amount
     * inside the message would be guessing at intent.
     */
    private function noteOutweighs(string $note, string $amount): bool
    {
        preg_match_all('/[0-9]+/', $note, $matches);

        foreach ($matches[0] as $number) {
            if ($this->exceeds($number, $amount)) {
                return true;
            }
        }

        return false;
    }

    /** Compares two digit strings by width and then lexically, so no integer bound applies. */
    private function exceeds(string $digits, string $amount): bool
    {
        $candidate = ltrim($digits, '0');

        if (strlen($candidate) !== strlen($amount)) {
            return strlen($candidate) > strlen($amount);
        }

        return strcmp($candidate, $amount) > 0;
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
