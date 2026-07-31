<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The webhook is a public URL, so the shared secret Telegram echoes back in this header
 * is the only thing that says an update came from Telegram. It is compared in constant
 * time, and an unconfigured secret refuses everything rather than accepting everything.
 */
class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.telegram.webhook_secret');
        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! is_string($expected) || $expected === '' || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            abort(403);
        }

        return $next($request);
    }
}
