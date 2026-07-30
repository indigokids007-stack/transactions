<?php

use App\Enums\UserRole;
use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\DepartmentResource\Pages\CreateDepartment;
use App\Filament\Resources\DepartmentResource\Pages\EditDepartment;
use App\Filament\Resources\DepartmentResource\Pages\ListDepartments;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

it('lets an admin create a department', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(CreateDepartment::class)
        ->fillForm(['name' => 'Moliya'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Department::where('name', 'Moliya')->exists())->toBeTrue();
});

it('lets an admin edit a department', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $department = Department::factory()->create(['name' => 'Sotuv']);

    Livewire::test(EditDepartment::class, ['record' => $department->getKey()])
        ->fillForm(['name' => 'Marketing'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($department->fresh()->name)->toBe('Marketing');
});

it('lets an admin delete a department through the table action', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $department = Department::factory()->create();

    Livewire::test(ListDepartments::class)
        ->assertTableActionVisible('delete', $department)
        ->callTableAction('delete', $department);

    expect(Department::whereKey($department->id)->exists())->toBeFalse();
});

it('forbids an owner from opening the create page directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))
        ->get(DepartmentResource::getUrl('create'))
        ->assertForbidden();
});

it('forbids an owner from opening the edit page directly', function () {
    $department = Department::factory()->create();

    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))
        ->get(DepartmentResource::getUrl('edit', ['record' => $department]))
        ->assertForbidden();
});

it('hides the create action from an owner and refuses it even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));

    Livewire::test(ListDepartments::class)
        ->assertActionHidden('create')
        ->mountAction('create')
        // Naming the schema explicitly: with the action disabled, `getMountedActionSchemaName()`
        // is null, so the generic `fillForm()`/`setActionData()` fallback would land on whatever
        // schema `getDefaultTestingSchemaName()` guesses instead of the (still cached) action
        // schema — not the no-op it looks like.
        ->fillForm(['name' => 'Owner Attempt'], 'mountedActionSchema0')
        ->callMountedAction();

    expect(Department::count())->toBe(0);
});

it('hides the edit and delete row actions from an owner and refuses them even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $department = Department::factory()->create(['name' => 'Original']);

    Livewire::test(ListDepartments::class)
        ->assertTableActionHidden('edit', $department)
        ->assertTableActionHidden('delete', $department)
        ->mountTableAction('edit', $department)
        ->setTableActionData(['name' => 'Tampered'])
        ->callMountedTableAction();

    expect($department->fresh()->name)->toBe('Original');

    Livewire::test(ListDepartments::class)
        ->mountTableAction('delete', $department)
        ->callMountedTableAction();

    expect(Department::whereKey($department->id)->exists())->toBeTrue();
});

it('hides the bulk delete action from an owner and refuses it even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $department = Department::factory()->create();

    Livewire::test(ListDepartments::class)
        ->assertTableBulkActionHidden('delete')
        ->mountTableBulkAction('delete', [$department])
        ->callMountedTableBulkAction();

    expect(Department::whereKey($department->id)->exists())->toBeTrue();
});

it('lets a department carry a multi select of managers', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    Livewire::test(CreateDepartment::class)
        ->fillForm(['name' => 'Xizmat', 'managers' => [$manager->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $department = Department::where('name', 'Xizmat')->sole();

    expect($department->managers->pluck('id')->all())->toBe([$manager->id]);
});
