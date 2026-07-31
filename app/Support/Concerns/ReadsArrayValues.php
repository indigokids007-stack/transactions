<?php

namespace App\Support\Concerns;

/**
 * Reading a usable scalar out of an array that came off the wire. A Telegram payload and a
 * draft payload are both `array<string, mixed>` by the time the domain sees one, and every
 * reader of either was repeating the same guard to get a string or an integer out of it.
 */
trait ReadsArrayValues
{
    /** @param array<string, mixed> $data */
    protected function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A string only where there is really one: absent, blank, or not a string all read as
     * null, so a caller can tell "nothing was sent" from "an empty string was sent".
     *
     * @param  array<string, mixed>  $data
     */
    protected function nonEmptyString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $data */
    protected function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }
}
