<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\RestrictsMutationsToAdmin;
use App\Filament\Resources\CurrencyResource\Pages\CreateCurrency;
use App\Filament\Resources\CurrencyResource\Pages\EditCurrency;
use App\Filament\Resources\CurrencyResource\Pages\ListCurrencies;
use App\Filament\Support\LedgerReferenceGuard;
use App\Models\Currency;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CurrencyResource extends Resource
{
    use RestrictsMutationsToAdmin;

    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->required()
                ->length(3)
                ->alpha()
                ->unique(ignoreRecord: true)
                ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper($state))
                ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper($state)),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('exponent')
                ->label('Decimal places')
                ->numeric()
                ->minValue(0)
                ->maxValue(4)
                ->default(2)
                ->required(),
            Toggle::make('is_active')
                ->default(true),
            TextInput::make('sort')
                ->numeric()
                ->default(0)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('exponent')
                    ->label('Decimal places'),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort')
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->visible(fn (Model $record): bool => static::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn (Model $record): bool => static::canDelete($record))
                    ->before(fn (DeleteAction $action, Model $record) => LedgerReferenceGuard::one($action, $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDeleteAny())
                        ->before(fn (DeleteBulkAction $action, Collection $records) => LedgerReferenceGuard::many($action, $records)),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCurrencies::route('/'),
            'create' => CreateCurrency::route('/create'),
            'edit' => EditCurrency::route('/{record}/edit'),
        ];
    }
}
