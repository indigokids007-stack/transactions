<?php

use App\Enums\UserRole;
use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

it('lets an admin flip the registration toggle', function () {
    Setting::put('registration_open', true);
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(ManageSettings::class)
        ->assertActionVisible('save')
        ->fillForm(['registration_open' => false])
        ->callAction('save');

    expect(Setting::get('registration_open'))->toBeFalse();
});

it('shows the current setting to an owner but refuses the save action even mounted directly', function () {
    Setting::put('registration_open', true);
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));

    Livewire::test(ManageSettings::class)
        ->assertFormFieldIsDisabled('registration_open')
        ->assertActionHidden('save')
        // `setActionData()` targets the mounted action's own schema, which the `save` action
        // does not have — the toggle lives on the page's `form`, filled at `mount()`. Filling
        // it here, before mounting a hidden (and therefore never actually mounted) action, is
        // what makes the assertion below meaningful: if the `visible()` guard were removed,
        // this is the value `save()` would read and persist.
        ->fillForm(['registration_open' => false])
        ->mountAction('save')
        ->callMountedAction();

    expect(Setting::get('registration_open'))->toBeTrue();
});

it('defaults the toggle to open when no setting has been stored yet', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(ManageSettings::class)
        ->assertFormSet(['registration_open' => true]);
});
