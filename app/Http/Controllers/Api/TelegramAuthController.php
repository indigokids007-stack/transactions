<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResolveTelegramUser;
use App\Exceptions\InvalidInitDataException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TelegramAuthRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Telegram\InitDataValidator;

class TelegramAuthController extends Controller
{
    private const TOKEN_NAME = 'mini-app';

    private const TOKEN_LIFETIME_IN_DAYS = 30;

    private const MAX_CONCURRENT_TOKENS = 5;

    public function __construct(
        private readonly InitDataValidator $validator,
        private readonly ResolveTelegramUser $resolveTelegramUser,
    ) {}

    /** @return array<string, mixed> */
    public function store(TelegramAuthRequest $request): array
    {
        try {
            $payload = $this->validator->validate($request->initData());
        } catch (InvalidInitDataException) {
            abort(401, 'Invalid Telegram init data.');
        }

        /** @var array<string, mixed> $telegramUser */
        $telegramUser = $payload['user'];
        $languageCode = $telegramUser['language_code'] ?? null;

        $user = $this->resolveTelegramUser->handle(
            $telegramUser,
            is_string($languageCode) ? $languageCode : '',
        );

        return [
            'token' => $user->isActive() ? $this->issueToken($user) : null,
            'user' => UserResource::make($user->loadMissing(['department', 'managedDepartments'])),
        ];
    }

    private function issueToken(User $user): string
    {
        $this->makeRoomForOneMoreToken($user);

        return $user
            ->createToken(self::TOKEN_NAME, ['*'], now()->addDays(self::TOKEN_LIFETIME_IN_DAYS))
            ->plainTextToken;
    }

    private function makeRoomForOneMoreToken(User $user): void
    {
        $user->tokens()
            ->where('name', self::TOKEN_NAME)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $surplus = $user->tokens()
            ->where('name', self::TOKEN_NAME)
            ->orderByDesc('id')
            ->skip(self::MAX_CONCURRENT_TOKENS - 1)
            ->pluck('id');

        if ($surplus->isEmpty()) {
            return;
        }

        $user->tokens()->whereIn('id', $surplus->all())->delete();
    }
}
