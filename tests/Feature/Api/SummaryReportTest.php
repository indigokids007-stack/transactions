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
 * The group rows folded back into one number per currency and type, which is what the
 * totals of the same report have to say.
 *
 * @param  array<int, array<string, mixed>>  $groups
 * @return array<string, array{amount_minor: int, count: int}>
 */
function foldedGroups(array $groups): array
{
    $folded = [];

    foreach ($groups as $group) {
        $bucket = "{$group['currency']}:{$group['type']}";
        $folded[$bucket] = [
            'amount_minor' => ($folded[$bucket]['amount_minor'] ?? 0) + $group['amount_minor'],
            'count' => ($folded[$bucket]['count'] ?? 0) + $group['count'],
        ];
    }

    ksort($folded);

    return $folded;
}

/**
 * A ledger wide enough that an aggregation cannot pass by accident: two people inside the
 * viewer's scope, two currencies, both types, two categories, a tagged and an untagged
 * row, a row on each end of the period, a row outside it, a soft deleted row, and a row
 * from a department the viewer does not manage. Every grouping it feeds splits at least
 * one currency and type into more than one group, so folding the groups back is a real
 * reconciliation rather than an identity.
 *
 * @return array<string, int>
 */
function seedLedger(User $viewer, Department $department): array
{
    $colleague = User::factory()->create(['department_id' => $department->id]);
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $chilonzor = DimensionValue::factory()->create(['dimension_id' => $branch->id, 'name' => 'Chilonzor']);
    $taksi = Category::factory()->create(['name' => 'Taksi']);
    $ovqat = Category::factory()->create(['name' => 'Ovqat']);

    $tagged = Transaction::factory()->for($viewer)->create([
        'department_id' => $department->id, 'category_id' => $taksi->id, 'type' => TransactionType::Expense,
        'amount_minor' => 120000, 'currency' => 'UZS', 'occurred_on' => today()->subDays(2),
    ]);
    $tagged->dimensionValues()->attach($chilonzor, ['dimension_id' => $branch->id]);

    Transaction::factory()->for($colleague)->create([
        'department_id' => $department->id, 'category_id' => $ovqat->id, 'type' => TransactionType::Expense,
        'amount_minor' => 30000, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($colleague)->create([
        'department_id' => $department->id, 'category_id' => $taksi->id, 'type' => TransactionType::Income,
        'amount_minor' => 900000, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($viewer)->create([
        'department_id' => $department->id, 'category_id' => $ovqat->id, 'type' => TransactionType::Expense,
        'amount_minor' => 1250, 'currency' => 'USD', 'occurred_on' => today()->subDay(),
    ]);
    Transaction::factory()->for($colleague)->create([
        'department_id' => $department->id, 'category_id' => $taksi->id, 'type' => TransactionType::Income,
        'amount_minor' => 4000, 'currency' => 'USD', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($viewer)->create([
        'department_id' => $department->id, 'category_id' => $ovqat->id, 'type' => TransactionType::Income,
        'amount_minor' => 500, 'currency' => 'USD', 'occurred_on' => today(),
    ]);

    Transaction::factory()->for($viewer)->create([
        'department_id' => $department->id, 'amount_minor' => 777777, 'currency' => 'UZS', 'occurred_on' => today()->subDays(3),
    ]);
    Transaction::factory()->for($colleague)->create([
        'department_id' => $department->id, 'amount_minor' => 888888, 'currency' => 'UZS', 'occurred_on' => today()->addDay(),
    ]);
    Transaction::factory()->for($colleague)->create([
        'department_id' => $department->id, 'amount_minor' => 555555, 'currency' => 'UZS', 'occurred_on' => today(),
    ])->delete();
    Transaction::factory()->create(['amount_minor' => 666666, 'currency' => 'UZS', 'occurred_on' => today()]);

    return ['USD:expense' => 1250, 'USD:income' => 4500, 'UZS:expense' => 150000, 'UZS:income' => 900000];
}

/**
 * The totals of a response keyed the way `foldedGroups()` keys the groups, so the two can
 * be compared as a whole rather than one number at a time.
 *
 * @param  array<int, array<string, mixed>>  $totals
 * @return array<string, array{amount_minor: int, count: int}>
 */
function totalsByBucket(array $totals): array
{
    $keyed = collect($totals)
        ->mapWithKeys(fn (array $total) => [
            "{$total['currency']}:{$total['type']}" => ['amount_minor' => $total['amount_minor'], 'count' => $total['count']],
        ])
        ->all();

    ksort($keyed);

    return $keyed;
}

/** @param  array<string, mixed>  $extra */
function summaryUrl(string $groupBy, array $extra = []): string
{
    return '/api/reports/summary?'.http_build_query(array_merge([
        'from' => today()->subDays(2)->toDateString(),
        'to' => today()->toDateString(),
        'group_by' => $groupBy,
    ], $extra));
}

it('totals income and expense per currency for the period', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense, 'amount_minor' => 120000, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense, 'amount_minor' => 1000, 'currency' => 'USD', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense, 'amount_minor' => 999, 'currency' => 'UZS', 'occurred_on' => today()->subMonths(2),
    ]);

    $response = $this->getJson('/api/reports/summary?from='.today()->startOfMonth()->toDateString()
        .'&to='.today()->toDateString().'&group_by=currency')->assertOk();

    $uzs = collect($response->json('totals'))->firstWhere('currency', 'UZS');

    expect($uzs['amount_minor'])->toBe(120000)
        ->and(collect($response->json('totals'))->firstWhere('currency', 'USD')['amount_minor'])->toBe(1000);
});

it('breaks down by category', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $taksi = Category::factory()->create(['name' => 'Taksi']);
    Transaction::factory()->for($user)->create([
        'category_id' => $taksi->id, 'amount_minor' => 5000, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);

    $this->getJson('/api/reports/summary?from='.today()->toDateString().'&to='.today()->toDateString().'&group_by=category')
        ->assertOk()
        ->assertJsonPath('groups.0.label', 'Taksi')
        ->assertJsonPath('groups.0.amount_minor', 5000);
});

it('breaks down by a dimension', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $chilonzor = DimensionValue::factory()->create(['dimension_id' => $branch->id, 'name' => 'Chilonzor']);
    $transaction = Transaction::factory()->for($user)->create([
        'amount_minor' => 7000, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    $transaction->dimensionValues()->attach($chilonzor, ['dimension_id' => $branch->id]);

    $this->getJson('/api/reports/summary?from='.today()->toDateString()
        .'&to='.today()->toDateString().'&group_by=dimension:branch')
        ->assertOk()
        ->assertJsonPath('groups.0.label', 'Chilonzor')
        ->assertJsonPath('groups.0.amount_minor', 7000);
});

it('groups by staff for a manager but only inside their departments', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $manager->managedDepartments()->attach($department);
    $inside = User::factory()->create(['department_id' => $department->id]);
    $outside = User::factory()->create();
    Transaction::factory()->for($inside)->create(['department_id' => $department->id, 'occurred_on' => today()]);
    Transaction::factory()->for($outside)->create(['occurred_on' => today()]);
    Sanctum::actingAs($manager);

    $this->getJson('/api/reports/summary?from='.today()->toDateString()
        .'&to='.today()->toDateString().'&group_by=user')
        ->assertOk()
        ->assertJsonCount(1, 'groups')
        ->assertJsonPath('groups.0.key', (string) $inside->id);
});

it('never sums different currencies into one number', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $category = Category::factory()->create();
    Transaction::factory()->for($user)->create(['category_id' => $category->id, 'amount_minor' => 100, 'currency' => 'UZS', 'occurred_on' => today()]);
    Transaction::factory()->for($user)->create(['category_id' => $category->id, 'amount_minor' => 100, 'currency' => 'USD', 'occurred_on' => today()]);

    $groups = $this->getJson('/api/reports/summary?from='.today()->toDateString()
        .'&to='.today()->toDateString().'&group_by=category')->assertOk()->json('groups');

    expect($groups)->toHaveCount(2);
});

it('keeps the groups of a currency and type adding up to its total', function (string $groupBy) {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $department->id]);
    $manager->managedDepartments()->attach($department);
    Sanctum::actingAs($manager);
    $expected = seedLedger($manager, $department);

    $response = $this->getJson(summaryUrl($groupBy))->assertOk();
    $totals = totalsByBucket($response->json('totals'));

    expect(collect($totals)->map(fn (array $total) => $total['amount_minor'])->all())->toBe($expected)
        ->and(foldedGroups($response->json('groups')))->toBe($totals)
        ->and(count($response->json('groups')))->toBeGreaterThan(count($totals));
})->with(['category', 'user', 'dimension:branch']);

it('never counts a soft deleted transaction', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    Transaction::factory()->for($user)->create(['amount_minor' => 400, 'currency' => 'UZS', 'occurred_on' => today()]);
    Transaction::factory()->for($user)->create(['amount_minor' => 700, 'currency' => 'UZS', 'occurred_on' => today()])->delete();

    $response = $this->getJson(summaryUrl('category'))->assertOk();

    expect($response->json('totals'))->toHaveCount(1)
        ->and($response->json('totals.0.amount_minor'))->toBe(400)
        ->and($response->json('totals.0.count'))->toBe(1)
        ->and($response->json('groups.0.amount_minor'))->toBe(400);
});

it('keeps income and expense of one currency apart', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $category = Category::factory()->create();
    Transaction::factory()->for($user)->create([
        'category_id' => $category->id, 'type' => TransactionType::Expense,
        'amount_minor' => 300, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($user)->create([
        'category_id' => $category->id, 'type' => TransactionType::Income,
        'amount_minor' => 500, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);

    $response = $this->getJson(summaryUrl('category'))->assertOk();

    expect(collect($response->json('totals'))->firstWhere('type', 'expense')['amount_minor'])->toBe(300)
        ->and(collect($response->json('totals'))->firstWhere('type', 'income')['amount_minor'])->toBe(500)
        ->and($response->json('groups'))->toHaveCount(2);
});

it('formats every amount in the currency of its own row', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    Transaction::factory()->for($user)->create(['amount_minor' => 120000, 'currency' => 'UZS', 'occurred_on' => today()]);
    Transaction::factory()->for($user)->create(['amount_minor' => 1000, 'currency' => 'USD', 'occurred_on' => today()]);

    $totals = collect($this->getJson(summaryUrl('currency'))->assertOk()->json('totals'));

    expect($totals->firstWhere('currency', 'UZS')['amount'])->toBe('120000')
        ->and($totals->firstWhere('currency', 'USD')['amount'])->toBe('10.00');
});

it('counts a transaction once when it carries values for several dimensions', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $project = Dimension::factory()->create(['key' => 'project']);
    $chilonzor = DimensionValue::factory()->create(['dimension_id' => $branch->id, 'name' => 'Chilonzor']);
    $alpha = DimensionValue::factory()->create(['dimension_id' => $project->id, 'name' => 'Alpha']);

    $transaction = Transaction::factory()->for($user)->create([
        'amount_minor' => 900, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    $transaction->syncDimensionValues([$branch->id => $chilonzor->id, $project->id => $alpha->id]);

    $groups = $this->getJson(summaryUrl('dimension:branch'))->assertOk()->json('groups');

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['amount_minor'])->toBe(900)
        ->and($groups[0]['count'])->toBe(1)
        ->and($groups[0]['label'])->toBe('Chilonzor');
});

it('reconciles when one dimension filters the rows and another groups them', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $project = Dimension::factory()->create(['key' => 'project']);
    $chilonzor = DimensionValue::factory()->create(['dimension_id' => $branch->id, 'name' => 'Chilonzor']);
    $yunusobod = DimensionValue::factory()->create(['dimension_id' => $branch->id, 'name' => 'Yunusobod']);
    $alpha = DimensionValue::factory()->create(['dimension_id' => $project->id, 'name' => 'Alpha']);
    $beta = DimensionValue::factory()->create(['dimension_id' => $project->id, 'name' => 'Beta']);

    $ledger = [
        [500, TransactionType::Expense, [$branch->id => $chilonzor->id, $project->id => $alpha->id]],
        [300, TransactionType::Expense, [$branch->id => $yunusobod->id, $project->id => $alpha->id]],
        [700, TransactionType::Expense, [$branch->id => $chilonzor->id, $project->id => $beta->id]],
        [200, TransactionType::Income, [$project->id => $alpha->id]],
        [900, TransactionType::Expense, []],
    ];

    foreach ($ledger as [$amount, $type, $values]) {
        Transaction::factory()->for($user)->create([
            'type' => $type, 'amount_minor' => $amount, 'currency' => 'UZS', 'occurred_on' => today(),
        ])->syncDimensionValues($values);
    }

    $response = $this->getJson(summaryUrl('dimension:branch', ['dimension' => ['project' => $alpha->id]]))->assertOk();
    $groups = collect($response->json('groups'));

    expect(totalsByBucket($response->json('totals')))->toBe([
        'UZS:expense' => ['amount_minor' => 800, 'count' => 2],
        'UZS:income' => ['amount_minor' => 200, 'count' => 1],
    ])
        ->and($groups)->toHaveCount(3)
        ->and($groups->firstWhere('label', 'Chilonzor')['amount_minor'])->toBe(500)
        ->and($groups->firstWhere('label', 'Yunusobod')['amount_minor'])->toBe(300)
        ->and($groups->firstWhere('key', null)['amount_minor'])->toBe(200)
        ->and(foldedGroups($response->json('groups')))->toBe(totalsByBucket($response->json('totals')));
});

it('reports a transaction without a value for the dimension under an unassigned bucket', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $chilonzor = DimensionValue::factory()->create(['dimension_id' => $branch->id, 'name' => 'Chilonzor']);

    $tagged = Transaction::factory()->for($user)->create([
        'amount_minor' => 500, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    $tagged->dimensionValues()->attach($chilonzor, ['dimension_id' => $branch->id]);
    Transaction::factory()->for($user)->create(['amount_minor' => 300, 'currency' => 'UZS', 'occurred_on' => today()]);

    $response = $this->getJson(summaryUrl('dimension:branch'))->assertOk();
    $groups = collect($response->json('groups'));
    $unassigned = $groups->firstWhere('key', null);

    expect($groups)->toHaveCount(2)
        ->and($groups->firstWhere('key', (string) $chilonzor->id)['amount_minor'])->toBe(500)
        ->and($unassigned['label'])->toBe(__('reports.unassigned'))
        ->and($unassigned['amount_minor'])->toBe(300)
        ->and($unassigned['count'])->toBe(1)
        ->and($response->json('totals.0.amount_minor'))->toBe(800);
});

it('orders the groups of a currency and type from the largest amount down', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $amounts = ['Ovqat' => 300, 'Taksi' => 900, 'Aloqa' => 100, 'Ijara' => 500];

    foreach ($amounts as $name => $amount) {
        Transaction::factory()->for($user)->create([
            'category_id' => Category::factory()->create(['name' => $name])->id,
            'amount_minor' => $amount, 'currency' => 'UZS', 'occurred_on' => today(),
        ]);
    }

    $groups = $this->getJson(summaryUrl('category'))->assertOk()->json('groups');

    expect(collect($groups)->pluck('label')->all())->toBe(['Taksi', 'Ijara', 'Ovqat', 'Aloqa']);
});

it('keeps a manager scope grouped so the period still narrows their own records', function () {
    $sales = Department::factory()->create();
    $warehouse = Department::factory()->create();
    $manager = User::factory()->create(['role' => UserRole::Manager, 'department_id' => $warehouse->id]);
    $manager->managedDepartments()->attach($sales);
    $staff = User::factory()->create(['department_id' => $sales->id]);
    $stranger = User::factory()->create(['department_id' => $warehouse->id]);

    Transaction::factory()->for($staff)->create([
        'department_id' => $sales->id, 'amount_minor' => 10, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($staff)->create([
        'department_id' => $sales->id, 'amount_minor' => 20, 'currency' => 'UZS', 'occurred_on' => today()->subDays(9),
    ]);
    Transaction::factory()->for($manager)->create([
        'department_id' => $warehouse->id, 'amount_minor' => 4, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($manager)->create([
        'department_id' => $warehouse->id, 'amount_minor' => 8, 'currency' => 'UZS', 'occurred_on' => today()->subDays(9),
    ]);
    Transaction::factory()->for($stranger)->create([
        'department_id' => $warehouse->id, 'amount_minor' => 1000, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Sanctum::actingAs($manager);

    $response = $this->getJson(summaryUrl('user'))->assertOk();

    expect($response->json('totals'))->toHaveCount(1)
        ->and($response->json('totals.0.amount_minor'))->toBe(14)
        ->and($response->json('totals.0.count'))->toBe(2)
        ->and($response->json('groups'))->toHaveCount(2);
});

it('refuses to widen a report through a client supplied user_id', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    Transaction::factory()->for($user)->create(['amount_minor' => 10, 'currency' => 'UZS', 'occurred_on' => today()]);
    Transaction::factory()->for($stranger)->create(['amount_minor' => 999, 'currency' => 'UZS', 'occurred_on' => today()]);
    Sanctum::actingAs($user);

    $response = $this->getJson(summaryUrl('user').'&user_id='.$stranger->id)->assertOk();

    expect($response->json('totals'))->toBe([])
        ->and($response->json('groups'))->toBe([]);
});

it('counts the rows on both ends of the period and no others', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    Transaction::factory()->for($user)->create(['amount_minor' => 1, 'currency' => 'UZS', 'occurred_on' => today()->subDays(3)]);
    Transaction::factory()->for($user)->create(['amount_minor' => 10, 'currency' => 'UZS', 'occurred_on' => today()->subDays(2)]);
    Transaction::factory()->for($user)->create(['amount_minor' => 100, 'currency' => 'UZS', 'occurred_on' => today()]);
    Transaction::factory()->for($user)->create(['amount_minor' => 1000, 'currency' => 'UZS', 'occurred_on' => today()->addDay()]);

    $response = $this->getJson(summaryUrl('currency'))->assertOk();

    expect($response->json('totals.0.amount_minor'))->toBe(110)
        ->and($response->json('totals.0.count'))->toBe(2);
});

it('rejects a grouping it cannot resolve', function (string $groupBy) {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(summaryUrl($groupBy))
        ->assertStatus(422)
        ->assertJsonValidationErrors('group_by');
})->with([
    'department',
    'dimension',
    'dimension:nope',
    'transactions.id) as key, (select count(*) from users',
]);

it('refuses a guest and a user who is not active', function () {
    $this->getJson(summaryUrl('category'))->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create(['status' => UserStatus::Blocked]));

    $this->getJson(summaryUrl('category'))->assertForbidden();
});
