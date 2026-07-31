<?php

namespace App\Filament\Resources;

use App\Enums\CategoryAppliesTo;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\Concerns\RestrictsMutationsToAdmin;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CategoryResource extends Resource
{
    use RestrictsMutationsToAdmin;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('parent_id')
                ->label('Parent')
                ->relationship(
                    'parent',
                    'name',
                    fn (Builder $query, ?Category $record): Builder => $record
                        ? $query->whereKeyNot($record->id)
                        : $query,
                )
                ->searchable()
                ->preload(),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Select::make('applies_to')
                ->options(CategoryAppliesTo::class)
                ->default(CategoryAppliesTo::Both->value)
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
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—'),
                TextColumn::make('applies_to')
                    ->badge(),
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

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
