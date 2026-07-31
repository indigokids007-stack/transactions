<?php

namespace App\Filament\Resources\Concerns;

use App\Filament\Support\PanelUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Only `UserRole::Admin` may create, edit or delete through this resource; an owner
 * reaches the panel too but stays read only everywhere in it. Every mutating action
 * the resource registers still has to consult this itself, since Filament does not
 * hide a table action just because a page-level check like this one exists.
 */
trait RestrictsMutationsToAdmin
{
    public static function canCreate(): bool
    {
        return PanelUser::isAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return PanelUser::isAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return PanelUser::isAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return PanelUser::isAdmin();
    }
}
