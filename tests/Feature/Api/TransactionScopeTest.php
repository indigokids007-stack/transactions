<?php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->sales = Department::factory()->create(['name' => 'Sales']);
    $this->warehouse = Department::factory()->create(['name' => 'Warehouse']);

    $this->salesStaff = User::factory()->create(['department_id' => $this->sales->id]);
    $this->warehouseStaff = User::factory()->create(['department_id' => $this->warehouse->id]);

    $this->salesTransaction = Transaction::factory()
        ->for($this->salesStaff)
        ->create(['department_id' => $this->sales->id]);
    $this->warehouseTransaction = Transaction::factory()
        ->for($this->warehouseStaff)
        ->create(['department_id' => $this->warehouse->id]);
});

it('shows a staff member only their own transactions', function () {
    Sanctum::actingAs($this->salesStaff);

    $this->getJson('/api/transactions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->salesTransaction->id);
});

it('shows a manager the transactions of the departments they manage', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $this->sales->id]);
    $manager->managedDepartments()->attach($this->sales);
    Sanctum::actingAs($manager);

    $this->getJson('/api/transactions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->salesTransaction->id);
});

it('shows an owner everything', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $this->getJson('/api/transactions')->assertOk()->assertJsonCount(2, 'data');
});

it('refuses to widen scope through a client supplied user_id', function () {
    Sanctum::actingAs($this->salesStaff);

    $this->getJson("/api/transactions?user_id={$this->warehouseStaff->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('refuses to widen scope through a client supplied department_id', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $manager->managedDepartments()->attach($this->sales);
    Sanctum::actingAs($manager);

    $this->getJson("/api/transactions?department_id={$this->warehouse->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('hides a single out of scope transaction behind 404', function () {
    Sanctum::actingAs($this->salesStaff);

    $this->getJson("/api/transactions/{$this->warehouseTransaction->id}/revisions")->assertStatus(404);
});

it('filters by period, type, category and dimension', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $old = Transaction::factory()->create(['occurred_on' => today()->subMonth()]);

    $this->getJson('/api/transactions?from='.today()->toDateString())
        ->assertOk()
        ->assertJsonMissing(['id' => $old->id]);
});
