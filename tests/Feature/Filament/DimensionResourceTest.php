<?php

use App\Enums\UserRole;
use App\Filament\Resources\DimensionResource;
use App\Filament\Resources\DimensionResource\Pages\CreateDimension;
use App\Filament\Resources\DimensionResource\Pages\EditDimension;
use App\Filament\Resources\DimensionResource\Pages\ListDimensions;
use App\Filament\Resources\DimensionResource\RelationManagers\ValuesRelationManager;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\User;
use Livewire\Livewire;

it('lets an admin create a dimension', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(CreateDimension::class)
        ->fillForm(['key' => 'project', 'name' => 'Loyiha', 'is_required' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $dimension = Dimension::where('key', 'project')->sole();

    expect($dimension->is_required)->toBeTrue();
});

it('refuses a dimension key longer than the reports API can group by', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(CreateDimension::class)
        ->fillForm(['key' => str_repeat('k', 55), 'name' => 'Uzun'])
        ->call('create')
        ->assertHasFormErrors(['key']);

    expect(Dimension::where('name', 'Uzun')->exists())->toBeFalse();
});

it('accepts a dimension key at exactly the reports API limit', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(CreateDimension::class)
        ->fillForm(['key' => str_repeat('k', 54), 'name' => 'Chegara'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Dimension::where('name', 'Chegara')->exists())->toBeTrue();
});

it('lets an admin edit and delete a dimension', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $dimension = Dimension::factory()->create(['name' => 'Original']);

    Livewire::test(EditDimension::class, ['record' => $dimension->getKey()])
        ->fillForm(['name' => 'Yangilangan'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dimension->fresh()->name)->toBe('Yangilangan');

    Livewire::test(ListDimensions::class)->callTableAction('delete', $dimension);

    expect(Dimension::whereKey($dimension->id)->exists())->toBeFalse();
});

it('forbids an owner from opening the dimension create and edit pages directly', function () {
    $dimension = Dimension::factory()->create();
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get(DimensionResource::getUrl('create'))->assertForbidden();
    $this->actingAs($owner)->get(DimensionResource::getUrl('edit', ['record' => $dimension]))->assertForbidden();
});

it('hides mutating dimension actions from an owner and refuses them even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $dimension = Dimension::factory()->create(['name' => 'Original']);

    Livewire::test(ListDimensions::class)
        ->assertActionHidden('create')
        ->assertTableActionHidden('edit', $dimension)
        ->assertTableActionHidden('delete', $dimension)
        ->mountTableAction('edit', $dimension)
        ->setTableActionData(['name' => 'Tampered'])
        ->callMountedTableAction();

    expect($dimension->fresh()->name)->toBe('Original');
});

it('lets an admin manage dimension values through the relation manager', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $dimension = Dimension::factory()->create();

    Livewire::test(ValuesRelationManager::class, [
        'ownerRecord' => $dimension,
        'pageClass' => EditDimension::class,
    ])
        ->assertTableActionVisible('create')
        ->callTableAction('create', data: ['name' => 'Naqd', 'is_active' => true, 'sort' => 0]);

    expect($dimension->values()->where('name', 'Naqd')->exists())->toBeTrue();
});

it('hides dimension value mutations from an owner and refuses them even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $dimension = Dimension::factory()->create();
    $value = DimensionValue::factory()->for($dimension)->create(['name' => 'Original']);

    Livewire::test(ValuesRelationManager::class, [
        'ownerRecord' => $dimension,
        'pageClass' => EditDimension::class,
    ])
        ->assertTableActionHidden('create')
        ->assertTableActionHidden('edit', $value)
        ->assertTableActionHidden('delete', $value)
        ->mountTableAction('create')
        // `setTableActionData()` resolves its target schema through
        // `getDefaultTestingSchemaName()`, which (with no action actually mounted, since it was
        // disabled) falls back to the table's *filters* form instead of the action's own — so
        // this names the mounted action's schema explicitly rather than fill the wrong one.
        ->fillForm(['name' => 'Owner Attempt', 'is_active' => true, 'sort' => 0], 'mountedActionSchema0')
        ->callMountedTableAction();

    expect($dimension->values()->where('name', 'Owner Attempt')->exists())->toBeFalse();
});
