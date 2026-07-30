<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

it('lets an admin into the panel', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get('/admin')
        ->assertSuccessful();
});

it('lets an owner into the panel', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))
        ->get('/admin')
        ->assertSuccessful();
});

it('keeps staff and managers out of the panel', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))->get('/admin')->assertForbidden();
});

it('keeps an inactive admin out of the panel', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Pending]))
        ->get('/admin')
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Blocked]))
        ->get('/admin')
        ->assertForbidden();
});
