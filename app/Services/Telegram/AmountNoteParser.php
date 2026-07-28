<?php

namespace App\Services\Telegram;

use App\Support\Money;

class AmountNoteParser
{
    /**
     * A message opens with an amount and the rest of it is the note. The integer part is
     * either one plain run of digits or groups of three separated by a space or an
     * apostrophe, so `1000 2 kishi` reads as one thousand with a note and not as ten
     * thousand and two. A comma or a dot introduces the decimals, and the note has to
     * start on a separator so `120000taksi` is not read as an amount at all.
     */
    private const PATTERN = "/^\s*(?<integer>\d{1,3}(?:[ \x{00A0}\x{202F}'’]\d{3})+|\d+)(?:[.,](?<decimals>\d+))?(?<note>\s.*|)$/su";

    /** Group separators stripped out of the integer part before it becomes an amount. */
    private const GROUP_SEPARATORS = [' ', "\u{00A0}", "\u{202F}", "'", '’'];

    public function parse(string $text): ?ParsedEntry
    {
        if (preg_match(self::PATTERN, $text, $matches) !== 1) {
            return null;
        }

        $amount = str_replace(self::GROUP_SEPARATORS, '', $matches['integer']);

        if ($matches['decimals'] !== '') {
            $amount .= '.'.$matches['decimals'];
        }

        if (preg_match(Money::AMOUNT_PATTERN, $amount) !== 1 || (float) $amount <= 0) {
            return null;
        }

        $note = trim($matches['note']);

        return new ParsedEntry($amount, $note === '' ? null : $note);
    }
}
