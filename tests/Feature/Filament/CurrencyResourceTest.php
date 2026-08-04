<?php

use App\Enums\UserRole;
use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\CurrencyResource\Pages\CreateCurrency;
use App\Filament\Resources\CurrencyResource\Pages\EditCurrency;
use App\Filament\Resources\CurrencyResource\Pages\ListCurrencies;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

it('lets an admin create a currency', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(CreateCurrency::class)
        ->fillForm(['code' => 'GBP', 'name' => 'British Pound', 'exponent' => 2])
        ->call('create')
        ->assertHasNoFormErrors();

    $currency = Currency::where('code', 'GBP')->sole();

    expect($currency->exponent)->toBe(2)
        ->and($currency->is_active)->toBeTrue();
});

it('refuses a currency code already taken', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Currency::factory()->create(['code' => 'GBP']);

    Livewire::test(CreateCurrency::class)
        ->fillForm(['code' => 'GBP', 'name' => 'Duplicate', 'exponent' => 2])
        ->call('create')
        ->assertHasFormErrors(['code']);

    expect(Currency::where('name', 'Duplicate')->exists())->toBeFalse();
});

it('lets an admin edit and delete a currency', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $currency = Currency::factory()->create(['code' => 'GBP', 'name' => 'Original']);

    Livewire::test(EditCurrency::class, ['record' => $currency->getKey()])
        ->fillForm(['name' => 'Updated'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($currency->fresh()->name)->toBe('Updated');

    Livewire::test(ListCurrencies::class)->callTableAction('delete', $currency);

    expect(Currency::whereKey($currency->id)->exists())->toBeFalse();
});

it('forbids an owner from opening the currency create and edit pages directly', function () {
    $currency = Currency::factory()->create();
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get(CurrencyResource::getUrl('create'))->assertForbidden();
    $this->actingAs($owner)->get(CurrencyResource::getUrl('edit', ['record' => $currency]))->assertForbidden();
});

it('hides mutating currency actions from an owner and refuses them even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $currency = Currency::factory()->create(['name' => 'Original']);

    Livewire::test(ListCurrencies::class)
        ->assertActionHidden('create')
        ->assertTableActionHidden('edit', $currency)
        ->assertTableActionHidden('delete', $currency)
        ->mountTableAction('edit', $currency)
        ->setTableActionData(['name' => 'Tampered'])
        ->callMountedTableAction();

    expect($currency->fresh()->name)->toBe('Original');
});

/**
 * Deleting a currency used to be indistinguishable from any other row in an admin's
 * eyes; nothing stopped one still in use from disappearing out from under its
 * transactions' formatting and validation. The database refuses now; the panel refuses
 * first and explains.
 */
it('refuses to delete a currency a transaction was recorded in', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $currency = Currency::factory()->create(['code' => 'GBP']);
    $transaction = Transaction::factory()->create(['currency' => 'GBP']);

    Livewire::test(ListCurrencies::class)
        ->callTableAction('delete', $currency)
        ->assertNotified(__('filament.delete_refused.title'));

    expect(Currency::whereKey($currency->id)->exists())->toBeTrue()
        ->and($transaction->fresh()->currency)->toBe('GBP');
});

it('still deletes a currency no transaction has recorded', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $currency = Currency::factory()->create();

    Livewire::test(ListCurrencies::class)->callTableAction('delete', $currency);

    expect(Currency::whereKey($currency->id)->exists())->toBeFalse();
});
