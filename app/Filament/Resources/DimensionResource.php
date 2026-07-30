<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\RestrictsMutationsToAdmin;
use App\Filament\Resources\DimensionResource\Pages\CreateDimension;
use App\Filament\Resources\DimensionResource\Pages\EditDimension;
use App\Filament\Resources\DimensionResource\Pages\ListDimensions;
use App\Filament\Resources\DimensionResource\RelationManagers\ValuesRelationManager;
use App\Models\Dimension;
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
use Illuminate\Database\Eloquent\Model;

class DimensionResource extends Resource
{
    use RestrictsMutationsToAdmin;

    protected static ?string $model = Dimension::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    /**
     * `group_by` is capped at 64 characters in the reports API, and `dimension:` costs 10 of
     * those before the key even starts, leaving 54. A key saved past that bound would be valid
     * here and permanently unusable as `group_by=dimension:<key>` in a report.
     */
    private const MAX_KEY_LENGTH = 54;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(self::MAX_KEY_LENGTH)
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Toggle::make('is_required')
                ->helperText(__('filament.dimension.is_required_help')),
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
                TextColumn::make('key')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('is_required')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort')
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->visible(fn (Model $record): bool => static::canEdit($record)),
                DeleteAction::make()->visible(fn (Model $record): bool => static::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn (): bool => static::canDeleteAny()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDimensions::route('/'),
            'create' => CreateDimension::route('/create'),
            'edit' => EditDimension::route('/{record}/edit'),
        ];
    }
}
