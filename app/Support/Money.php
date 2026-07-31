<?php

namespace App\Support;

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
        $exponent = config("money.currencies.{$currency}");

        if ($exponent === null) {
            throw new InvalidArgumentException("Unsupported currency `{$currency}`.");
        }

        return $exponent;
    }

    public static function isSupported(string $currency): bool
    {
        return config("money.currencies.{$currency}") !== null;
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

        return number_format($minor / (10 ** $exponent), $exponent, '.', '');
    }
}
