<?php

namespace App\Services\Telegram;

use App\Exceptions\InvalidInitDataException;

class InitDataValidator
{
    /**
     * @return array<string, mixed>
     *
     * @throws InvalidInitDataException
     */
    public function validate(string $initData): array
    {
        $botToken = (string) config('services.telegram.bot_token');

        if ($botToken === '') {
            throw new InvalidInitDataException('The Telegram bot token is not configured.');
        }

        parse_str($initData, $fields);

        $hash = $fields['hash'] ?? null;

        if (! is_string($hash)) {
            throw new InvalidInitDataException('Missing hash.');
        }

        unset($fields['hash']);
        ksort($fields);

        $checkString = $this->buildCheckString($fields);
        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);

        if (! hash_equals(hash_hmac('sha256', $checkString, $secret), $hash)) {
            throw new InvalidInitDataException('Signature mismatch.');
        }

        $authDate = (int) ($fields['auth_date'] ?? 0);

        if (now()->timestamp - $authDate > (int) config('services.telegram.init_data_ttl')) {
            throw new InvalidInitDataException('Stale auth_date.');
        }

        $user = json_decode((string) ($fields['user'] ?? ''), true);

        if (! is_array($user) || ! isset($user['id'])) {
            throw new InvalidInitDataException('Missing user.');
        }

        $fields['user'] = $user;

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     *
     * @throws InvalidInitDataException
     */
    private function buildCheckString(array $fields): string
    {
        $lines = [];

        foreach ($fields as $key => $value) {
            if (! is_string($value)) {
                throw new InvalidInitDataException("Field {$key} is not a scalar value.");
            }

            $lines[] = "{$key}={$value}";
        }

        return implode("\n", $lines);
    }
}
