<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResolveTelegramUser;
use App\Exceptions\InvalidInitDataException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TelegramAuthRequest;
use App\Http\Resources\UserResource;
use App\Services\Telegram\InitDataValidator;

class TelegramAuthController extends Controller
{
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
            'token' => $user->isActive()
                ? $user->createToken('mini-app', ['*'], now()->addDays(30))->plainTextToken
                : null,
            'user' => UserResource::make($user),
        ];
    }
}
