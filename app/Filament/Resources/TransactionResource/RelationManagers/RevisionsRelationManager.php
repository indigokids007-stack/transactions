<?php

namespace App\Filament\Resources\TransactionResource\RelationManagers;

use App\Models\TransactionRevision;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Append only and read only: nothing here ever writes a revision, so the table has no
 * create, edit or delete action at all, for an admin exactly as much as for an owner.
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('actor.name')
                    ->label('Actor'),
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime(),
                TextColumn::make('snapshot')
                    ->label('Snapshot')
                    ->formatStateUsing(fn (TransactionRevision $record): string => json_encode(
                        $record->snapshot,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                    ))
                    ->wrap()
                    ->extraAttributes(['class' => 'font-mono text-xs']),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
