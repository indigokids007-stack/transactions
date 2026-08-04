<?php

namespace App\Support;

use App\Models\Currency;
use InvalidArgumentException;

class Money
{
    /**
     * Largest amount any transaction may hold, in minor units.
     *
     * Below 2^53, so every stored amount stays inside the range where a float
     * represents an integer exactly and the conversions here are lossless. That is a
     * far tighter bound than the bigint column, and deliberately so: it leaves the
     * int cast in `toMinor()` nowhere near the range where it wraps.
     */
    public const MAX_MINOR = 1_000_000_000_000_000;

    /** Widest integer part accepted from a caller, in digits. */
    public const MAX_INTEGER_DIGITS = 15;

    /**
     * Shape of an amount as callers submit it: a bounded decimal string. The bound is
     * what keeps `toMinor()` away from the range where casting the float to int wraps,
     * so it has to be enforced on the string, before any conversion.
     */
    public const AMOUNT_PATTERN = '/^\d{1,'.self::MAX_INTEGER_DIGITS.'}(\.\d{1,4})?$/';

    public static function exponent(string $currency): int
    {
        $exponent = Currency::exponents()[$currency] ?? null;

        if ($exponent === null) {
            throw new InvalidArgumentException("Unsupported currency `{$currency}`.");
        }

        return $exponent;
    }

    public static function isSupported(string $currency): bool
    {
        return array_key_exists($currency, Currency::exponents());
    }

    public static function toMinor(string $amount, string $currency): int
    {
        $exponent = static::exponent($currency);

        return (int) round(((float) $amount) * (10 ** $exponent));
    }

    public static function toDecimal(int $minor, string $currency): string
    {
        $exponent = static::exponent($currency);

        if ($exponent === 0) {
            return (string) $minor;
        }

        // Integer arithmetic throughout: `$minor` can exceed 2^53 once a report sums many
        // transactions, and a float division (the previous implementation) silently rounds
        // past that point. `intdiv()`/`%` on two ints never leave the integer domain, so the
        // string built from them is exact regardless of magnitude.
        //
        // `$minor` is signed and PHP_INT_MIN has no positive int counterpart (its magnitude
        // is one past PHP_INT_MAX), so `abs($minor)` would silently widen to a float and
        // `intdiv()` would then throw. Dividing/moduloing `$minor` itself sidesteps that:
        // PHP's `intdiv`/`%` truncate toward zero on negative operands, and since the
        // exponent==0 case already returned above, `$divisor` is always >= 10 here, which
        // keeps both the quotient and the remainder well clear of PHP_INT_MIN and safe to
        // negate.
        $sign = $minor < 0 ? '-' : '';
        $divisor = 10 ** $exponent;

        $whole = intdiv($minor, $divisor);
        $fraction = $minor % $divisor;

        if ($whole < 0) {
            $whole = -$whole;
        }

        if ($fraction < 0) {
            $fraction = -$fraction;
        }

        return $sign.$whole.'.'.str_pad((string) $fraction, $exponent, '0', STR_PAD_LEFT);
    }
}
