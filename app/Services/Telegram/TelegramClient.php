<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The Bot API calls this application makes, over the HTTP client rather than a package.
 * A failed call is logged and swallowed: the webhook has already accepted the update and
 * a non 200 answer would only make Telegram send it again.
 */
class TelegramClient
{
    private const TIMEOUT_IN_SECONDS = 10;

    /**
     * @param  array<int, array<int, array<string, mixed>>>|null  $keyboard
     * @return array<string, mixed>
     */
    public function sendMessage(int $chatId, string $text, ?array $keyboard = null): array
    {
        $response = $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            ...$this->replyMarkup($keyboard),
        ]);

        /** @var array<string, mixed> $payload */
        $payload = is_array($response->json()) ? $response->json() : [];

        return $payload;
    }

    /** @param array<int, array<int, array<string, mixed>>>|null $keyboard */
    public function editMessageText(int $chatId, int $messageId, string $text, ?array $keyboard = null): void
    {
        $this->call('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            ...$this->replyMarkup($keyboard),
        ]);
    }

    public function answerCallbackQuery(string $id, ?string $text = null): void
    {
        $this->call('answerCallbackQuery', array_filter([
            'callback_query_id' => $id,
            'text' => $text,
        ], fn (mixed $value) => $value !== null));
    }

    /**
     * Point Telegram at this application's webhook URL. Unlike the other calls here, the
     * caller (the registration command) needs the raw response to decide whether to fail.
     *
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        $response = $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = is_array($response->json()) ? $response->json() : [];

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function call(string $method, array $payload): Response
    {
        $response = $this->request()->post("/{$method}", $payload);

        if (! $response->successful()) {
            Log::warning('Telegram API call failed.', [
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.telegram.api_url').'/bot'.config('services.telegram.bot_token'))
            ->timeout(self::TIMEOUT_IN_SECONDS)
            ->asJson();
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>|null  $keyboard
     * @return array<string, string>
     */
    private function replyMarkup(?array $keyboard): array
    {
        if ($keyboard === null) {
            return [];
        }

        return ['reply_markup' => (string) json_encode(['inline_keyboard' => $keyboard])];
    }
}
