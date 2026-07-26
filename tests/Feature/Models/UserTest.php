<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;

it('casts role and status to enums', function () {
    $user = User::factory()->create([
        'role' => UserRole::Manager,
        'status' => UserStatus::Active,
    ]);

    expect($user->fresh()->role)->toBe(UserRole::Manager)
        ->and($user->fresh()->status)->toBe(UserStatus::Active);
});

it('knows which departments a manager covers', function () {
    $sales = Department::factory()->create();
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $manager->managedDepartments()->attach($sales);

    expect($manager->managedDepartments->pluck('id')->all())->toBe([$sales->id]);
});

it('lets owners and admins see everything but not staff or managers', function () {
    expect(User::factory()->create(['role' => UserRole::Owner])->canSeeEverything())->toBeTrue()
        ->and(User::factory()->create(['role' => UserRole::Admin])->canSeeEverything())->toBeTrue()
        ->and(User::factory()->create(['role' => UserRole::Manager])->canSeeEverything())->toBeFalse()
        ->and(User::factory()->create(['role' => UserRole::Staff])->canSeeEverything())->toBeFalse();
});
