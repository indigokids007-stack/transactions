<?php

namespace App\Filament\Resources\DimensionResource\RelationManagers;

use App\Filament\Support\LedgerReferenceGuard;
use App\Filament\Support\PanelUser;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Toggle::make('is_active')
                ->default(true),
            TextInput::make('sort')
                ->numeric()
                ->default(0)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort')
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->headerActions([
                CreateAction::make()->visible(fn (): bool => PanelUser::isAdmin()),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => PanelUser::isAdmin()),
                DeleteAction::make()
                    ->visible(fn (): bool => PanelUser::isAdmin())
                    ->before(fn (DeleteAction $action, Model $record) => LedgerReferenceGuard::one($action, $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => PanelUser::isAdmin())
                        ->before(fn (DeleteBulkAction $action, Collection $records) => LedgerReferenceGuard::many($action, $records)),
                ]),
            ]);
    }
}
