<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Concerns\RestrictsMutationsToAdmin;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Support\PanelUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Users are never created here: they arrive through the bot. The panel's one write path
 * onto this model is activating a pending user, which is also where role and department
 * are set, so admins never juggle a separate edit screen for the same decision.
 */
class UserResource extends Resource
{
    use RestrictsMutationsToAdmin;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    /**
     * Overrides the trait: nobody creates a user through the panel, admin included — they
     * only ever arrive through the bot.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('telegram_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('username')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(UserStatus::class)
                    ->default(UserStatus::Pending->value),
            ])
            ->recordActions([
                self::activateAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::activateBulkAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }

    private static function activateAction(): Action
    {
        return Action::make('activate')
            ->label(__('filament.user.activate_action'))
            ->modalHeading(__('filament.user.activate_action_modal_heading'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (User $record): bool => PanelUser::isAdmin() && $record->status === UserStatus::Pending)
            ->schema(self::activationSchema())
            ->fillForm(fn (User $record): array => [
                'role' => $record->role->value,
                'department_id' => $record->department_id,
            ])
            ->action(function (User $record, array $data): void {
                $record->update([
                    'status' => UserStatus::Active,
                    'role' => $data['role'],
                    'department_id' => $data['department_id'],
                ]);
            });
    }

    private static function activateBulkAction(): BulkAction
    {
        return BulkAction::make('activate')
            ->label(__('filament.user.activate_action'))
            ->modalHeading(__('filament.user.activate_action_modal_heading'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (): bool => PanelUser::isAdmin())
            ->schema(self::activationSchema())
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $records->each(function (User $record) use ($data): void {
                    if ($record->status !== UserStatus::Pending) {
                        return;
                    }

                    $record->update([
                        'status' => UserStatus::Active,
                        'role' => $data['role'],
                        'department_id' => $data['department_id'],
                    ]);
                });
            });
    }

    /** @return array<int, Component> */
    private static function activationSchema(): array
    {
        return [
            Select::make('role')
                ->options(UserRole::class)
                ->default(UserRole::Staff->value)
                ->required(),
            Select::make('department_id')
                ->relationship('department', 'name')
                ->searchable()
                ->preload(),
        ];
    }
}
