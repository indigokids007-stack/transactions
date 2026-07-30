<?php

use App\Enums\CategoryAppliesTo;
use App\Enums\UserRole;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

it('lets an admin create a category under a parent', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $parent = Category::factory()->create(['name' => 'Ofis xarajatlari']);

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'parent_id' => $parent->id,
            'name' => 'Kanselyariya',
            'applies_to' => CategoryAppliesTo::Expense->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = Category::where('name', 'Kanselyariya')->sole();

    expect($category->parent_id)->toBe($parent->id)
        ->and($category->applies_to)->toBe(CategoryAppliesTo::Expense);
});

it('lets an admin edit and delete a category', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $category = Category::factory()->create(['name' => 'Original']);

    Livewire::test(EditCategory::class, ['record' => $category->getKey()])
        ->fillForm(['name' => 'Yangilangan'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->fresh()->name)->toBe('Yangilangan');

    Livewire::test(ListCategories::class)->callTableAction('delete', $category);

    expect(Category::whereKey($category->id)->exists())->toBeFalse();
});

it('forbids an owner from opening the create and edit pages directly', function () {
    $category = Category::factory()->create();
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get(CategoryResource::getUrl('create'))->assertForbidden();
    $this->actingAs($owner)->get(CategoryResource::getUrl('edit', ['record' => $category]))->assertForbidden();
});

it('hides mutating category actions from an owner and refuses them even mounted directly', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $category = Category::factory()->create(['name' => 'Original']);

    Livewire::test(ListCategories::class)
        ->assertActionHidden('create')
        ->assertTableActionHidden('edit', $category)
        ->assertTableActionHidden('delete', $category)
        ->mountTableAction('edit', $category)
        ->setTableActionData(['name' => 'Tampered'])
        ->callMountedTableAction();

    expect($category->fresh()->name)->toBe('Original');

    Livewire::test(ListCategories::class)
        ->mountTableAction('delete', $category)
        ->callMountedTableAction();

    expect(Category::whereKey($category->id)->exists())->toBeTrue();
});
