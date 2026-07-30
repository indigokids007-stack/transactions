<?php

namespace App\Filament\Pages;

use App\Filament\Support\PanelUser;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.manage-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'registration_open' => Setting::get('registration_open', true),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('registration_open')
                    ->label(__('filament.settings.registration_open_label'))
                    ->helperText(__('filament.settings.registration_open_help'))
                    ->disabled(fn (): bool => ! PanelUser::isAdmin()),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament.settings.save_action'))
                ->visible(fn (): bool => PanelUser::isAdmin())
                ->action(function (): void {
                    $state = $this->form->getState();

                    Setting::put('registration_open', (bool) $state['registration_open']);

                    Notification::make()
                        ->success()
                        ->title(__('filament.settings.saved'))
                        ->send();
                }),
        ];
    }
}
