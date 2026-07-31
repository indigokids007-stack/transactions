<?php

namespace App\Filament\Resources\DimensionResource\Pages;

use App\Filament\Resources\DimensionResource;
use App\Filament\Support\LedgerReferenceGuard;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDimension extends EditRecord
{
    protected static string $resource = DimensionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Model $record): bool => static::getResource()::canDelete($record))
                ->before(fn (DeleteAction $action, Model $record) => LedgerReferenceGuard::one($action, $record)),
        ];
    }
}
