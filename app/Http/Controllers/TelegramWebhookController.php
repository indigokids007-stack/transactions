<?php

namespace App\Http\Controllers;

use App\Actions\Drafts\ConfirmDraft;
use App\Actions\Drafts\StartDraft;
use App\Actions\ResolveTelegramUser;
use App\Models\Category;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\EntryDraft;
use App\Models\User;
use App\Services\Telegram\AmountNoteParser;
use App\Services\Telegram\CallbackContext;
use App\Services\Telegram\DraftCallback;
use App\Services\Telegram\DraftPresenter;
use App\Services\Telegram\ParsedEntry;
use App\Services\Telegram\TelegramClient;
use App\Validation\TransactionFieldValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Telegram resends an update until it is acknowledged, so every update this endpoint
 * accepts is answered with 200, including the ones it does nothing with: an update that
 * is neither a message nor a tapped button, a message with no text, a button with data
 * this application never wrote.
 */
class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly ResolveTelegramUser $resolveTelegramUser,
        private readonly AmountNoteParser $parser,
        private readonly StartDraft $startDraft,
        private readonly ConfirmDraft $confirmDraft,
        private readonly TelegramClient $telegram,
        private readonly DraftPresenter $presenter,
        private readonly TransactionFieldValidator $validator,
    ) {}

    /** @return array<string, bool> */
    public function __invoke(Request $request): array
    {
        $message = $request->input('message');

        if (is_array($message)) {
            $this->onMessage($message);

            return ['ok' => true];
        }

        $callback = $request->input('callback_query');

        if (is_array($callback)) {
            $this->onCallback($callback);
        }

        return ['ok' => true];
    }

    /** @param array<string, mixed> $message */
    private function onMessage(array $message): void
    {
        $text = $message['text'] ?? null;
        $from = $message['from'] ?? null;
        $chatId = $this->integer($message, 'chat.id');

        if (! is_string($text) || ! is_array($from) || $chatId === 0) {
            return;
        }

        $user = $this->resolve($from);

        if (! $user instanceof User) {
            $this->telegram->sendMessage($chatId, $this->refusal($from));

            return;
        }

        if (! $user->isActive()) {
            $this->telegram->sendMessage($chatId, $this->presenter->line('pending', $user));

            return;
        }

        $entry = $this->parser->parse($text);

        if (! $entry instanceof ParsedEntry) {
            $this->telegram->sendMessage($chatId, $this->presenter->line('unparseable', $user));

            return;
        }

        $draft = $this->startDraft->handle($user, $entry);

        $this->telegram->sendMessage(
            $chatId,
            $this->presenter->preview($draft, $user),
            $this->presenter->keyboard($draft, $user),
        );
    }

    /** @param array<string, mixed> $query */
    private function onCallback(array $query): void
    {
        $data = $query['data'] ?? null;
        $queryId = $query['id'] ?? null;
        $from = $query['from'] ?? null;
        $chatId = $this->integer($query, 'message.chat.id');
        $messageId = $this->integer($query, 'message.message_id');

        if (! is_string($data) || ! is_string($queryId) || ! is_array($from) || $chatId === 0 || $messageId === 0) {
            return;
        }

        $callback = DraftCallback::parse($data);

        if (! $callback instanceof DraftCallback) {
            $this->telegram->answerCallbackQuery($queryId);

            return;
        }

        $user = $this->resolve($from);

        if (! $user instanceof User) {
            $this->telegram->answerCallbackQuery($queryId, $this->refusal($from));

            return;
        }

        if (! $user->isActive()) {
            $this->telegram->answerCallbackQuery($queryId, $this->presenter->line('pending', $user));

            return;
        }

        $this->act(new CallbackContext($queryId, $chatId, $messageId, $user, $callback));
    }

    /**
     * The draft is looked up inside the caller's own drafts, and a draft that is not
     * theirs is answered exactly like one that has expired, so tapping a guessed button
     * cannot tell anyone whose drafts exist.
     */
    private function act(CallbackContext $context): void
    {
        $draft = EntryDraft::query()
            ->where('user_id', $context->user->id)
            ->where('id', 'like', $context->callback->draftId.'%')
            ->first();

        if (! $draft instanceof EntryDraft || $draft->isExpired()) {
            $draft?->delete();

            $this->telegram->answerCallbackQuery($context->queryId, $this->presenter->line('expired', $context->user));

            return;
        }

        match ($context->callback->verb) {
            DraftCallback::CONFIRM => $this->confirm($draft, $context),
            DraftCallback::CHOOSE_CATEGORY => $this->chooseCategory($draft, $context),
            DraftCallback::SET_CATEGORY => $this->setCategory($draft, $context),
            DraftCallback::SET_DIMENSION => $this->setDimension($draft, $context),
            DraftCallback::CANCEL => $this->cancel($draft, $context),
            default => $this->telegram->answerCallbackQuery($context->queryId),
        };
    }

    private function confirm(EntryDraft $draft, CallbackContext $context): void
    {
        $missing = $this->validator
            ->missingRequiredDimensions(TransactionFieldValidator::dimensionValues($this->payload($draft)['dimension_values'] ?? null))
            ->first();

        if ($missing instanceof Dimension) {
            $this->askForDimension($draft, $context, $missing);

            return;
        }

        if (($this->payload($draft)['category_id'] ?? null) === null) {
            $this->chooseCategory($draft, $context);

            return;
        }

        try {
            $transaction = $this->confirmDraft->handle($draft, $context->user);
        } catch (ValidationException $exception) {
            $this->edit(
                $context,
                $this->presenter->line('save_failed', $context->user, ['reason' => $exception->validator->errors()->first()]),
                $this->presenter->keyboard($draft, $context->user),
            );

            $this->telegram->answerCallbackQuery($context->queryId);

            return;
        }

        $this->edit($context, $this->presenter->saved($transaction, $context->user));

        $this->telegram->answerCallbackQuery($context->queryId, $this->presenter->line('saved', $context->user));
    }

    /**
     * A required dimension nobody has given values yet would otherwise be a dead end: the
     * prompt would carry a keyboard whose only button is Cancel, and the entry could never
     * be recorded. Say so, and leave the draft's own keyboard in place so the person can
     * still change the category or cancel deliberately.
     */
    private function askForDimension(EntryDraft $draft, CallbackContext $context, Dimension $dimension): void
    {
        $hasValues = $dimension->values()->where('is_active', true)->exists();

        $this->edit(
            $context,
            $this->presenter->line($hasValues ? 'choose_dimension' : 'no_dimension_values', $context->user, [
                'dimension' => $dimension->name,
            ]),
            $hasValues
                ? $this->presenter->dimensionKeyboard($draft, $dimension, $context->user)
                : $this->presenter->keyboard($draft, $context->user),
        );

        $this->telegram->answerCallbackQuery($context->queryId);
    }

    private function chooseCategory(EntryDraft $draft, CallbackContext $context): void
    {
        if (! Category::query()->where('is_active', true)->exists()) {
            $this->edit($context, $this->presenter->line('no_categories', $context->user));

            $this->telegram->answerCallbackQuery($context->queryId);

            return;
        }

        $this->edit(
            $context,
            $this->presenter->line('choose_category', $context->user),
            $this->presenter->categoryKeyboard($draft, $context->user),
        );

        $this->telegram->answerCallbackQuery($context->queryId);
    }

    private function setCategory(EntryDraft $draft, CallbackContext $context): void
    {
        $category = Category::query()
            ->where('is_active', true)
            ->find($context->callback->argument(0));

        if ($category instanceof Category) {
            $this->store($draft, ['category_id' => $category->id]);
        }

        $this->showPreview($draft, $context);
    }

    private function setDimension(EntryDraft $draft, CallbackContext $context): void
    {
        $dimensionId = $context->callback->argument(0);

        $value = DimensionValue::query()
            ->where('dimension_id', $dimensionId)
            ->where('is_active', true)
            ->whereRelation('dimension', 'is_active', true)
            ->find($context->callback->argument(1));

        if ($value instanceof DimensionValue && $dimensionId !== null) {
            $values = TransactionFieldValidator::dimensionValues($this->payload($draft)['dimension_values'] ?? null);
            $values[$dimensionId] = $value->id;

            $this->store($draft, ['dimension_values' => $values]);
        }

        $this->showPreview($draft, $context);
    }

    private function cancel(EntryDraft $draft, CallbackContext $context): void
    {
        $draft->delete();

        $this->edit($context, $this->presenter->line('cancelled', $context->user));

        $this->telegram->answerCallbackQuery($context->queryId, $this->presenter->line('cancelled', $context->user));
    }

    private function showPreview(EntryDraft $draft, CallbackContext $context): void
    {
        $this->edit(
            $context,
            $this->presenter->preview($draft, $context->user),
            $this->presenter->keyboard($draft, $context->user),
        );

        $this->telegram->answerCallbackQuery($context->queryId);
    }

    /** @param array<int, array<int, array<string, mixed>>>|null $keyboard */
    private function edit(CallbackContext $context, string $text, ?array $keyboard = null): void
    {
        $this->telegram->editMessageText($context->chatId, $context->messageId, $text, $keyboard);
    }

    /** @param array<string, mixed> $changes */
    private function store(EntryDraft $draft, array $changes): void
    {
        $draft->update(['payload' => [...$this->payload($draft), ...$changes]]);
    }

    /** @return array<string, mixed> */
    private function payload(EntryDraft $draft): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $draft->payload;

        return $payload;
    }

    /**
     * Resolution is shared with the mini app, so a pending, blocked or unregistered
     * person is treated the same way whichever door they come through.
     *
     * @param  array<string, mixed>  $from
     */
    private function resolve(array $from): ?User
    {
        try {
            $languageCode = $from['language_code'] ?? null;

            return $this->resolveTelegramUser->handle($from, is_string($languageCode) ? $languageCode : '');
        } catch (AuthorizationException) {
            return null;
        }
    }

    /**
     * `ResolveTelegramUser` refuses for two reasons. Someone who already has a row was
     * refused because that row is blocked; someone who has none was refused because
     * registration is closed.
     *
     * @param  array<string, mixed>  $from
     */
    private function refusal(array $from): string
    {
        $telegramId = $from['id'] ?? null;
        $user = User::firstWhere('telegram_id', is_numeric($telegramId) ? (int) $telegramId : 0);

        return $this->presenter->line($user instanceof User ? 'blocked' : 'registration_closed', $user);
    }

    /** @param array<string, mixed> $source */
    private function integer(array $source, string $key): int
    {
        $value = data_get($source, $key);

        return is_numeric($value) ? (int) $value : 0;
    }
}
