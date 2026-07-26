<?php

use App\Enums\CategoryAppliesTo;
use App\Enums\RevisionAction;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Department;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lets a staff member edit their own transaction and logs a revision', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->for($user)->create(['amount_minor' => 1000, 'currency' => 'UZS']);

    $this->patchJson("/api/transactions/{$transaction->id}", ['amount' => '2000'])
        ->assertOk()
        ->assertJsonPath('data.amount_minor', 2000);

    expect($transaction->fresh()->amount_minor)->toBe(2000)
        ->and($transaction->revisions()->where('action', RevisionAction::Updated)->count())->toBe(1);
});

it('forbids editing someone else transaction', function () {
    Sanctum::actingAs(User::factory()->create());
    $foreign = Transaction::factory()->create();

    $this->patchJson("/api/transactions/{$foreign->id}", ['amount' => '2000'])->assertStatus(404);
});

it('lets an admin edit any transaction', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $foreign = Transaction::factory()->create();

    $this->patchJson("/api/transactions/{$foreign->id}", ['note' => 'fixed'])->assertOk();
});

it('forbids an owner from writing', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $foreign = Transaction::factory()->create();

    $this->patchJson("/api/transactions/{$foreign->id}", ['note' => 'nope'])->assertStatus(403);
});

it('applies a full edit and carries it into the revision snapshot', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $dimension = Dimension::factory()->create();
    $before = DimensionValue::factory()->for($dimension)->create();
    $after = DimensionValue::factory()->for($dimension)->create();
    $category = Category::factory()->create(['applies_to' => CategoryAppliesTo::Income]);

    $transaction = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'amount_minor' => 1000,
        'currency' => 'UZS',
    ]);
    $transaction->syncDimensionValues([$dimension->id => $before->id]);

    $this->patchJson("/api/transactions/{$transaction->id}", [
        'type' => 'income',
        'amount' => '12.34',
        'currency' => 'USD',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
        'note' => 'tuzatildi',
        'dimension_values' => [$dimension->id => $after->id],
    ])->assertOk()->assertJsonPath('data.amount_minor', 1234);

    $fresh = $transaction->fresh();

    expect($fresh->type)->toBe(TransactionType::Income)
        ->and($fresh->amount_minor)->toBe(1234)
        ->and($fresh->currency)->toBe('USD')
        ->and($fresh->occurred_on->toDateString())->toBe(today()->toDateString())
        ->and($fresh->category_id)->toBe($category->id)
        ->and($fresh->note)->toBe('tuzatildi')
        ->and($fresh->dimensionValues->pluck('id')->all())->toBe([$after->id]);

    $snapshot = $transaction->revisions()->sole()->snapshot;

    expect($snapshot['amount_minor'])->toBe(1234)
        ->and($snapshot['currency'])->toBe('USD')
        ->and($snapshot['dimension_values'])->toBe([$dimension->id => $after->id]);
});

it('validates an edited category against the type already stored', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->for($user)->create(['type' => TransactionType::Income]);
    $expenseOnly = Category::factory()->create(['applies_to' => CategoryAppliesTo::Expense]);

    $this->patchJson("/api/transactions/{$transaction->id}", ['category_id' => $expenseOnly->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');

    expect($transaction->fresh()->category_id)->not->toBe($expenseOnly->id);
});

it('refuses a currency change that does not carry the amount it applies to', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->for($user)->create(['amount_minor' => 1000, 'currency' => 'UZS']);

    $this->patchJson("/api/transactions/{$transaction->id}", ['currency' => 'USD'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');

    expect($transaction->fresh()->currency)->toBe('UZS');
});

it('forbids an owner from editing even their own transaction', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Sanctum::actingAs($owner);
    $own = Transaction::factory()->for($owner)->create();

    $this->patchJson("/api/transactions/{$own->id}", ['note' => 'nope'])->assertStatus(403);

    expect($own->fresh()->note)->toBeNull();
});

it('forbids a manager from editing a record they can see but did not write', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $department->id]);
    $manager->managedDepartments()->attach($department);
    $staff = User::factory()->create(['department_id' => $department->id]);
    $colleagues = Transaction::factory()->for($staff)->create(['department_id' => $department->id]);

    Sanctum::actingAs($manager);

    $this->getJson('/api/transactions')->assertOk()->assertJsonPath('data.0.id', $colleagues->id);
    $this->patchJson("/api/transactions/{$colleagues->id}", ['note' => 'nope'])->assertStatus(403);

    expect($colleagues->fresh()->note)->toBeNull();
});

it('ignores a user_id and a department_id sent in an edit', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->for($user)->create(['department_id' => $department->id]);

    $stranger = User::factory()->create();
    $otherDepartment = Department::factory()->create();

    $this->patchJson("/api/transactions/{$transaction->id}", [
        'note' => 'edited',
        'user_id' => $stranger->id,
        'department_id' => $otherDepartment->id,
    ])->assertOk();

    expect($transaction->fresh()->user_id)->toBe($user->id)
        ->and($transaction->fresh()->department_id)->toBe($department->id)
        ->and($transaction->fresh()->note)->toBe('edited');
});

it('soft deletes and logs the deletion', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->for($user)->create();

    $this->deleteJson("/api/transactions/{$transaction->id}")->assertNoContent();

    expect(Transaction::count())->toBe(0)
        ->and(Transaction::withTrashed()->sole()->revisions()->where('action', RevisionAction::Deleted)->count())->toBe(1);
});

it('forbids an owner from deleting even their own transaction', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Sanctum::actingAs($owner);
    Transaction::factory()->for($owner)->create();

    $this->deleteJson('/api/transactions/'.Transaction::sole()->id)->assertStatus(403);

    expect(Transaction::count())->toBe(1);
});

it('forbids a manager from deleting a colleague record', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $department->id]);
    $manager->managedDepartments()->attach($department);
    $staff = User::factory()->create(['department_id' => $department->id]);
    $colleagues = Transaction::factory()->for($staff)->create(['department_id' => $department->id]);

    Sanctum::actingAs($manager);

    $this->deleteJson("/api/transactions/{$colleagues->id}")->assertStatus(403);

    expect(Transaction::count())->toBe(1);
});

it('snapshots the transaction as it stood before the delete', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->for($user)->create();

    $this->deleteJson("/api/transactions/{$transaction->id}")->assertNoContent();

    $revision = Transaction::withTrashed()->sole()->revisions()->sole();

    expect($revision->action)->toBe(RevisionAction::Deleted)
        ->and($revision->snapshot['deleted_at'])->toBeNull();
});

it('returns the revision history of a transaction', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->for($user)->create();

    $this->patchJson("/api/transactions/{$transaction->id}", ['note' => 'edited'])->assertOk();

    $this->getJson("/api/transactions/{$transaction->id}/revisions")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'updated');
});
