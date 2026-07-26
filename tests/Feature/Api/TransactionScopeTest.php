<?php

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Department;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * The ids of a listing, compared as a set so a test says exactly which rows crossed the
 * boundary rather than only how many did.
 *
 * @param  array<int, array<string, mixed>>  $data
 * @return array<int, int>
 */
function listedIds(array $data): array
{
    return collect($data)->pluck('id')->all();
}

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

it('shows a manager a record they wrote in a department they do not manage', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $this->sales->id]);
    $manager->managedDepartments()->attach($this->sales);
    $ownElsewhere = Transaction::factory()
        ->for($manager)
        ->create(['department_id' => $this->warehouse->id]);

    Sanctum::actingAs($manager);

    expect(listedIds($this->getJson('/api/transactions')->assertOk()->json('data')))
        ->toEqualCanonicalizing([$this->salesTransaction->id, $ownElsewhere->id]);

    $this->getJson("/api/transactions/{$ownElsewhere->id}/revisions")->assertOk();
    $this->getJson("/api/transactions/{$this->warehouseTransaction->id}/revisions")->assertStatus(404);
});

it('narrows every branch of the manager scope with the same filter', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $this->sales->id]);
    $manager->managedDepartments()->attach($this->sales);
    $ownIncome = Transaction::factory()
        ->for($manager)
        ->create(['department_id' => $this->warehouse->id, 'type' => TransactionType::Income]);

    Sanctum::actingAs($manager);

    expect(listedIds($this->getJson('/api/transactions?type=income')->assertOk()->json('data')))
        ->toBe([$ownIncome->id]);
});

it('gives a deactivated caller the same answer inside and outside their scope', function () {
    $blocked = User::factory()->create([
        'department_id' => $this->sales->id,
        'status' => UserStatus::Blocked,
    ]);
    $own = Transaction::factory()->for($blocked)->create(['department_id' => $this->sales->id]);

    Sanctum::actingAs($blocked);

    $this->getJson("/api/transactions/{$own->id}/revisions")->assertStatus(403);
    $this->getJson("/api/transactions/{$this->warehouseTransaction->id}/revisions")->assertStatus(403);
});

it('filters by type', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $income = Transaction::factory()->create(['type' => TransactionType::Income]);

    expect(listedIds($this->getJson('/api/transactions?type=income')->assertOk()->json('data')))
        ->toBe([$income->id]);
});

it('filters by currency', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $inDollars = Transaction::factory()->create(['currency' => 'USD']);

    expect(listedIds($this->getJson('/api/transactions?currency=USD')->assertOk()->json('data')))
        ->toBe([$inDollars->id]);
});

it('filters by a closed period', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));
    Transaction::query()->update(['occurred_on' => today()->subYear()]);

    $inside = Transaction::factory()->create(['occurred_on' => today()->subDays(3)]);
    Transaction::factory()->create(['occurred_on' => today()->subDays(9)]);
    Transaction::factory()->create(['occurred_on' => today()]);

    $window = '?from='.today()->subDays(5)->toDateString().'&to='.today()->subDay()->toDateString();

    expect(listedIds($this->getJson("/api/transactions{$window}")->assertOk()->json('data')))
        ->toBe([$inside->id]);
});

it('rejects a period that ends before it starts', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $this->getJson('/api/transactions?from='.today()->toDateString().'&to='.today()->subDay()->toDateString())
        ->assertStatus(422)
        ->assertJsonValidationErrors('to');
});

it('filters by a category and everything underneath it', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);
    $grandchild = Category::factory()->create(['parent_id' => $child->id]);
    $unrelated = Category::factory()->create();

    $onParent = Transaction::factory()->create(['category_id' => $parent->id]);
    $onChild = Transaction::factory()->create(['category_id' => $child->id]);
    $onGrandchild = Transaction::factory()->create(['category_id' => $grandchild->id]);
    Transaction::factory()->create(['category_id' => $unrelated->id]);

    expect(listedIds($this->getJson("/api/transactions?category_id={$parent->id}")->assertOk()->json('data')))
        ->toEqualCanonicalizing([$onParent->id, $onChild->id, $onGrandchild->id]);
});

it('filters by a dimension value', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $dimension = Dimension::factory()->create(['key' => 'branch']);
    $chosen = DimensionValue::factory()->for($dimension)->create();
    $other = DimensionValue::factory()->for($dimension)->create();

    $matching = Transaction::factory()->create();
    $matching->syncDimensionValues([$dimension->id => $chosen->id]);
    Transaction::factory()->create()->syncDimensionValues([$dimension->id => $other->id]);

    expect(listedIds($this->getJson("/api/transactions?dimension[branch]={$chosen->id}")->assertOk()->json('data')))
        ->toBe([$matching->id]);
});

it('matches a dimension value only under the dimension it was named with', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    Dimension::factory()->create(['key' => 'branch']);
    $project = Dimension::factory()->create(['key' => 'project']);
    $projectValue = DimensionValue::factory()->for($project)->create();

    Transaction::factory()->create()->syncDimensionValues([$project->id => $projectValue->id]);

    expect(listedIds($this->getJson("/api/transactions?dimension[branch]={$projectValue->id}")->assertOk()->json('data')))
        ->toBe([]);
});

it('rejects a dimension key that does not exist', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $value = DimensionValue::factory()->create();

    $this->getJson("/api/transactions?dimension[nowhere]={$value->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('dimension');
});

it('filters by period, type, category and dimension', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $old = Transaction::factory()->create(['occurred_on' => today()->subMonth()]);

    $this->getJson('/api/transactions?from='.today()->toDateString())
        ->assertOk()
        ->assertJsonMissing(['id' => $old->id]);
});
