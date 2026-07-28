<?php

namespace App\Services\Telegram;

use App\Models\EntryDraft;

/**
 * The grammar of the inline buttons: `d:<draftId8>:<verb>[:<argument>...]`. Telegram
 * caps callback data at 64 bytes, which is why the buttons carry the first eight
 * characters of the draft uuid instead of the whole of it. Eight hex characters are not
 * enough to be unguessable, so the lookup they feed is always restricted to the user who
 * tapped the button; they identify a draft, they do not authorise it.
 */
readonly class DraftCallback
{
    public const PREFIX = 'd';

    public const ID_LENGTH = 8;

    public const CONFIRM = 'ok';

    public const CHOOSE_CATEGORY = 'cat';

    public const SET_CATEGORY = 'c';

    public const SET_DIMENSION = 'dim';

    public const CANCEL = 'x';

    /** @param list<string> $arguments */
    public function __construct(
        public string $draftId,
        public string $verb,
        public array $arguments = [],
    ) {}

    public static function parse(string $data): ?self
    {
        $parts = explode(':', $data);

        if (count($parts) < 3 || $parts[0] !== self::PREFIX || $parts[2] === '') {
            return null;
        }

        // Only the characters a uuid opens with, so the prefix can never carry a `%` or
        // a `_` into the `like` it becomes.
        if (preg_match('/^[0-9a-f]{'.self::ID_LENGTH.'}$/', $parts[1]) !== 1) {
            return null;
        }

        return new self($parts[1], $parts[2], array_slice($parts, 3));
    }

    public static function build(EntryDraft $draft, string $verb, int ...$arguments): string
    {
        return implode(':', [self::PREFIX, substr($draft->id, 0, self::ID_LENGTH), $verb, ...$arguments]);
    }

    public function argument(int $position): ?int
    {
        $value = $this->arguments[$position] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
