<?php

use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('refuses a pending user', function () {
    Sanctum::actingAs(User::factory()->create(['status' => UserStatus::Pending]));

    $this->getJson('/api/bootstrap')->assertStatus(403);
});

it('returns reference data for an active user', function () {
    Sanctum::actingAs(User::factory()->create());
    $parent = Category::factory()->create(['name' => 'Transport']);
    Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Taksi']);
    Category::factory()->create(['name' => 'Hidden', 'is_active' => false]);
    $branch = Dimension::factory()->create(['key' => 'branch']);
    DimensionValue::factory()->create(['dimension_id' => $branch->id, 'name' => 'Chilonzor']);

    $this->getJson('/api/bootstrap')
        ->assertOk()
        ->assertJsonCount(1, 'categories')
        ->assertJsonPath('categories.0.children.0.name', 'Taksi')
        ->assertJsonPath('dimensions.0.key', 'branch')
        ->assertJsonPath('dimensions.0.values.0.name', 'Chilonzor')
        ->assertJsonPath('currencies.UZS', 0);
});

it('derives sticky defaults from the last transaction of the caller', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $value = DimensionValue::factory()->create();
    $transaction = Transaction::factory()->for($user)->create(['currency' => 'USD']);
    $transaction->dimensionValues()->attach($value, ['dimension_id' => $value->dimension_id]);

    $this->getJson('/api/bootstrap')
        ->assertOk()
        ->assertJsonPath('defaults.currency', 'USD')
        ->assertJsonPath('defaults.category_id', $transaction->category_id)
        ->assertJsonPath("defaults.dimension_values.{$value->dimension_id}", $value->id);
});

it('falls back to the configured currency and an expense type when the caller has no history', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/bootstrap')
        ->assertOk()
        ->assertJsonPath('defaults.currency', 'UZS')
        ->assertJsonPath('defaults.category_id', null)
        ->assertJsonPath('defaults.type', 'expense');
});

it('serialises empty dimension_values as an object rather than an array', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/bootstrap')->assertOk();

    expect($response->getContent())->toContain('"dimension_values":{}');
});
