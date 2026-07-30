<?php

namespace App\Filament\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The single place the panel asks who is acting. Every mutating action in the panel
 * checks this rather than the record being edited, which is what keeps an owner's read
 * only status from depending on which resource happens to remember to enforce it.
 */
class PanelUser
{
    public static function isAdmin(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }
}
