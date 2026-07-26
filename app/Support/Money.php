<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
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
