<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

it('defaults the list filter to pending users', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);
    $pending = User::factory()->create(['status' => UserStatus::Pending]);
    $active = User::factory()->create(['status' => UserStatus::Active]);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$active, $admin]);
});

it('lets an admin activate a pending user and set their role and department', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $department = Department::factory()->create();
    $pending = User::factory()->create(['status' => UserStatus::Pending, 'role' => UserRole::Staff]);

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('activate', $pending)
        ->callTableAction('activate', $pending, data: [
            'role' => UserRole::Manager->value,
            'department_id' => $department->id,
        ]);

    $fresh = $pending->fresh();

    expect($fresh->status)->toBe(UserStatus::Active)
        ->and($fresh->role)->toBe(UserRole::Manager)
        ->and($fresh->department_id)->toBe($department->id);
});

it('lets an admin activate a batch of pending users', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $department = Department::factory()->create();
    $first = User::factory()->create(['status' => UserStatus::Pending]);
    $second = User::factory()->create(['status' => UserStatus::Pending]);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('activate', [$first, $second], data: [
            'role' => UserRole::Staff->value,
            'department_id' => $department->id,
        ]);

    expect($first->fresh()->status)->toBe(UserStatus::Active)
        ->and($second->fresh()->status)->toBe(UserStatus::Active);
});

it('hides the activate action from an already active user', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $active = User::factory()->create(['status' => UserStatus::Active]);

    Livewire::test(ListUsers::class)
        ->filterTable('status', null)
        ->assertTableActionHidden('activate', $active);
});

it('hides the activate action from an owner and refuses it even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $pending = User::factory()->create(['status' => UserStatus::Pending, 'role' => UserRole::Staff]);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('activate', $pending)
        ->mountTableAction('activate', $pending)
        ->callMountedTableAction();

    $fresh = $pending->fresh();

    expect($fresh->status)->toBe(UserStatus::Pending)
        ->and($fresh->role)->toBe(UserRole::Staff);
});

it('hides the bulk activate action from an owner and refuses it even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $pending = User::factory()->create(['status' => UserStatus::Pending]);

    Livewire::test(ListUsers::class)
        ->assertTableBulkActionHidden('activate')
        ->mountTableBulkAction('activate', [$pending])
        ->callMountedTableBulkAction();

    expect($pending->fresh()->status)->toBe(UserStatus::Pending);
});

it('lets an admin edit an already active user\'s role and department', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $department = Department::factory()->create();
    $active = User::factory()->create(['status' => UserStatus::Active, 'role' => UserRole::Staff]);

    Livewire::test(ListUsers::class)
        ->filterTable('status', null)
        ->assertTableActionVisible('edit', $active)
        ->callTableAction('edit', $active, data: [
            'role' => UserRole::Manager->value,
            'department_id' => $department->id,
        ]);

    $fresh = $active->fresh();

    expect($fresh->status)->toBe(UserStatus::Active)
        ->and($fresh->role)->toBe(UserRole::Manager)
        ->and($fresh->department_id)->toBe($department->id);
});

it('hides the edit action from a pending user, who is activated instead', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $pending = User::factory()->create(['status' => UserStatus::Pending]);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('edit', $pending);
});

it('hides the edit action from an owner and refuses it even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $department = Department::factory()->create();
    $active = User::factory()->create(['status' => UserStatus::Active, 'role' => UserRole::Staff]);

    Livewire::test(ListUsers::class)
        ->filterTable('status', null)
        ->assertTableActionHidden('edit', $active)
        ->mountTableAction('edit', $active)
        ->callMountedTableAction();

    $fresh = $active->fresh();

    expect($fresh->role)->toBe(UserRole::Staff)
        ->and($fresh->department_id)->toBeNull();
});
