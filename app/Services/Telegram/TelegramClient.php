<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The Bot API calls this application makes, over the HTTP client rather than a package.
 * A failed call is logged and swallowed: the webhook has already accepted the update and
 * a non 200 answer would only make Telegram send it again.
 *
 * That promise covers a refusal by Telegram and a failure to reach it at all. A timeout
 * or a DNS failure raises `ConnectionException` from the transport, which used to escape
 * this class and fail the whole webhook request. Telegram redelivers an update it did not
 * get a 200 for, so on the message path every redelivery wrote another draft, and on the
 * confirm path the transaction was already committed and the draft already deleted, so
 * the redelivery told the person their entry had expired when it had in fact been saved.
 */
class TelegramClient
{
    private const TIMEOUT_IN_SECONDS = 10;

    /** Total attempts per call, not retries on top of the first. */
    private const ATTEMPTS = 3;

    private const RETRY_DELAY_IN_MILLISECONDS = 200;

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

        return $this->payload($response);
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

        return $this->payload($response);
    }

    /** @return array<string, mixed> */
    private function payload(?Response $response): array
    {
        $json = $response?->json();

        /** @var array<string, mixed> $payload */
        $payload = is_array($json) ? $json : [];

        return $payload;
    }

    /**
     * Null means the call never reached Telegram. Every caller treats that the same way it
     * treats a refusal, so nothing above this line has to know the difference.
     *
     * @param  array<string, mixed>  $payload
     */
    private function call(string $method, array $payload): ?Response
    {
        try {
            $response = $this->request()->post("/{$method}", $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Telegram API call could not be delivered.', [
                'method' => $method,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

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
            // A blip is worth a second try before a person is left without their preview.
            // `throw: false` keeps a refusal by Telegram out of the exception path, so the
            // status check below stays the one place a refusal is handled.
            ->retry(self::ATTEMPTS, self::RETRY_DELAY_IN_MILLISECONDS, throw: false)
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
