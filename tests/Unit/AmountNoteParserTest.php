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
    'a second amount where the note goes' => ['1000 2 kishi'],
    'a suffix carrying groups' => ['1.250.000k'],
]);
