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

// The review's live check: ten maximal transactions (Money::MAX_MINOR each) plus one more
// minor unit sums to 10000000000000001 — one past Number.MAX_SAFE_INTEGER (2^53 - 1) and,
// more to the point here, past the point where PHP's own float division starts rounding.
// A prior implementation routed this through `$minor / (10 ** $exponent)`, so it built the
// string from an already-lossy float and rendered "100000000000000.00" here, silently
// dropping the last cent. `toDecimal()` must build the string from integer arithmetic only.
it('keeps the last cent for a minor-unit amount past the float-safe range', function () {
    expect(Money::toDecimal(10000000000000001, 'USD'))->toBe('100000000000000.01');
});

it('keeps the sign and the last cent for a negative amount past the float-safe range', function () {
    expect(Money::toDecimal(-10000000000000001, 'USD'))->toBe('-100000000000000.01');
});

it('formats a negative zero-exponent amount without going through the fraction branch', function () {
    expect(Money::toDecimal(-500, 'UZS'))->toBe('-500');
});

it('formats zero at a non-zero exponent with a padded fraction', function () {
    expect(Money::toDecimal(0, 'USD'))->toBe('0.00');
});

it('knows which currencies are supported', function () {
    expect(Money::isSupported('UZS'))->toBeTrue()
        ->and(Money::isSupported('XXX'))->toBeFalse();
});
