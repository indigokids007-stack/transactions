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

it('normalises grouped and decimal separators', function () {
    expect((new AmountNoteParser)->parse('120 000')->amount)->toBe('120000')
        ->and((new AmountNoteParser)->parse("120'000")->amount)->toBe('120000')
        ->and((new AmountNoteParser)->parse('12,50 kofe')->amount)->toBe('12.50');
});

it('returns null when there is no leading amount', function () {
    expect((new AmountNoteParser)->parse('salom'))->toBeNull()
        ->and((new AmountNoteParser)->parse(''))->toBeNull();
});
