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
     * What multiplies an amount, mapped to the zeros it appends. `k` is the shorthand for a
     * thousand; the words are what people type in Uzbek and in Russian, in Latin and in
     * Cyrillic, spelt well and spelt badly, and `30 ming` is as ordinary as `30k`. Every
     * missing spelling is a live thousandfold error, so the table is generous on purpose.
     */
    private const MAGNITUDES = [
        'k' => 3,
        'к' => 3,
        'ming' => 3,
        'mingta' => 3,
        'минг' => 3,
        'мингта' => 3,
        'тыс' => 3,
        'тысяч' => 3,
        'тысяча' => 3,
        'тыщ' => 3,
        'тыща' => 3,
        'тыщи' => 3,
        'mln' => 6,
        'млн' => 6,
        'million' => 6,
        'millon' => 6,
        'миллион' => 6,
        'миллон' => 6,
        'мильон' => 6,
        'лям' => 6,
        'mlrd' => 9,
        'млрд' => 9,
        'milliard' => 9,
        'миллиард' => 9,
    ];

    /**
     * A remainder made only of digits, spaces and separators is the rest of a number typed
     * in pieces, never a note, so `100 000`, `100 00` and `1 2 3` refuse.
     */
    private const NUMERIC_REMAINDER = '/^[0-9., ]+$/';

    /**
     * A note that opens with two or more digits, whatever follows them, is a number typed in
     * pieces whose tail is being read as a note: `100 00 obed` means 10000, `50 00som` means
     * 5000, and `50 000so'm` means 50000. Recording the leading piece loses orders of
     * magnitude. A bare zero opens nothing countable either. One to nine stays a count, so
     * `1000 2 kishi`, `50000 2ta non` and `120000 3-avtobus` still record; `50000 12 kishi`,
     * `1000 25ta` and `500km yo'l` refusing is the deliberate cost, because nothing
     * distinguishes a genuine count of ten or more from a mistyped group.
     */
    private const DIGITS_OPEN_NOTE = '/^(?:[0-9]{2}|0)/';

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

        if (preg_match(self::DIGITS_OPEN_NOTE, $note) === 1) {
            return null;
        }

        if ($this->magnitudeOpensNote($note)) {
            return null;
        }

        if ($this->magnitudeQualifiesNoteNumber($note)) {
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
     * The amount token is a run of digits and separators, optionally multiplied by a magnitude
     * that may be glued to it or stand a space apart, and only a space or the end of the
     * message may follow, so `120000taksi` and `2mlnsom` refuse rather than split, while
     * `3 kg` reads as three with the note `kg` because `g` is neither. The remainder
     * alternates with the empty string so the group always takes part in the match.
     */
    private function tokenPattern(): string
    {
        return '/^(?<token>[0-9.,]+)(?<magnitude> *(?:'.$this->magnitudeAlternation().'))?(?<remainder> .*|)$/iu';
    }

    /**
     * A note holding a digit run followed by a magnitude carries part of the amount: the
     * second term of `1 mln 5 ming`, which means 1005000 and not 1000000, or the real amount
     * behind a leading count in `3 kg 2 mln`. The note-outweighs rule cannot see either,
     * because the note's bare digits are small. Adding the terms together, or picking one,
     * would be guessing, so it refuses; `1.5 mlrd` says the same thing in one term.
     */
    private function magnitudeQualifiesNoteNumber(string $note): bool
    {
        return preg_match('/[0-9] *(?:'.$this->magnitudeAlternation().')/iu', $note) === 1;
    }

    /**
     * A note opening with a magnitude the token could not swallow is a magnitude this grammar
     * cannot read: `2 mln.`, `30 тыс.`, `30 minglab`, `50 mingga`. Recording the bare leading
     * count would be the very disaster the magnitudes exist to prevent, so it refuses.
     */
    private function magnitudeOpensNote(string $note): bool
    {
        return preg_match('/^(?:'.$this->magnitudeAlternation().')/iu', $note) === 1;
    }

    /**
     * The one alternation every magnitude rule reads: the token pattern that swallows a
     * magnitude, and the two note rules that refuse one the token could not. Every entry of
     * the table appears in all three, so a magnitude cannot be a magnitude to one rule and
     * invisible to another. Longest first, so the alternation reads the way it matches rather
     * than leaning on backtracking.
     */
    private function magnitudeAlternation(): string
    {
        $words = array_keys(self::MAGNITUDES);

        usort($words, static fn (string $word, string $other): int => strlen($other) <=> strlen($word));

        return implode('|', array_map($this->magnitudeFragment(...), $words));
    }

    /**
     * A one-letter magnitude cannot stand in an alternation unguarded: `k` would match inside
     * `kishi`, `kg`, `km`, `kun` and `kerak`, and its Cyrillic twin `к` inside `кг`, `км`,
     * `кун`, `керак`, `китоб` and `картошка`, so a one-letter entry carries a lookahead that a
     * whole word does not need. The lookahead is what protects those notes. `k` used to be
     * excluded from the note rules and re-added as a literal in the token pattern instead, and
     * that divergence is exactly what let `5 kishi 2k` record 5 and `1 mln 5k` drop its second
     * term.
     *
     * The length is counted in characters, not bytes. `к` is one character and two bytes, so a
     * byte count would hand every Cyrillic one-letter magnitude an unguarded fragment and
     * refuse every ordinary Cyrillic note word beginning with it.
     */
    private function magnitudeFragment(string $word): string
    {
        return mb_strlen($word) === 1
            ? $word.'(?![\p{L}0-9])'
            : $word;
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
