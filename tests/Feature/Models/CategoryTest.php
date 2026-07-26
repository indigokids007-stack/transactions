<?php

use App\Enums\CategoryAppliesTo;
use App\Enums\TransactionType;
use App\Models\Category;

it('nests categories', function () {
    $parent = Category::factory()->create(['name' => 'Transport']);
    $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Taksi']);

    expect($parent->children->pluck('id')->all())->toBe([$child->id])
        ->and($child->parent->id)->toBe($parent->id);
});

it('accepts only compatible transaction types', function () {
    $expenseOnly = Category::factory()->create(['applies_to' => CategoryAppliesTo::Expense]);
    $both = Category::factory()->create(['applies_to' => CategoryAppliesTo::Both]);

    expect($expenseOnly->acceptsType(TransactionType::Expense))->toBeTrue()
        ->and($expenseOnly->acceptsType(TransactionType::Income))->toBeFalse()
        ->and($both->acceptsType(TransactionType::Income))->toBeTrue();
});
