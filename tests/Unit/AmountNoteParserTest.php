<?php

use App\Services\Telegram\AmountNoteParser;

it('reads an amount and a note, or refuses', function (string $text, ?string $amount, ?string $note = null) {
    $entry = (new AmountNoteParser)->parse($text);

    if ($amount === null) {
        expect($entry)->toBeNull();

        return;
    }

    expect($entry)->not->toBeNull()
        ->and($entry->amount)->toBe($amount)
        ->and($entry->note)->toBe($note);
})->with([
    'ruled: 100k' => ['100k', '100000'],
    'ruled: 1.5k' => ['1.5k', '1500'],
    'ruled: 100.000' => ['100.000', '100000'],
    'ruled: 1.250.000' => ['1.250.000', '1250000'],
    'ruled: 12.50' => ['12.50', '12500'],
    'ruled: 100000' => ['100000', '100000'],
    'ruled: 100 000 refuses' => ['100 000', null],
    'ruled: 120000 taksi' => ['120000 taksi', '120000', 'taksi'],

    'no-break space refuses' => ["100\u{00A0}000", null],
    'narrow no-break space refuses' => ["100\u{202F}000", null],
    'thin space refuses' => ["100\u{2009}000", null],
    'figure space refuses' => ["100\u{2007}000", null],
    'no-break space before a note' => ["1000\u{00A0}taksi", '1000', 'taksi'],

    'three digits then latin currency word' => ["50 000so'm", null],
    'three digits then sum' => ['120 000sum', null],
    'three digits then cyrillic sum' => ['50 000сум', null],
    'three digits then cyrillic rouble' => ['100 000руб', null],
    'three digits then ta' => ['50 000ta', null],
    'three digits then dan' => ['250 000dan', null],
    'three digits then currency code' => ['50 000UZS', null],
    'three digits then unit' => ['50 000km', null],

    'numeric tail of two digits' => ['100 00', null],
    'numeric tail of four digits' => ['1 0000', null],
    'numeric tail after twelve' => ['12 5000', null],
    'numeric tail after a hundred' => ['100 0000', null],

    'note counting people' => ['1000 2 kishi', '1000', '2 kishi'],
    'note counting bread' => ['50000 2ta non', '50000', '2ta non'],
    'note naming a bus' => ['120000 3-avtobus', '120000', '3-avtobus'],
    'note counting people at lunch' => ['50000 2 kishi obed', '50000', '2 kishi obed'],

    'spaced amount then a word' => ['50 000 taksi', null],
    'spaced amount with decimals then a word' => ['100 000,50 taksi', null],
    'spaced million then words' => ['1 500 000 uy ijara', null],
    'spaced amount then obed' => ['12 500 obed', null],
    'spaced groups only' => ['100 000 000', null],
    'spaced amount then bare suffix' => ['50 000k', null],
    'spaced amount then dashed word' => ['50 000-taksi', null],
    'note opening with a three digit count' => ['5000 100 dona', null],
    'note opening with three digits and a unit' => ["10000 500km yo'l", null],
    'digit-only note' => ['1000 2', null],
    'digits scattered by spaces' => ['1 2 3', null],

    'apostrophe as a separator' => ["120'000", null],
    'european thousand' => ['1.000,00', null],
    'grouped with a short tail' => ['1.250.00', null],
    'grouped with a single digit tail' => ['12.345.6', null],
    'short middle group' => ['1.25.000', null],
    'group wider than three digits' => ['1.2500', null],
    'leading part wider than a group' => ['1234.567', null],
    'trailing separator' => ['1000.', null],
    'leading separator' => ['.500', null],
    'bare fraction' => ['.5', null],
    'bare whole and separator' => ['5.', null],
    'doubled separator' => ['1..5', null],
    'comma as the one separator' => ['12,50', '12500'],
    'commas as group separators' => ['1,250,000', '1250000'],
    'comma before the suffix' => ['1,5k', '1500'],
    'uppercase suffix' => ['100K', '100000'],
    'suffix then a note' => ['50k taksi', '50000', 'taksi'],
    'fraction of a thousand' => ['0.5k', '500'],
    'suffix carrying groups' => ['1.250.000k', null],

    'widest amount the column holds' => ['123456789012345', '123456789012345'],
    'one digit past the column' => ['1234567890123456', null],
    'suffix landing exactly on the ceiling' => ['999999999999.999k', '999999999999999'],
    'suffix overflowing the ceiling' => ['999999999999999k', null],
    'zero' => ['0', null],
    'zero in groups' => ['0.000', null],
    'negative amount' => ['-1000', null],

    'note with no space before it' => ['120000taksi', null],
    'bare suffix' => ['k', null],
    'suffix with no amount' => ['k taksi', null],
    'plain word' => ['salom', null],
    'empty message' => ['', null],
    'start command' => ['/start', null],
    'help command' => ['/help', null],

    'leading and trailing whitespace' => ['  120000 taksi  ', '120000', 'taksi'],
    'multi-line note flattens' => ["1000 taksi\nChilonzor", '1000', 'taksi Chilonzor'],
    'emoji note' => ['50000 taksi 🚕', '50000', 'taksi 🚕'],
    'cyrillic note' => ['10000 такси', '10000', 'такси'],
]);
