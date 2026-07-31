<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Department;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds the placeholder departments, category tree and branch dimension', function () {
    test()->artisan('db:seed', ['--class' => ReferenceDataSeeder::class])->assertSuccessful();

    expect(Department::pluck('name')->sort()->values()->all())->toBe(['Office', 'Sales', 'Warehouse'])
        ->and(Category::whereNull('parent_id')->pluck('name')->sort()->values()->all())
        ->toBe(['Boshqa', 'Boshqa kirim', 'Marketing', 'Ofis', 'Transport'])
        ->and(Category::whereNotNull('parent_id')->pluck('name')->sort()->values()->all())
        ->toBe(['Kanselyariya', 'Kommunal', 'Taksi', 'Yoqilgi'])
        ->and(Dimension::where('key', 'branch')->exists())->toBeTrue();

    $branch = Dimension::where('key', 'branch')->sole();

    expect($branch->values()->count())->toBeGreaterThan(1);
});

it('is idempotent: seeding twice does not duplicate rows', function () {
    test()->artisan('db:seed', ['--class' => ReferenceDataSeeder::class]);

    $departments = Department::count();
    $categories = Category::count();
    $dimensions = Dimension::count();
    $dimensionValues = DimensionValue::count();

    test()->artisan('db:seed', ['--class' => ReferenceDataSeeder::class]);

    expect(Department::count())->toBe($departments)
        ->and(Category::count())->toBe($categories)
        ->and(Dimension::count())->toBe($dimensions)
        ->and(DimensionValue::count())->toBe($dimensionValues);
});

it('creates no admin and says so when ADMIN_TELEGRAM_ID is not set', function () {
    config()->set('admin.telegram_id', null);

    test()->artisan('db:seed', ['--class' => ReferenceDataSeeder::class])
        ->expectsOutputToContain('No ADMIN_TELEGRAM_ID')
        ->assertSuccessful();

    expect(User::count())->toBe(0);
});

it('creates an active admin with a login when ADMIN_TELEGRAM_ID, ADMIN_EMAIL and ADMIN_PASSWORD are set', function () {
    config()->set('admin.telegram_id', '777');
    config()->set('admin.email', 'admin@cara.test');
    config()->set('admin.password', 'correct horse battery staple');

    test()->artisan('db:seed', ['--class' => ReferenceDataSeeder::class])->assertSuccessful();

    $admin = User::sole();

    expect($admin->telegram_id)->toBe(777)
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and($admin->status)->toBe(UserStatus::Active)
        ->and($admin->email)->toBe('admin@cara.test')
        ->and($admin->password)->not->toBeNull()
        ->and(Hash::check('correct horse battery staple', $admin->password))->toBeTrue();
});

it('seeds the admin without panel credentials and warns when ADMIN_PASSWORD is blank', function () {
    config()->set('admin.telegram_id', '777');
    config()->set('admin.email', 'admin@cara.test');
    config()->set('admin.password', null);

    test()->artisan('db:seed', ['--class' => ReferenceDataSeeder::class])
        ->expectsOutputToContain('without panel credentials')
        ->assertSuccessful();

    $admin = User::sole();

    expect($admin->telegram_id)->toBe(777)
        ->and($admin->email)->toBeNull()
        ->and($admin->password)->toBeNull();
});

it('seeds the admin without panel credentials and warns when ADMIN_EMAIL is blank', function () {
    config()->set('admin.telegram_id', '777');
    config()->set('admin.email', null);
    config()->set('admin.password', 'correct horse battery staple');

    test()->artisan('db:seed', ['--class' => ReferenceDataSeeder::class])
        ->expectsOutputToContain('without panel credentials')
        ->assertSuccessful();

    $admin = User::sole();

    expect($admin->telegram_id)->toBe(777)
        ->and($admin->email)->toBeNull()
        ->and($admin->password)->toBeNull();
});

it('does not duplicate the admin when seeded twice', function () {
    config()->set('admin.telegram_id', '777');
    config()->set('admin.email', 'admin@cara.test');
    config()->set('admin.password', 'correct horse battery staple');

    test()->artisan('db:seed', ['--class' => ReferenceDataSeeder::class]);
    test()->artisan('db:seed', ['--class' => ReferenceDataSeeder::class]);

    expect(User::count())->toBe(1);
});

it('runs the reference data seeder as part of the default database seed', function () {
    test()->artisan('db:seed')->assertSuccessful();

    expect(Department::count())->toBe(3);
});
