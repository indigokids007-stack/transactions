<?php

use App\Support\Money;

it('converts decimal amounts to minor units by currency exponent', function () {
    expect(Money::toMinor('120000', 'UZS'))->toBe(120000)
        ->and(Money::toMinor('12.34', 'USD'))->toBe(1234)
        ->and(Money::toMinor('12.3', 'USD'))->toBe(1230);
});

it('formats minor units back to a decimal string', function () {
    expect(Money::toDecimal(120000, 'UZS'))->toBe('120000')
        ->and(Money::toDecimal(1234, 'USD'))->toBe('12.34');
});

it('knows which currencies are supported', function () {
    expect(Money::isSupported('UZS'))->toBeTrue()
        ->and(Money::isSupported('XXX'))->toBeFalse();
});
