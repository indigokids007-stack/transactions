<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;

/**
 * A one-off setup step, run by a human after the app is reachable over HTTPS: it tells
 * Telegram where to deliver updates and which shared secret to sign them with.
 */
class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = "Register this application's webhook URL with the Telegram Bot API";

    public function __construct(private readonly TelegramClient $telegramClient)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $secretToken = (string) config('services.telegram.webhook_secret');

        if ($secretToken === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET is blank: refusing to register a webhook Telegram could call unsigned.');

            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/').'/telegram/webhook';

        $response = $this->telegramClient->setWebhook($url, $secretToken);

        $this->line((string) json_encode($response));

        if (($response['ok'] ?? false) !== true) {
            $this->error('Telegram refused the webhook registration: '.($response['description'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info("Webhook registered at {$url}.");

        return self::SUCCESS;
    }
}
