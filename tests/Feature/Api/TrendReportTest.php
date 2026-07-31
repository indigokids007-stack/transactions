<?php

use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

function trendUrl(string $interval, DateTimeInterface $from, DateTimeInterface $to): string
{
    return '/api/reports/trend?'.http_build_query([
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
        'interval' => $interval,
    ]);
}

it('returns one point per day with amounts per currency', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    Transaction::factory()->for($user)->create(['amount_minor' => 100, 'currency' => 'UZS', 'occurred_on' => today()]);
    Transaction::factory()->for($user)->create(['amount_minor' => 200, 'currency' => 'UZS', 'occurred_on' => today()]);
    Transaction::factory()->for($user)->create(['amount_minor' => 300, 'currency' => 'UZS', 'occurred_on' => today()->subDay()]);

    $points = $this->getJson('/api/reports/trend?from='.today()->subDay()->toDateString()
        .'&to='.today()->toDateString().'&interval=day')->assertOk()->json('points');

    expect($points)->toHaveCount(2)
        ->and(collect($points)->firstWhere('period', today()->toDateString())['amount_minor'])->toBe(300);
});

it('groups by month', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    Transaction::factory()->for($user)->create(['amount_minor' => 100, 'currency' => 'UZS', 'occurred_on' => today()->startOfMonth()]);
    Transaction::factory()->for($user)->create(['amount_minor' => 100, 'currency' => 'UZS', 'occurred_on' => today()->startOfMonth()->addDays(5)]);

    $points = $this->getJson('/api/reports/trend?from='.today()->startOfMonth()->toDateString()
        .'&to='.today()->endOfMonth()->toDateString().'&interval=month')->assertOk()->json('points');

    expect($points)->toHaveCount(1)
        ->and($points[0]['amount_minor'])->toBe(200);
});

it('splits one period by currency and type', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense, 'amount_minor' => 100, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income, 'amount_minor' => 700, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense, 'amount_minor' => 250, 'currency' => 'USD', 'occurred_on' => today(),
    ]);

    $points = collect($this->getJson(trendUrl('day', today(), today()))->assertOk()->json('points'));

    expect($points)->toHaveCount(3)
        ->and($points->firstWhere('currency', 'USD')['amount'])->toBe('2.50')
        ->and($points->where('currency', 'UZS')->firstWhere('type', 'expense')['amount_minor'])->toBe(100)
        ->and($points->where('currency', 'UZS')->firstWhere('type', 'income')['amount_minor'])->toBe(700)
        ->and($points->every(fn (array $point) => $point['period'] === today()->toDateString()))->toBeTrue();
});

it('cuts a week at its first day', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $monday = CarbonImmutable::parse('2026-07-20');
    Transaction::factory()->for($user)->create(['amount_minor' => 100, 'currency' => 'UZS', 'occurred_on' => $monday]);
    Transaction::factory()->for($user)->create(['amount_minor' => 200, 'currency' => 'UZS', 'occurred_on' => $monday->addDays(6)]);
    Transaction::factory()->for($user)->create(['amount_minor' => 400, 'currency' => 'UZS', 'occurred_on' => $monday->addDays(7)]);

    $points = $this->getJson(trendUrl('week', $monday, $monday->addDays(7)))->assertOk()->json('points');

    expect($points)->toHaveCount(2)
        ->and($points[0])->toMatchArray(['period' => '2026-07-20', 'amount_minor' => 300, 'count' => 2])
        ->and($points[1])->toMatchArray(['period' => '2026-07-27', 'amount_minor' => 400, 'count' => 1]);
});

it('orders its points by period however the rows were written', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $written = [5, 0, 8, 3, 1, 7, 2, 6, 4];

    foreach ($written as $daysAgo) {
        Transaction::factory()->for($user)->create([
            'amount_minor' => 100, 'currency' => 'UZS', 'occurred_on' => today()->subDays($daysAgo),
        ]);
    }

    $points = $this->getJson(trendUrl('day', today()->subDays(8), today()))->assertOk()->json('points');
    $expected = collect($written)->sortDesc()->map(fn (int $daysAgo) => today()->subDays($daysAgo)->toDateString())->values()->all();

    expect(collect($points)->pluck('period')->all())->toBe($expected);
});

it('never counts a soft deleted transaction or one outside the scope', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    Transaction::factory()->for($user)->create(['amount_minor' => 100, 'currency' => 'UZS', 'occurred_on' => today()]);
    Transaction::factory()->for($user)->create(['amount_minor' => 900, 'currency' => 'UZS', 'occurred_on' => today()])->delete();
    Transaction::factory()->create(['amount_minor' => 9000, 'currency' => 'UZS', 'occurred_on' => today()]);

    $points = $this->getJson(trendUrl('day', today(), today()))->assertOk()->json('points');

    expect($points)->toHaveCount(1)
        ->and($points[0]['amount_minor'])->toBe(100)
        ->and($points[0]['count'])->toBe(1);
});

it('rejects an interval outside the whitelist', function (string $interval) {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(trendUrl($interval, today(), today()))
        ->assertStatus(422)
        ->assertJsonValidationErrors('interval');

    expect(Schema::hasTable('transactions'))->toBeTrue();
})->with(['year', 'hour', "day'); drop table transactions; --"]);

it('refuses a guest and a user who is not active', function () {
    $this->getJson(trendUrl('day', today(), today()))->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create(['status' => UserStatus::Blocked]));

    $this->getJson(trendUrl('day', today(), today()))->assertForbidden();
});
