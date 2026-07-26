<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ResolveTelegramUser
{
    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['uz', 'ru', 'en'];

    private const FALLBACK_LOCALE = 'uz';

    /**
     * @param  array<string, mixed>  $telegramUser
     *
     * @throws AuthorizationException
     */
    public function handle(array $telegramUser, string $languageCode): User
    {
        $telegramId = (int) ($telegramUser['id'] ?? 0);
        $user = User::firstWhere('telegram_id', $telegramId);

        if ($user) {
            if ($user->status === UserStatus::Blocked) {
                throw new AuthorizationException('This account is blocked.');
            }

            $user->update([
                'name' => $this->name($telegramUser, $telegramId),
                'username' => $this->string($telegramUser, 'username'),
            ]);

            return $user;
        }

        if (! Setting::get('registration_open', true)) {
            throw new AuthorizationException('Registration is closed.');
        }

        return User::create([
            'telegram_id' => $telegramId,
            'name' => $this->name($telegramUser, $telegramId),
            'username' => $this->string($telegramUser, 'username'),
            'role' => UserRole::Staff,
            'status' => UserStatus::Pending,
            'locale' => $this->initialLocale($languageCode),
        ]);
    }

    /** @param array<string, mixed> $telegramUser */
    private function name(array $telegramUser, int $telegramId): string
    {
        $parts = array_filter([
            $this->string($telegramUser, 'first_name'),
            $this->string($telegramUser, 'last_name'),
        ]);

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return $this->string($telegramUser, 'username') ?? "Telegram {$telegramId}";
    }

    /** @param array<string, mixed> $telegramUser */
    private function string(array $telegramUser, string $key): ?string
    {
        $value = $telegramUser[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Telegram only seeds the locale. Once stored it belongs to the user and no
     * later login may change it.
     */
    private function initialLocale(string $languageCode): string
    {
        if (in_array($languageCode, self::SUPPORTED_LOCALES, true)) {
            return $languageCode;
        }

        return self::FALLBACK_LOCALE;
    }
}
