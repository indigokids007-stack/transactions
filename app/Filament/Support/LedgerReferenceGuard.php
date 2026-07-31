<?php

namespace App\Filament\Support;

use App\Contracts\ReferencedByLedger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The panel's half of the rule the foreign keys enforce. The database is what makes the
 * deletion impossible; this is what turns the refusal into a sentence an admin can act
 * on, before the query runs, instead of a failed request they have to guess at.
 *
 * It is attached to every delete the panel offers on a record a transaction can point at:
 * the row action, the edit page's header action, and the bulk action.
 */
class LedgerReferenceGuard
{
    public static function one(Action $action, Model $record): void
    {
        if (! self::isReferenced($record)) {
            return;
        }

        self::refuse($action);
    }

    /**
     * A selection is refused whole when any one of its records is referenced, rather than
     * deleting the rest around it: a bulk delete that silently did some of what was asked
     * is harder to reason about than one that did none of it and said why.
     *
     * @param  Collection<int, Model>  $records
     */
    public static function many(Action $action, Collection $records): void
    {
        if (! $records->contains(fn (Model $record) => self::isReferenced($record))) {
            return;
        }

        self::refuse($action);
    }

    private static function isReferenced(Model $record): bool
    {
        return $record instanceof ReferencedByLedger && $record->isReferencedByLedger();
    }

    private static function refuse(Action $action): void
    {
        Notification::make()
            ->danger()
            ->title(__('filament.delete_refused.title'))
            ->body(__('filament.delete_refused.body'))
            ->send();

        $action->cancel();
    }
}
