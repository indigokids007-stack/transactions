<?php

namespace App\Services\Telegram;

readonly class ParsedEntry
{
    public function __construct(
        public string $amount,
        public ?string $note = null,
    ) {}
}
