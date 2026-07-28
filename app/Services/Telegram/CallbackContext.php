<?php

namespace App\Services\Telegram;

use App\Models\User;

/** Everything a tapped button needs answering: who tapped it, where, and what it said. */
readonly class CallbackContext
{
    public function __construct(
        public string $queryId,
        public int $chatId,
        public int $messageId,
        public User $user,
        public DraftCallback $callback,
    ) {}
}
