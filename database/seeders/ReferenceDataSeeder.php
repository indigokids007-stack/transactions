<?php

namespace Database\Seeders;

use App\Enums\CategoryAppliesTo;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Department;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Placeholder reference data for a fresh install: department, category and dimension
 * names the human has not supplied Cara's real values for yet, seeded so the panel and
 * the bot have something to work against until they are replaced. Running this twice
 * never duplicates a row: dimensions key on their unique `key` column, departments and
 * categories key on name (and, for categories, parent), so renaming one in the panel and
 * then re-seeding recreates it under the old name rather than updating it in place. The
 * seeded admin is the one exception to "placeholder": it is a real login, created once.
 */
class ReferenceDataSeeder extends Seeder
{
    /** @var array<string, list<string>> Parent category name to its placeholder children. */
    private const EXPENSE_CATEGORY_TREE = [
        'Transport' => ['Taksi', 'Yoqilgi'],
        'Ofis' => ['Kanselyariya', 'Kommunal'],
        'Marketing' => [],
        'Boshqa' => [],
    ];

    /** @var list<string> */
    private const BRANCH_PLACEHOLDER_VALUES = ['Markaziy ofis', 'Filial 1', 'Filial 2'];

    public function run(): void
    {
        $this->seedDepartments();
        $this->seedExpenseCategories();
        $this->seedIncomeCategory();
        $this->seedBranchDimension();
        $this->seedAdmin();
    }

    private function seedDepartments(): void
    {
        foreach (['Sales', 'Warehouse', 'Office'] as $name) {
            Department::query()->firstOrCreate(['name' => $name]);
        }
    }

    private function seedExpenseCategories(): void
    {
        foreach (self::EXPENSE_CATEGORY_TREE as $parentName => $children) {
            $parent = Category::query()->firstOrCreate(
                ['name' => $parentName, 'parent_id' => null],
                ['applies_to' => CategoryAppliesTo::Expense],
            );

            foreach ($children as $childName) {
                Category::query()->firstOrCreate(
                    ['name' => $childName, 'parent_id' => $parent->id],
                    ['applies_to' => CategoryAppliesTo::Expense],
                );
            }
        }
    }

    private function seedIncomeCategory(): void
    {
        Category::query()->firstOrCreate(
            ['name' => 'Boshqa kirim', 'parent_id' => null],
            ['applies_to' => CategoryAppliesTo::Income],
        );
    }

    private function seedBranchDimension(): void
    {
        $branch = Dimension::query()->firstOrCreate(
            ['key' => 'branch'],
            ['name' => 'Filial', 'is_required' => false],
        );

        foreach (self::BRANCH_PLACEHOLDER_VALUES as $sort => $name) {
            DimensionValue::query()->firstOrCreate(
                ['dimension_id' => $branch->id, 'name' => $name],
                ['sort' => $sort],
            );
        }
    }

    private function seedAdmin(): void
    {
        $telegramId = config('admin.telegram_id');

        if (blank($telegramId)) {
            $this->command->warn(
                'No ADMIN_TELEGRAM_ID configured: skipping the admin account, seeded placeholder reference data only.'
            );

            return;
        }

        if (! is_numeric($telegramId)) {
            $this->command->warn(
                "ADMIN_TELEGRAM_ID ({$telegramId}) is not numeric: skipping the admin account, seeded placeholder reference data only."
            );

            return;
        }

        $email = config('admin.email');
        $password = config('admin.password');

        // Filament's default login page authenticates by email and password together, so
        // half a credential (an email with no password, or the reverse) is as unusable as
        // none: rather than seed one, the admin is seeded with neither.
        $hasCredentials = filled($email) && filled($password);

        $admin = User::query()->firstOrCreate(
            ['telegram_id' => (int) $telegramId],
            [
                'name' => 'Admin',
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'locale' => 'uz',
                'email' => $hasCredentials ? $email : null,
                'password' => $hasCredentials ? Hash::make($password) : null,
            ],
        );

        // firstOrCreate returns a pre-existing row untouched, credentials and role
        // included: reporting that row as freshly "seeded with credentials" here would
        // tell an operator their login works, or that a staff member became an admin,
        // when neither happened. This seeder never edits an existing user, so it says so
        // instead of claiming success on their behalf.
        if (! $admin->wasRecentlyCreated) {
            $this->command->warn(
                "A user with telegram id {$admin->telegram_id} already exists: role and panel credentials left unchanged."
            );

            return;
        }

        if (! $hasCredentials) {
            $this->command->warn(
                "Admin (telegram_id {$admin->telegram_id}) seeded without panel credentials: this seeder will not add them on a later run since the account now exists, so set the password directly (for example through the panel or php artisan tinker)."
            );

            return;
        }

        $this->command->info("Admin (telegram_id {$admin->telegram_id}) seeded with panel login credentials.");
    }
}
