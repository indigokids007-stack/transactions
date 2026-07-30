<?php

use App\Enums\RevisionAction;
use App\Enums\UserRole;
use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use App\Filament\Resources\TransactionResource\RelationManagers\RevisionsRelationManager;
use App\Models\Transaction;
use App\Models\TransactionRevision;
use App\Models\User;
use Livewire\Livewire;

it('registers no create or edit page at all', function () {
    expect(TransactionResource::hasPage('create'))->toBeFalse()
        ->and(TransactionResource::hasPage('edit'))->toBeFalse();
});

it('404s on the edit URL shape for either an admin or an owner, since no such route exists', function () {
    $transaction = Transaction::factory()->create();

    foreach ([UserRole::Admin, UserRole::Owner] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get("/admin/transactions/{$transaction->id}/edit")
            ->assertNotFound();
    }
});

it('registers no create, edit or delete action on the list table, for an admin either', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $transaction = Transaction::factory()->create(['note' => 'Original']);

    Livewire::test(ListTransactions::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit', record: $transaction)
        ->assertTableActionDoesNotExist('delete', record: $transaction)
        ->assertTableBulkActionDoesNotExist('delete')
        ->mountTableAction('edit', $transaction);

    expect($transaction->fresh()->note)->toBe('Original')
        ->and(Transaction::count())->toBe(1);
});

it('lets an owner view the transaction list and a single transaction', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $transaction = Transaction::factory()->create();

    Livewire::test(ListTransactions::class)->assertCanSeeTableRecords([$transaction]);

    Livewire::test(ViewTransaction::class, ['record' => $transaction->getKey()])
        ->assertSuccessful();
});

it('filters the transaction list the way the API filters it', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $matching = Transaction::factory()->create(['currency' => 'USD']);
    $other = Transaction::factory()->create(['currency' => 'UZS']);

    Livewire::test(ListTransactions::class)
        ->filterTable('currency', 'USD')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

it('shows the revision history read only, with no mutating action, for an admin and an owner', function () {
    foreach ([UserRole::Admin, UserRole::Owner] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $transaction = Transaction::factory()->create();
        $revision = TransactionRevision::create([
            'transaction_id' => $transaction->id,
            'action' => RevisionAction::Updated,
            'actor_id' => $transaction->user_id,
            'snapshot' => ['note' => 'edited'],
        ]);

        Livewire::test(RevisionsRelationManager::class, [
            'ownerRecord' => $transaction,
            'pageClass' => ViewTransaction::class,
        ])
            ->assertCanSeeTableRecords([$revision])
            ->assertTableActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete');
    }
});

it('denies create, edit and delete at the resource authorization level regardless of role', function () {
    $transaction = Transaction::factory()->create();

    foreach ([UserRole::Admin, UserRole::Owner] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));

        expect(TransactionResource::canCreate())->toBeFalse()
            ->and(TransactionResource::canEdit($transaction))->toBeFalse()
            ->and(TransactionResource::canDelete($transaction))->toBeFalse()
            ->and(TransactionResource::canDeleteAny())->toBeFalse();
    }
});
