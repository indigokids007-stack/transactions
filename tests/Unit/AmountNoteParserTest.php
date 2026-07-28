<?php

use App\Services\Telegram\AmountNoteParser;

it('parses a bare amount', function () {
    $entry = (new AmountNoteParser)->parse('120000');

    expect($entry->amount)->toBe('120000')
        ->and($entry->note)->toBeNull();
});

it('parses an amount with a note', function () {
    $entry = (new AmountNoteParser)->parse('120000 taksi Chilonzor');

    expect($entry->amount)->toBe('120000')
        ->and($entry->note)->toBe('taksi Chilonzor');
});

/**
 * The ruling, row by row. A dot is a thousands separator and never a decimal point, a `k`
 * suffix multiplies by a thousand and may carry a decimal fraction, and a space groups
 * nothing at all.
 */
it('reads amounts the way the ruling says', function (string $text, ?string $amount) {
    expect((new AmountNoteParser)->parse($text)?->amount)->toBe($amount);
})->with([
    ['100k', '100000'],
    ['1.5k', '1500'],
    ['100.000', '100000'],
    ['1.250.000', '1250000'],
    ['12.50', '12500'],
    ['100000', '100000'],
    ['100 000', null],
    ['120000 taksi', '120000'],
]);

/**
 * A space groups nothing, so the note is simply what follows the amount and it may open
 * with a digit. `100 000` is still refused, but for its own reason: what follows is only
 * digits, so it is the rest of a number rather than a note.
 */
it('keeps a note that begins with a digit', function (string $text, string $amount, string $note) {
    $entry = (new AmountNoteParser)->parse($text);

    expect($entry->amount)->toBe($amount)
        ->and($entry->note)->toBe($note);
})->with([
    ['1000 2 kishi', '1000', '2 kishi'],
    ['50000 2 kishi obed', '50000', '2 kishi obed'],
    ['120000 3-avtobus', '120000', '3-avtobus'],
    ['50000 2ta non', '50000', '2ta non'],
    ['10000 500km yo\'l', '10000', '500km yo\'l'],
    ['1000 2', '1000', '2'],
]);

/**
 * The shape that matters most on a note taking bot: an amount typed with spaces and then
 * what it was spent on. Reading `50 000 taksi` as fifty with the note `000 taksi` records
 * a thousandth of the entry, and it is a preview someone taps straight through.
 */
it('refuses a number typed with spaces however the message goes on', function (string $text) {
    expect((new AmountNoteParser)->parse($text))->toBeNull();
})->with([
    ['50 000 taksi'],
    ['100 000 taksi'],
    ['120 000 som'],
    ['1 500 000 uy ijara'],
    ['12 500 obed'],
    ['100 000,50 taksi'],
    ['100 000'],
    ['100 000 000'],
    ['50 000k'],
    ['50 000-taksi'],
]);

/**
 * The cost of anchoring on the front of the note: a note that genuinely opens with a three
 * digit count is refused too, because nothing distinguishes it from a spaced group. The
 * person retypes it as `5000 100ta dona` or `5000 dona 100` and the entry is recorded.
 * This is the deliberate side of the trade, not an accident.
 */
it('refuses a note that opens with a three digit count', function () {
    expect((new AmountNoteParser)->parse('5000 100 dona'))->toBeNull();
});

/**
 * A short group after the first separator is shorthand, but only when it is the only
 * separator. `1.000,00` is how a Russian or Uzbek keyboard writes one thousand, and
 * padding its last group would record a million.
 */
it('refuses a short group once the number is already grouped', function (string $text) {
    expect((new AmountNoteParser)->parse($text))->toBeNull();
})->with([
    'european thousand' => ['1.000,00'],
    'grouped with a short tail' => ['1.250.00'],
    'grouped with a single digit tail' => ['12.345.6'],
]);

it('treats a comma exactly like a dot', function () {
    expect((new AmountNoteParser)->parse('12,50')?->amount)->toBe('12500')
        ->and((new AmountNoteParser)->parse('1,250,000')?->amount)->toBe('1250000')
        ->and((new AmountNoteParser)->parse('1,5k')?->amount)->toBe('1500');
});

it('takes the k suffix in either case', function () {
    expect((new AmountNoteParser)->parse('100K')?->amount)->toBe('100000')
        ->and((new AmountNoteParser)->parse('50k taksi')?->note)->toBe('taksi');
});

it('returns null when there is no leading amount', function () {
    expect((new AmountNoteParser)->parse('salom'))->toBeNull()
        ->and((new AmountNoteParser)->parse(''))->toBeNull();
});

it('refuses anything that is not plainly an amount', function (string $text) {
    expect((new AmountNoteParser)->parse($text))->toBeNull();
})->with([
    'a negative amount' => ['-1000'],
    'more digits than the column holds' => ['1234567890123456'],
    'a note with no space before it' => ['120000taksi'],
    'a bare suffix' => ['k'],
    'a suffix with no amount' => ['k taksi'],
    'zero' => ['0'],
    'zero in groups' => ['0.000'],
    'a trailing separator' => ['1000.'],
    'a leading separator' => ['.500'],
    'a group that is too wide' => ['1.2500'],
    'a leading part wider than a group' => ['1234.567'],
    'a short middle group' => ['1.25.000'],
    'an apostrophe as a group separator' => ["120'000"],
    'a suffix carrying groups' => ['1.250.000k'],
]);

it('reads a message the way a phone sends one', function () {
    $spaced = (new AmountNoteParser)->parse('  120000 taksi  ');
    $wrapped = (new AmountNoteParser)->parse("1000 taksi\nChilonzor");
    $emoji = (new AmountNoteParser)->parse('50000 taksi 🚕');

    expect($spaced->amount)->toBe('120000')
        ->and($spaced->note)->toBe('taksi')
        ->and($wrapped->amount)->toBe('1000')
        ->and($wrapped->note)->toBe("taksi\nChilonzor")
        ->and($emoji->note)->toBe('taksi 🚕');
});

it('takes the widest amount the column holds and refuses the next digit', function () {
    expect((new AmountNoteParser)->parse('123456789012345')?->amount)->toBe('123456789012345')
        ->and((new AmountNoteParser)->parse('1234567890123456'))->toBeNull();
});

it('refuses a bot command', function () {
    expect((new AmountNoteParser)->parse('/start'))->toBeNull()
        ->and((new AmountNoteParser)->parse('/help'))->toBeNull();
});
