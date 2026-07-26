# Transactions Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Laravel backend of the Cara staff transactions ledger: schema, Telegram authentication, the transaction write and read API, scoped reports, the Telegram bot entry flow and the Filament admin panel.

**Architecture:** One Laravel 12 application exposes three surfaces over one domain model: a REST API for the mini app, a Telegram webhook for bot quick entry, and a Filament v4 admin panel. The bot owns no write logic; it builds a draft and, on confirmation, calls the same `CreateTransaction` action the API uses, so validation, scoping, idempotency and the revision log exist in exactly one place. PostgreSQL is the only datastore and reports are computed live.

**Tech Stack:** PHP 8.4, Laravel 12, PostgreSQL 17, Laravel Sanctum (API tokens), Filament v4, Pest 4, Pint, Larastan. Everything runs in Docker; the host has no PHP or Composer.

**Spec:** `docs/superpowers/specs/2026-07-26-transactions-design.md`

**Out of scope for this plan:** the React mini app (its own plan, written after this API exists), cashboxes/accounts, receipt photos, approvals, currency conversion.

## Global Constraints

- PHP 8.4, Laravel 12, PostgreSQL 17, Filament v4, Pest 4. Versions are read from the manifest once created; never hardcode them elsewhere.
- Host has no `php` and no `composer`. Every PHP, Artisan, Composer and test command runs inside the `app` container through the `Makefile`.
- Money is stored as `amount_minor` (`bigint`) plus an ISO 4217 `currency` code. Never float, never `double`.
- Aggregates are always grouped by currency. Amounts of different currencies are never summed.
- Authorization scope is resolved server side in `TransactionScope`. A client supplied `user_id` or `department_id` is a filter inside the caller's scope, never a way to widen it.
- `transactions.department_id` is a snapshot written from the author at creation time and is never recalculated afterwards.
- Follow the Spatie PHP and Laravel guidelines: typed properties, constructor property promotion, early returns, no `else`, string interpolation, array notation in validation rules, no comments that restate code. Migrations contain an `up` method only.
- No new Composer or NPM dependency beyond those named in Task 1 without explicit human approval.
- Bot and API user facing strings live in `lang/{uz,ru,en}` and are kept in sync.

---

### Task 1: Project bootstrap, Docker, tooling

**Files:**
- Create: `Dockerfile`, `docker-compose.yml`, `Makefile`, `.dockerignore`, `.editorconfig`, `.gitignore`, `README.md`, `AGENTS.md`
- Create (generated): the Laravel 12 skeleton at the repository root
- Create: `phpstan.neon`, `pint.json`
- Test: `tests/Feature/HealthTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `make up`, `make down`, `make test`, `make artisan cmd="..."`, `make composer cmd="..."`, `make analyze`, `make pint`. Container name `transactions-app`, database service `db`, database `transactions`, user `transactions`, password `secret`.

- [ ] **Step 1: Generate the Laravel skeleton into the empty repository**

```bash
cd /Users/anvarai/Development/USMON/Cara/transactions
docker run --rm -v "$PWD":/app -w /app composer:2 \
  create-project laravel/laravel:^12.0 tmp-skeleton --no-interaction
# move the skeleton to the repository root, keeping docs/ and .git/
rsync -a tmp-skeleton/ ./ && rm -rf tmp-skeleton
```

- [ ] **Step 2: Write the Dockerfile**

```dockerfile
FROM php:8.4-cli-alpine

RUN apk add --no-cache postgresql-dev icu-dev libzip-dev git \
    && docker-php-ext-install pdo_pgsql intl zip bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

- [ ] **Step 3: Write docker-compose.yml**

```yaml
services:
  app:
    build: .
    container_name: transactions-app
    volumes:
      - .:/app
    ports:
      - "8000:8000"
    depends_on:
      db:
        condition: service_healthy
    environment:
      DB_CONNECTION: pgsql
      DB_HOST: db
      DB_PORT: 5432
      DB_DATABASE: transactions
      DB_USERNAME: transactions
      DB_PASSWORD: secret

  db:
    image: postgres:17-alpine
    container_name: transactions-db
    environment:
      POSTGRES_DB: transactions
      POSTGRES_USER: transactions
      POSTGRES_PASSWORD: secret
    ports:
      - "55432:5432"
    volumes:
      - db-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U transactions"]
      interval: 5s
      timeout: 3s
      retries: 10

volumes:
  db-data:
```

- [ ] **Step 4: Write the Makefile**

```makefile
DC = docker compose
PHP = $(DC) exec -T app php
COMPOSER = $(DC) exec -T app composer

up:
	$(DC) up -d --build

down:
	$(DC) down

shell:
	$(DC) exec app sh

artisan:
	$(PHP) artisan $(cmd)

composer:
	$(COMPOSER) $(cmd)

migrate:
	$(PHP) artisan migrate

fresh:
	$(PHP) artisan migrate:fresh --seed

test:
	$(PHP) artisan test $(args)

analyze:
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

pint:
	$(PHP) vendor/bin/pint --dirty
```

- [ ] **Step 5: Install the runtime and dev dependencies**

```bash
make up
make composer cmd="require laravel/sanctum filament/filament:^4.0"
make composer cmd="require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/pint"
make artisan cmd="install:api"
make artisan cmd="filament:install --panels"
make artisan cmd="pest:install"
```

- [ ] **Step 6: Write the failing health test**

```php
<?php

// tests/Feature/HealthTest.php

it('answers the health endpoint', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});
```

- [ ] **Step 7: Run it and confirm it fails**

Run: `make test args="--filter=health"`
Expected: FAIL, 404 not found.

- [ ] **Step 8: Add the health route**

```php
// routes/api.php
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => ['status' => 'ok'])->name('health');
```

- [ ] **Step 9: Run the test and confirm it passes**

Run: `make test args="--filter=health"`
Expected: PASS.

- [ ] **Step 10: Configure phpstan.neon at level 6 and pint.json with the laravel preset, then run both**

```yaml
# phpstan.neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 6
    paths:
        - app
        - database
        - routes
```

Run: `make analyze` and `make pint`. Both must be clean.

- [ ] **Step 11: Write README.md and AGENTS.md**

README.md states in one sentence what the project is, the five commands to run it locally (`make up`, `make migrate`, `make test`, `make analyze`, `make pint`), that the Telegram webhook needs a public HTTPS tunnel in development, and who owns it. AGENTS.md restates the Global Constraints section of this plan.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "chore: bootstrap laravel 12 project with docker tooling"
```

---

### Task 2: Enums, settings and the users and departments schema

**Files:**
- Create: `app/Enums/UserRole.php`, `app/Enums/UserStatus.php`
- Create: `database/migrations/*_create_departments_table.php`, `*_update_users_table_for_telegram.php`, `*_create_department_manager_table.php`, `*_create_settings_table.php`
- Create: `app/Models/Department.php`, `app/Models/Setting.php`
- Modify: `app/Models/User.php`
- Create: `database/factories/DepartmentFactory.php`, modify `database/factories/UserFactory.php`
- Test: `tests/Feature/Models/UserTest.php`, `tests/Feature/SettingTest.php`

**Interfaces:**
- Consumes: Task 1 tooling.
- Produces: `UserRole::Staff|Manager|Owner|Admin`, `UserStatus::Pending|Active|Blocked`; `User::$telegram_id`, `User::$role`, `User::$status`, `User::$department_id`, `User::$locale`, `User::managedDepartments(): BelongsToMany`, `User::isAdmin(): bool`, `User::canSeeEverything(): bool`; `Setting::get(string $key, mixed $default = null): mixed`, `Setting::put(string $key, mixed $value): void`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

// tests/Feature/Models/UserTest.php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;

it('casts role and status to enums', function () {
    $user = User::factory()->create([
        'role' => UserRole::Manager,
        'status' => UserStatus::Active,
    ]);

    expect($user->fresh()->role)->toBe(UserRole::Manager)
        ->and($user->fresh()->status)->toBe(UserStatus::Active);
});

it('knows which departments a manager covers', function () {
    $sales = Department::factory()->create();
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $manager->managedDepartments()->attach($sales);

    expect($manager->managedDepartments->pluck('id')->all())->toBe([$sales->id]);
});

it('lets owners and admins see everything but not staff or managers', function () {
    expect(User::factory()->create(['role' => UserRole::Owner])->canSeeEverything())->toBeTrue()
        ->and(User::factory()->create(['role' => UserRole::Admin])->canSeeEverything())->toBeTrue()
        ->and(User::factory()->create(['role' => UserRole::Manager])->canSeeEverything())->toBeFalse()
        ->and(User::factory()->create(['role' => UserRole::Staff])->canSeeEverything())->toBeFalse();
});
```

```php
<?php

// tests/Feature/SettingTest.php

use App\Models\Setting;

it('stores and reads a setting', function () {
    Setting::put('registration_open', false);

    expect(Setting::get('registration_open'))->toBeFalse();
});

it('returns the default when a setting is missing', function () {
    expect(Setting::get('registration_open', true))->toBeTrue();
});
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `make test args="--filter='UserTest|SettingTest'"`
Expected: FAIL, missing classes.

- [ ] **Step 3: Write the enums**

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Staff = 'staff';
    case Manager = 'manager';
    case Owner = 'owner';
    case Admin = 'admin';
}
```

```php
<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Blocked = 'blocked';
}
```

- [ ] **Step 4: Write the migrations**

```php
// create_departments_table
Schema::create('departments', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

```php
// update_users_table_for_telegram
Schema::table('users', function (Blueprint $table) {
    $table->unsignedBigInteger('telegram_id')->unique()->after('id');
    $table->string('username')->nullable()->after('name');
    $table->string('role')->default('staff')->after('username');
    $table->string('status')->default('pending')->after('role');
    $table->string('locale', 2)->default('ru')->after('status');
    $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
    $table->string('email')->nullable()->change();
    $table->string('password')->nullable()->change();
});
```

```php
// create_department_manager_table
Schema::create('department_manager', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('department_id')->constrained()->cascadeOnDelete();
    $table->primary(['user_id', 'department_id']);
});
```

```php
// create_settings_table
Schema::create('settings', function (Blueprint $table) {
    $table->string('key')->primary();
    $table->jsonb('value');
    $table->timestamps();
});
```

- [ ] **Step 5: Write the models**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::find($key);

        if (! $setting) {
            return $default;
        }

        return $setting->value['value'] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => ['value' => $value]]);
    }
}
```

`User` gains typed casts (`'role' => UserRole::class`, `'status' => UserStatus::class`), the `department(): BelongsTo`, `managedDepartments(): BelongsToMany` (table `department_manager`) and `transactions(): HasMany` relations, plus:

```php
public function isAdmin(): bool
{
    return $this->role === UserRole::Admin;
}

public function canSeeEverything(): bool
{
    return in_array($this->role, [UserRole::Owner, UserRole::Admin], true);
}

public function isActive(): bool
{
    return $this->status === UserStatus::Active;
}
```

`Department` has `name`, `is_active` fillable, `users(): HasMany` and `managers(): BelongsToMany`.

- [ ] **Step 6: Update the factories**

`UserFactory` produces a unique `telegram_id`, `role => UserRole::Staff`, `status => UserStatus::Active`, `locale => 'ru'`, null email and password. `DepartmentFactory` produces a name and `is_active => true`.

- [ ] **Step 7: Run the tests and confirm they pass**

Run: `make test args="--filter='UserTest|SettingTest'"`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add users, departments and settings schema"
```

---

### Task 3: Categories, dimensions and dimension values

**Files:**
- Create: `database/migrations/*_create_categories_table.php`, `*_create_dimensions_table.php`, `*_create_dimension_values_table.php`
- Create: `app/Models/Category.php`, `app/Models/Dimension.php`, `app/Models/DimensionValue.php`
- Create: `app/Enums/CategoryAppliesTo.php`
- Create: factories for all three models
- Test: `tests/Feature/Models/CategoryTest.php`, `tests/Feature/Models/DimensionTest.php`

**Interfaces:**
- Consumes: Task 2 models.
- Produces: `Category::$parent_id`, `Category::children(): HasMany`, `Category::$applies_to` cast to `CategoryAppliesTo::Income|Expense|Both`, `Category::acceptsType(TransactionType $type): bool`; `Dimension::$key` (unique), `Dimension::$is_required`, `Dimension::values(): HasMany`; `DimensionValue::$dimension_id`, `DimensionValue::$name`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

// tests/Feature/Models/CategoryTest.php

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
```

```php
<?php

// tests/Feature/Models/DimensionTest.php

use App\Models\Dimension;
use App\Models\DimensionValue;

it('holds its values', function () {
    $dimension = Dimension::factory()->create(['key' => 'branch', 'name' => 'Filial']);
    DimensionValue::factory()->create(['dimension_id' => $dimension->id, 'name' => 'Chilonzor']);

    expect($dimension->values)->toHaveCount(1);
});

it('rejects a duplicate key', function () {
    Dimension::factory()->create(['key' => 'branch']);

    Dimension::factory()->create(['key' => 'branch']);
})->throws(Illuminate\Database\QueryException::class);
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `make test args="--filter='CategoryTest|DimensionTest'"`
Expected: FAIL, missing classes. `TransactionType` does not exist yet either, so create it in Step 3.

- [ ] **Step 3: Write the enums**

```php
<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
}
```

```php
<?php

namespace App\Enums;

enum CategoryAppliesTo: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Both = 'both';
}
```

- [ ] **Step 4: Write the migrations**

```php
// create_categories_table
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
    $table->string('name');
    $table->string('applies_to')->default('both');
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort')->default(0);
    $table->timestamps();
});
```

```php
// create_dimensions_table
Schema::create('dimensions', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->string('name');
    $table->boolean('is_required')->default(false);
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort')->default(0);
    $table->timestamps();
});
```

```php
// create_dimension_values_table
Schema::create('dimension_values', function (Blueprint $table) {
    $table->id();
    $table->foreignId('dimension_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort')->default(0);
    $table->timestamps();

    $table->unique(['dimension_id', 'name']);
});
```

- [ ] **Step 5: Write the models**

`Category` casts `applies_to` to `CategoryAppliesTo`, has `parent(): BelongsTo`, `children(): HasMany`, and:

```php
public function acceptsType(TransactionType $type): bool
{
    if ($this->applies_to === CategoryAppliesTo::Both) {
        return true;
    }

    return $this->applies_to->value === $type->value;
}
```

`Dimension` has `values(): HasMany` ordered by `sort`; `DimensionValue` has `dimension(): BelongsTo`.

- [ ] **Step 6: Run the tests and confirm they pass**

Run: `make test args="--filter='CategoryTest|DimensionTest'"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add categories and admin defined dimensions"
```

---

### Task 4: Transactions, dimension pivot, revisions and drafts schema

**Files:**
- Create: `database/migrations/*_create_transactions_table.php`, `*_create_transaction_dimension_values_table.php`, `*_create_transaction_revisions_table.php`, `*_create_entry_drafts_table.php`
- Create: `app/Models/Transaction.php`, `app/Models/TransactionRevision.php`, `app/Models/EntryDraft.php`
- Create: `app/Enums/RevisionAction.php`
- Create: `config/money.php`, `app/Support/Money.php`
- Create: `database/factories/TransactionFactory.php`
- Test: `tests/Feature/Models/TransactionTest.php`, `tests/Unit/MoneyTest.php`

**Interfaces:**
- Consumes: Tasks 2 and 3 models and enums.
- Produces: `Transaction::$user_id, $department_id, $type, $amount_minor, $currency, $occurred_on, $category_id, $note, $created_by, $idempotency_key, $deleted_at`; `Transaction::dimensionValues(): BelongsToMany`; `Transaction::revisions(): HasMany`; `RevisionAction::Created|Updated|Deleted|Restored`; `Money::toMinor(string $amount, string $currency): int`, `Money::toDecimal(int $minor, string $currency): string`, `Money::exponent(string $currency): int`, `Money::isSupported(string $currency): bool`; `EntryDraft::$user_id, $payload, $expires_at`, `EntryDraft::isExpired(): bool`.

- [ ] **Step 1: Write the failing money tests**

```php
<?php

// tests/Unit/MoneyTest.php

use App\Support\Money;

it('converts decimal amounts to minor units by currency exponent', function () {
    expect(Money::toMinor('120000', 'UZS'))->toBe(120000)
        ->and(Money::toMinor('12.34', 'USD'))->toBe(1234)
        ->and(Money::toMinor('12.3', 'USD'))->toBe(1230);
});

it('formats minor units back to a decimal string', function () {
    expect(Money::toDecimal(120000, 'UZS'))->toBe('120000')
        ->and(Money::toDecimal(1234, 'USD'))->toBe('12.34');
});

it('knows which currencies are supported', function () {
    expect(Money::isSupported('UZS'))->toBeTrue()
        ->and(Money::isSupported('XXX'))->toBeFalse();
});
```

- [ ] **Step 2: Run and confirm failure**

Run: `make test args="--filter=MoneyTest"`
Expected: FAIL, class `App\Support\Money` not found.

- [ ] **Step 3: Write the money config and helper**

```php
<?php

// config/money.php

return [
    'default' => 'UZS',
    'currencies' => [
        'UZS' => 0,
        'USD' => 2,
        'EUR' => 2,
        'RUB' => 2,
    ],
];
```

```php
<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function exponent(string $currency): int
    {
        $exponent = config("money.currencies.{$currency}");

        if ($exponent === null) {
            throw new InvalidArgumentException("Unsupported currency `{$currency}`.");
        }

        return $exponent;
    }

    public static function isSupported(string $currency): bool
    {
        return config("money.currencies.{$currency}") !== null;
    }

    public static function toMinor(string $amount, string $currency): int
    {
        $exponent = static::exponent($currency);

        return (int) round(((float) $amount) * (10 ** $exponent));
    }

    public static function toDecimal(int $minor, string $currency): string
    {
        $exponent = static::exponent($currency);

        if ($exponent === 0) {
            return (string) $minor;
        }

        return number_format($minor / (10 ** $exponent), $exponent, '.', '');
    }
}
```

- [ ] **Step 4: Run the money tests and confirm they pass**

Run: `make test args="--filter=MoneyTest"`
Expected: PASS.

- [ ] **Step 5: Write the failing transaction model test**

```php
<?php

// tests/Feature/Models/TransactionTest.php

use App\Enums\TransactionType;
use App\Models\DimensionValue;
use App\Models\Transaction;

it('stores money in minor units and casts its type', function () {
    $transaction = Transaction::factory()->create([
        'type' => TransactionType::Expense,
        'amount_minor' => 120000,
        'currency' => 'UZS',
    ]);

    expect($transaction->fresh()->type)->toBe(TransactionType::Expense)
        ->and($transaction->fresh()->amount_minor)->toBe(120000);
});

it('attaches at most one value per dimension', function () {
    $transaction = Transaction::factory()->create();
    $value = DimensionValue::factory()->create();

    $transaction->dimensionValues()->attach($value, ['dimension_id' => $value->dimension_id]);
    $transaction->dimensionValues()->attach($value, ['dimension_id' => $value->dimension_id]);
})->throws(Illuminate\Database\QueryException::class);

it('soft deletes', function () {
    $transaction = Transaction::factory()->create();

    $transaction->delete();

    expect(Transaction::count())->toBe(0)
        ->and(Transaction::withTrashed()->count())->toBe(1);
});
```

- [ ] **Step 6: Run and confirm failure**

Run: `make test args="--filter=TransactionTest"`
Expected: FAIL, missing model and tables.

- [ ] **Step 7: Write the migrations**

```php
// create_transactions_table
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->restrictOnDelete();
    $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
    $table->string('type');
    $table->bigInteger('amount_minor');
    $table->char('currency', 3);
    $table->date('occurred_on');
    $table->foreignId('category_id')->constrained()->restrictOnDelete();
    $table->text('note')->nullable();
    $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
    $table->uuid('idempotency_key')->nullable()->unique();
    $table->softDeletes();
    $table->timestamps();

    $table->index(['user_id', 'occurred_on']);
    $table->index(['department_id', 'occurred_on']);
    $table->index('occurred_on');
    $table->index('category_id');
});
```

```php
// create_transaction_dimension_values_table
Schema::create('transaction_dimension_values', function (Blueprint $table) {
    $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
    $table->foreignId('dimension_id')->constrained()->cascadeOnDelete();
    $table->foreignId('dimension_value_id')->constrained()->cascadeOnDelete();

    $table->primary(['transaction_id', 'dimension_id']);
    $table->index(['dimension_value_id', 'transaction_id']);
});
```

```php
// create_transaction_revisions_table
Schema::create('transaction_revisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
    $table->string('action');
    $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
    $table->jsonb('snapshot');
    $table->timestamp('created_at');

    $table->index(['transaction_id', 'created_at']);
});
```

```php
// create_entry_drafts_table
Schema::create('entry_drafts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->jsonb('payload');
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->index('expires_at');
});
```

- [ ] **Step 8: Write the models and the enum**

`RevisionAction` is a backed enum with `Created`, `Updated`, `Deleted`, `Restored`.

`Transaction` uses `SoftDeletes`, casts `type` to `TransactionType`, `occurred_on` to `date`, `amount_minor` to `integer`, and defines `user(): BelongsTo`, `category(): BelongsTo`, `department(): BelongsTo`, `revisions(): HasMany`, and:

```php
public function dimensionValues(): BelongsToMany
{
    return $this->belongsToMany(DimensionValue::class, 'transaction_dimension_values')
        ->withPivot('dimension_id');
}
```

`EntryDraft` uses a string primary key (`$incrementing = false`, `$keyType = 'string'`), casts `payload` to `array` and `expires_at` to `datetime`, and exposes:

```php
public function isExpired(): bool
{
    return $this->expires_at->isPast();
}
```

- [ ] **Step 9: Run the tests and confirm they pass**

Run: `make test args="--filter='TransactionTest|MoneyTest'"`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: add transactions, revisions and draft schema"
```

---

### Task 5: Telegram authentication, registration toggle, pending users

**Files:**
- Create: `app/Services/Telegram/InitDataValidator.php`, `app/Exceptions/InvalidInitDataException.php`
- Create: `app/Actions/ResolveTelegramUser.php`
- Create: `app/Http/Controllers/Api/TelegramAuthController.php`, `app/Http/Requests/Api/TelegramAuthRequest.php`
- Create: `app/Http/Resources/UserResource.php`
- Modify: `routes/api.php`, `config/services.php`
- Test: `tests/Unit/InitDataValidatorTest.php`, `tests/Feature/Api/TelegramAuthTest.php`

**Interfaces:**
- Consumes: `User`, `UserStatus`, `UserRole`, `Setting` from Task 2.
- Produces: `InitDataValidator::validate(string $initData): array` returning the parsed payload with a `user` key, throwing `InvalidInitDataException` on a bad signature or stale `auth_date`; `ResolveTelegramUser::handle(array $telegramUser, string $languageCode): User`; `POST /api/auth/telegram` returning `{token, user}` for active users and `{token: null, user: {status: 'pending'}}` for pending ones; `UserResource` shape `{id, telegram_id, name, username, role, status, locale, department: {id, name}|null, permissions: {can_see_all: bool, can_manage: bool}}`.

Security notes for the implementer: the Telegram secret key is `hash_hmac('sha256', $botToken, 'WebAppData', true)` and the computed hash is compared with `hash_equals`. The `hash` field is removed before building the data check string, which is the remaining fields sorted by key and joined with `\n` as `key=value`. `auth_date` older than the configured TTL is rejected.

- [ ] **Step 1: Write the failing validator tests**

```php
<?php

// tests/Unit/InitDataValidatorTest.php

use App\Exceptions\InvalidInitDataException;
use App\Services\Telegram\InitDataValidator;

function buildInitData(array $overrides = [], string $botToken = 'test-bot-token'): string
{
    $fields = array_merge([
        'auth_date' => (string) now()->timestamp,
        'query_id' => 'AAA',
        'user' => json_encode(['id' => 111, 'first_name' => 'Alisher', 'language_code' => 'uz']),
    ], $overrides);

    ksort($fields);

    $checkString = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
    $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $fields['hash'] = hash_hmac('sha256', $checkString, $secret);

    return http_build_query($fields);
}

beforeEach(fn () => config()->set('services.telegram.bot_token', 'test-bot-token'));

it('accepts a correctly signed payload', function () {
    $payload = (new InitDataValidator)->validate(buildInitData());

    expect($payload['user']['id'])->toBe(111);
});

it('rejects a tampered payload', function () {
    $initData = buildInitData();
    $tampered = str_replace('Alisher', 'Attacker', $initData);

    (new InitDataValidator)->validate($tampered);
})->throws(InvalidInitDataException::class);

it('rejects a payload signed with another bot token', function () {
    (new InitDataValidator)->validate(buildInitData(botToken: 'other-token'));
})->throws(InvalidInitDataException::class);

it('rejects a stale auth_date', function () {
    (new InitDataValidator)->validate(buildInitData([
        'auth_date' => (string) now()->subDay()->timestamp,
    ]));
})->throws(InvalidInitDataException::class);
```

- [ ] **Step 2: Run and confirm failure**

Run: `make test args="--filter=InitDataValidatorTest"`
Expected: FAIL, class not found.

- [ ] **Step 3: Add the Telegram config**

```php
// config/services.php
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'init_data_ttl' => (int) env('TELEGRAM_INIT_DATA_TTL', 3600),
    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
],
```

Add the four keys to `.env.example`.

- [ ] **Step 4: Write the validator**

```php
<?php

namespace App\Services\Telegram;

use App\Exceptions\InvalidInitDataException;

class InitDataValidator
{
    /** @return array<string, mixed> */
    public function validate(string $initData): array
    {
        parse_str($initData, $fields);

        $hash = $fields['hash'] ?? null;

        if (! is_string($hash)) {
            throw new InvalidInitDataException('Missing hash.');
        }

        unset($fields['hash']);
        ksort($fields);

        $checkString = collect($fields)
            ->map(fn (string $value, string $key) => "{$key}={$value}")
            ->implode("\n");

        $secret = hash_hmac('sha256', (string) config('services.telegram.bot_token'), 'WebAppData', true);

        if (! hash_equals(hash_hmac('sha256', $checkString, $secret), $hash)) {
            throw new InvalidInitDataException('Signature mismatch.');
        }

        $authDate = (int) ($fields['auth_date'] ?? 0);

        if (now()->timestamp - $authDate > (int) config('services.telegram.init_data_ttl')) {
            throw new InvalidInitDataException('Stale auth_date.');
        }

        $user = json_decode((string) ($fields['user'] ?? ''), true);

        if (! is_array($user) || ! isset($user['id'])) {
            throw new InvalidInitDataException('Missing user.');
        }

        $fields['user'] = $user;

        return $fields;
    }
}
```

- [ ] **Step 5: Run the validator tests and confirm they pass**

Run: `make test args="--filter=InitDataValidatorTest"`
Expected: PASS.

- [ ] **Step 6: Write the failing auth endpoint tests**

```php
<?php

// tests/Feature/Api/TelegramAuthTest.php

use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;

beforeEach(fn () => config()->set('services.telegram.bot_token', 'test-bot-token'));

it('issues a token to an active user', function () {
    User::factory()->create(['telegram_id' => 111, 'status' => UserStatus::Active]);

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])
        ->assertOk()
        ->assertJsonPath('user.status', 'active')
        ->assertJsonStructure(['token', 'user' => ['id', 'role', 'permissions']]);
});

it('creates a pending user and issues no token when registration is open', function () {
    Setting::put('registration_open', true);

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])
        ->assertOk()
        ->assertJsonPath('token', null)
        ->assertJsonPath('user.status', 'pending');

    expect(User::where('telegram_id', 111)->exists())->toBeTrue();
});

it('rejects an unknown user and creates nothing when registration is closed', function () {
    Setting::put('registration_open', false);

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])
        ->assertStatus(403);

    expect(User::where('telegram_id', 111)->exists())->toBeFalse();
});

it('lets an existing pending user in while registration is closed but issues no token', function () {
    Setting::put('registration_open', false);
    User::factory()->create(['telegram_id' => 111, 'status' => UserStatus::Pending]);

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])
        ->assertOk()
        ->assertJsonPath('token', null);
});

it('refuses a blocked user', function () {
    User::factory()->create(['telegram_id' => 111, 'status' => UserStatus::Blocked]);

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])
        ->assertStatus(403);
});

it('rejects an invalid signature', function () {
    $this->postJson('/api/auth/telegram', ['init_data' => 'user=%7B%22id%22%3A1%7D&hash=deadbeef'])
        ->assertStatus(401);
});
```

Move `buildInitData()` from the unit test into `tests/Pest.php` so both suites use one helper.

- [ ] **Step 7: Run and confirm failure**

Run: `make test args="--filter=TelegramAuthTest"`
Expected: FAIL, route missing.

- [ ] **Step 8: Write the action, the request, the resource and the controller**

`ResolveTelegramUser::handle()` finds the user by `telegram_id`. If found, it refreshes `name`, `username` and `locale` and returns it. If not found and `Setting::get('registration_open', true)` is false, it throws an `AuthorizationException`. Otherwise it creates a user with `status: Pending`, `role: Staff`, and the locale taken from the Telegram `language_code` when it is one of `uz`, `ru`, `en`, falling back to `ru`.

The controller validates `init_data` as required string, calls the validator (mapping `InvalidInitDataException` to a 401 response), calls the action (a blocked user gets 403), and returns:

```php
return [
    'token' => $user->isActive()
        ? $user->createToken('mini-app', ['*'], now()->addDays(30))->plainTextToken
        : null,
    'user' => UserResource::make($user),
];
```

Register the route as `Route::post('auth/telegram', [TelegramAuthController::class, 'store'])->middleware('throttle:20,1')`.

- [ ] **Step 9: Run the tests and confirm they pass**

Run: `make test args="--filter='TelegramAuthTest|InitDataValidatorTest'"`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: authenticate telegram mini app users with activation gate"
```

---

### Task 6: Active-user middleware, /api/me and /api/bootstrap

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsActive.php`
- Create: `app/Http/Controllers/Api/BootstrapController.php`
- Create: `app/Http/Resources/CategoryResource.php`, `app/Http/Resources/DimensionResource.php`
- Create: `app/Support/StickyDefaults.php`
- Create: `lang/uz/errors.php`, `lang/ru/errors.php`, `lang/en/errors.php`
- Modify: `bootstrap/app.php`, `routes/api.php`
- Test: `tests/Feature/Api/BootstrapTest.php`

**Interfaces:**
- Consumes: Task 5 auth, Task 3 reference models.
- Produces: middleware alias `active`; `GET /api/me` returning `UserResource`; `GET /api/bootstrap` returning `{user, categories, dimensions, currencies, defaults}` where `categories` is a nested tree of active categories, `dimensions` carries its active values and `is_required`, `currencies` is the configured list with exponents, and `defaults` comes from `StickyDefaults::for(User $user): array` returning `{type, currency, category_id, dimension_values: {<dimensionId>: <valueId>}}` derived from the caller's most recent non deleted transaction (nulls plus `config('money.default')` when there is none). Task 11 reuses `StickyDefaults` for the bot, so it lives in `app/Support`, not in the controller.
- The three `errors.php` lang files carry at least `not_active`, `registration_closed` and `blocked`, kept in sync across uz, ru and en.

- [ ] **Step 1: Write the failing tests**

```php
<?php

// tests/Feature/Api/BootstrapTest.php

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

it('falls back to the configured currency when the caller has no history', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/bootstrap')
        ->assertOk()
        ->assertJsonPath('defaults.currency', 'UZS')
        ->assertJsonPath('defaults.category_id', null);
});
```

- [ ] **Step 2: Run and confirm failure**

Run: `make test args="--filter=BootstrapTest"`
Expected: FAIL, routes missing.

- [ ] **Step 3: Write the middleware and register the alias**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isActive()) {
            abort(403, __('errors.not_active'));
        }

        return $next($request);
    }
}
```

In `bootstrap/app.php`: `$middleware->alias(['active' => EnsureUserIsActive::class]);`

- [ ] **Step 4: Write the controller and the resources, and register the routes**

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('me', fn (Request $request) => UserResource::make($request->user()))->name('me');
    Route::get('bootstrap', BootstrapController::class)->name('bootstrap');
});
```

`BootstrapController` loads root categories with recursive `children` (active only, ordered by `sort` then `name`), active dimensions with active values, `config('money.currencies')`, and delegates the defaults to `StickyDefaults::for($request->user())`, which reads `Transaction::where('user_id', $user->id)->latest('id')->with('dimensionValues')->first()` and falls back to `config('money.default')` with null category and empty dimension values.

- [ ] **Step 5: Run the tests and confirm they pass**

Run: `make test args="--filter=BootstrapTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add active user gate, me and bootstrap endpoints"
```

---

### Task 7: Creating a transaction (validation, idempotency, department snapshot, revision)

**Files:**
- Create: `app/Actions/Transactions/CreateTransaction.php`, `app/Actions/Transactions/RecordRevision.php`
- Create: `app/DataObjects/TransactionInput.php`
- Create: `app/Http/Controllers/Api/TransactionsController.php`, `app/Http/Requests/Api/StoreTransactionRequest.php`
- Create: `app/Http/Resources/TransactionResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/CreateTransactionTest.php`

**Interfaces:**
- Consumes: Tasks 4, 5, 6.
- Produces: `TransactionInput` readonly object with `int $userId, TransactionType $type, string $amount, string $currency, CarbonImmutable $occurredOn, int $categoryId, ?string $note, array<int, int> $dimensionValues, ?string $idempotencyKey`; `CreateTransaction::handle(TransactionInput $input, User $actor): Transaction`; `RecordRevision::handle(Transaction $transaction, RevisionAction $action, User $actor): void`; `POST /api/transactions` returning 201 with `TransactionResource`, or 200 with the existing record when the idempotency key repeats.

`TransactionResource` shape: `{id, type, amount_minor, amount, currency, occurred_on, note, category: {id, name}, user: {id, name}, department: {id, name}|null, dimension_values: [{dimension_id, dimension_key, value_id, value_name}], created_at, updated_at}`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

// tests/Feature/Api/CreateTransactionTest.php

use App\Enums\CategoryAppliesTo;
use App\Enums\RevisionAction;
use App\Models\Category;
use App\Models\Department;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a transaction for the caller', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    Sanctum::actingAs($user);
    $category = Category::factory()->create(['applies_to' => CategoryAppliesTo::Expense]);

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '120000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
        'note' => 'taksi',
    ])->assertCreated()
        ->assertJsonPath('data.amount_minor', 120000)
        ->assertJsonPath('data.amount', '120000');

    $transaction = Transaction::sole();

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->created_by)->toBe($user->id)
        ->and($transaction->department_id)->toBe($department->id)
        ->and($transaction->revisions()->where('action', RevisionAction::Created)->count())->toBe(1);
});

it('ignores a user_id sent by a staff caller', function () {
    $caller = User::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($caller);
    $category = Category::factory()->create();

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
        'user_id' => $other->id,
    ])->assertCreated();

    expect(Transaction::sole()->user_id)->toBe($caller->id);
});

it('rejects a category that does not accept the type', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create(['applies_to' => CategoryAppliesTo::Expense]);

    $this->postJson('/api/transactions', [
        'type' => 'income',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertStatus(422)->assertJsonValidationErrors('category_id');
});

it('rejects an unsupported currency, a non positive amount and a future date', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $base = [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $this->postJson('/api/transactions', [...$base, 'currency' => 'XXX'])
        ->assertJsonValidationErrors('currency');
    $this->postJson('/api/transactions', [...$base, 'amount' => '0'])
        ->assertJsonValidationErrors('amount');
    $this->postJson('/api/transactions', [...$base, 'occurred_on' => today()->addDays(2)->toDateString()])
        ->assertJsonValidationErrors('occurred_on');
});

it('requires values for required dimensions and stores them', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $branch = Dimension::factory()->create(['key' => 'branch', 'is_required' => true]);
    $value = DimensionValue::factory()->create(['dimension_id' => $branch->id]);
    $base = [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $this->postJson('/api/transactions', $base)
        ->assertJsonValidationErrors('dimension_values');

    $this->postJson('/api/transactions', [...$base, 'dimension_values' => [$branch->id => $value->id]])
        ->assertCreated();

    expect(Transaction::sole()->dimensionValues->pluck('id')->all())->toBe([$value->id]);
});

it('rejects a dimension value that belongs to another dimension', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $foreignValue = DimensionValue::factory()->create();

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
        'dimension_values' => [$branch->id => $foreignValue->id],
    ])->assertJsonValidationErrors('dimension_values');
});

it('snapshots the department and keeps it when the author transfers later', function () {
    $sales = Department::factory()->create();
    $warehouse = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $sales->id]);
    Sanctum::actingAs($user);
    $category = Category::factory()->create();

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertCreated();

    $user->update(['department_id' => $warehouse->id]);

    expect(Transaction::sole()->department_id)->toBe($sales->id);
});

it('returns the same record for a repeated idempotency key', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $key = (string) Str::uuid();
    $payload = [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/transactions', $payload)->assertCreated();
    $second = $this->withHeader('Idempotency-Key', $key)->postJson('/api/transactions', $payload)->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Transaction::count())->toBe(1);
});
```

- [ ] **Step 2: Run and confirm failure**

Run: `make test args="--filter=CreateTransactionTest"`
Expected: FAIL, route missing.

- [ ] **Step 3: Write the request validation**

`StoreTransactionRequest::rules()` uses array notation:

```php
public function rules(): array
{
    return [
        'type' => ['required', Rule::enum(TransactionType::class)],
        'amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/', 'not_in:0,0.0,0.00'],
        'currency' => ['required', 'string', 'size:3', Rule::in(array_keys(config('money.currencies')))],
        'occurred_on' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
        'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
        'note' => ['nullable', 'string', 'max:1000'],
        'dimension_values' => ['array'],
        'dimension_values.*' => ['integer'],
    ];
}
```

`withValidator()` adds three checks: the category accepts the submitted type; every active `is_required` dimension has a value in `dimension_values`; every submitted pair exists and the value truly belongs to that dimension and both are active. All three add errors under `category_id` or `dimension_values` respectively.

- [ ] **Step 4: Write RecordRevision**

```php
<?php

namespace App\Actions\Transactions;

use App\Enums\RevisionAction;
use App\Models\Transaction;
use App\Models\User;

class RecordRevision
{
    public function handle(Transaction $transaction, RevisionAction $action, User $actor): void
    {
        $transaction->loadMissing('dimensionValues');

        $transaction->revisions()->create([
            'action' => $action,
            'actor_id' => $actor->id,
            'created_at' => now(),
            'snapshot' => [
                'type' => $transaction->type->value,
                'amount_minor' => $transaction->amount_minor,
                'currency' => $transaction->currency,
                'occurred_on' => $transaction->occurred_on->toDateString(),
                'category_id' => $transaction->category_id,
                'note' => $transaction->note,
                'user_id' => $transaction->user_id,
                'department_id' => $transaction->department_id,
                'dimension_values' => $transaction->dimensionValues
                    ->mapWithKeys(fn ($value) => [$value->pivot->dimension_id => $value->id])
                    ->all(),
                'deleted_at' => $transaction->deleted_at?->toIso8601String(),
            ],
        ]);
    }
}
```

- [ ] **Step 5: Write CreateTransaction**

The action runs inside `DB::transaction()`: it creates the row with `user_id` and `created_by` from the actor, `department_id` copied from `$actor->department_id` as a snapshot, `amount_minor` from `Money::toMinor()`, syncs the dimension values into the pivot with their `dimension_id`, then calls `RecordRevision` with `RevisionAction::Created` and returns the fresh model with `category`, `user`, `department` and `dimensionValues.dimension` loaded.

The controller checks `Idempotency-Key` first: an existing transaction with that key belonging to the caller is returned with status 200 before any write.

- [ ] **Step 6: Register the route**

```php
Route::post('transactions', [TransactionsController::class, 'store'])->name('transactions.store');
```

inside the existing `auth:sanctum` plus `active` group.

- [ ] **Step 7: Run the tests and confirm they pass**

Run: `make test args="--filter=CreateTransactionTest"`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: create transactions with validation, idempotency and revisions"
```

---

### Task 8: Scoping, listing, updating, deleting and revision history

**Files:**
- Create: `app/Support/TransactionScope.php`, `app/Policies/TransactionPolicy.php`
- Create: `app/Actions/Transactions/UpdateTransaction.php`, `app/Actions/Transactions/DeleteTransaction.php`
- Create: `app/Http/Requests/Api/UpdateTransactionRequest.php`, `app/Http/Requests/Api/TransactionFilterRequest.php`
- Create: `app/Http/Resources/TransactionRevisionResource.php`
- Modify: `app/Http/Controllers/Api/TransactionsController.php`, `routes/api.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Api/TransactionScopeTest.php`, `tests/Feature/Api/UpdateDeleteTransactionTest.php`

**Interfaces:**
- Consumes: Task 7.
- Produces: `TransactionScope::apply(Builder $query, User $viewer): Builder`; `TransactionPolicy::view|update|delete`; `GET /api/transactions` with cursor paging and filters `from, to, type, category_id, currency, user_id, department_id, dimension[<key>]`; `PATCH /api/transactions/{transaction}`; `DELETE /api/transactions/{transaction}`; `GET /api/transactions/{transaction}/revisions`.

- [ ] **Step 1: Write the failing scope tests**

```php
<?php

// tests/Feature/Api/TransactionScopeTest.php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

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

it('filters by period, type, category and dimension', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));
    $old = Transaction::factory()->create(['occurred_on' => today()->subMonth()]);

    $this->getJson('/api/transactions?from='.today()->toDateString())
        ->assertOk()
        ->assertJsonMissing(['id' => $old->id]);
});
```

- [ ] **Step 2: Run and confirm failure**

Run: `make test args="--filter=TransactionScopeTest"`
Expected: FAIL, route missing.

- [ ] **Step 3: Write TransactionScope**

```php
<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TransactionScope
{
    public static function apply(Builder $query, User $viewer): Builder
    {
        if ($viewer->canSeeEverything()) {
            return $query;
        }

        if ($viewer->role === UserRole::Manager) {
            $departmentIds = $viewer->managedDepartments()->pluck('departments.id');

            return $query->whereIn('transactions.department_id', $departmentIds);
        }

        return $query->where('transactions.user_id', $viewer->id);
    }
}
```

Out of scope single records resolve to 404 by applying the same scope in a route model binding, so existence is not leaked.

- [ ] **Step 4: Write the list endpoint**

`TransactionsController::index()` starts from `Transaction::query()->with(...)`, applies `TransactionScope::apply()` first, then the validated filters from `TransactionFilterRequest` (`from`/`to` against `occurred_on`, `type`, `category_id` including descendants, `currency`, `user_id`, `department_id`, and `dimension[<key>]=<valueId>` translated into a `whereHas` on the pivot), orders by `occurred_on desc, id desc` and returns `cursorPaginate(50)`.

- [ ] **Step 5: Run the scope tests and confirm they pass**

Run: `make test args="--filter=TransactionScopeTest"`
Expected: PASS.

- [ ] **Step 6: Write the failing update and delete tests**

```php
<?php

// tests/Feature/Api/UpdateDeleteTransactionTest.php

use App\Enums\RevisionAction;
use App\Enums\UserRole;
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

it('soft deletes and logs the deletion', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->for($user)->create();

    $this->deleteJson("/api/transactions/{$transaction->id}")->assertNoContent();

    expect(Transaction::count())->toBe(0)
        ->and(Transaction::withTrashed()->sole()->revisions()->where('action', RevisionAction::Deleted)->count())->toBe(1);
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
```

- [ ] **Step 7: Run and confirm failure**

Run: `make test args="--filter=UpdateDeleteTransactionTest"`
Expected: FAIL.

- [ ] **Step 8: Write the policy, the actions and the routes**

`TransactionPolicy::update()` and `delete()` return true for an admin, or for the author when `$transaction->user_id === $user->id`; both return false for `owner` and for a manager acting on someone else's record. `view()` defers to `TransactionScope`. Register the policy in `AppServiceProvider`.

`UpdateTransaction` applies only the submitted fields (`UpdateTransactionRequest` marks each of them `sometimes` with the same rules as Task 7, and never accepts `user_id` or `department_id`), re-syncs the dimension pivot when `dimension_values` is present, and records a revision with `RevisionAction::Updated`. `DeleteTransaction` records the revision first, then soft deletes.

- [ ] **Step 9: Run the tests and confirm they pass**

Run: `make test args="--filter='UpdateDeleteTransactionTest|TransactionScopeTest'"`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: scope, list, edit and delete transactions with history"
```

---

### Task 9: Reports (summary and trend)

**Files:**
- Create: `app/Reports/TransactionFilters.php`, `app/Reports/SummaryReport.php`, `app/Reports/TrendReport.php`
- Create: `app/Http/Controllers/Api/ReportsController.php`, `app/Http/Requests/Api/ReportRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/SummaryReportTest.php`, `tests/Feature/Api/TrendReportTest.php`

**Interfaces:**
- Consumes: Task 8 scope and filters.
- Produces: `TransactionFilters::fromRequest(Request $request): self` and `TransactionFilters::apply(Builder $query): Builder` (shared by list, reports and export); `SummaryReport::build(User $viewer, TransactionFilters $filters, string $groupBy): array`; `TrendReport::build(User $viewer, TransactionFilters $filters, string $interval): array`; `GET /api/reports/summary?group_by=category|user|currency|dimension:<key>`; `GET /api/reports/trend?interval=day|week|month`.

Response shape for summary:

```json
{
  "totals": [{"currency": "UZS", "type": "expense", "amount_minor": 320000, "amount": "320000", "count": 4}],
  "groups": [{"key": "12", "label": "Taksi", "currency": "UZS", "type": "expense", "amount_minor": 120000, "amount": "120000", "count": 2}]
}
```

Response shape for trend: `{"points": [{"period": "2026-07-26", "currency": "UZS", "type": "expense", "amount_minor": 120000, "amount": "120000", "count": 2}]}`.

- [ ] **Step 1: Write the failing summary tests**

```php
<?php

// tests/Feature/Api/SummaryReportTest.php

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

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
    $department = App\Models\Department::factory()->create();
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
```

- [ ] **Step 2: Write the failing trend test**

```php
<?php

// tests/Feature/Api/TrendReportTest.php

use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

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
```

- [ ] **Step 3: Run both and confirm failure**

Run: `make test args="--filter='SummaryReportTest|TrendReportTest'"`
Expected: FAIL, routes missing.

- [ ] **Step 4: Extract TransactionFilters and reuse it in the list endpoint**

`TransactionFilters` holds `?CarbonImmutable $from, ?CarbonImmutable $to, ?TransactionType $type, ?int $categoryId, ?string $currency, ?int $userId, ?int $departmentId, array<string, int> $dimensions` and applies them to a query. `TransactionsController::index()` is refactored to use it, and the Task 8 tests must still pass afterwards.

- [ ] **Step 5: Write SummaryReport and TrendReport**

`SummaryReport` runs two aggregate queries over the scoped and filtered builder: totals grouped by `currency, type`, and groups grouped by the requested key plus `currency, type`, using `selectRaw('sum(amount_minor) as amount_minor, count(*) as count')`. `group_by=dimension:<key>` joins `transaction_dimension_values` and `dimension_values` for that dimension key. `group_by=user` joins `users` for the label, `category` joins `categories`. Every row is decorated with `Money::toDecimal()`.

`TrendReport` groups by `date_trunc('<interval>', occurred_on)` with the interval whitelisted to `day|week|month` (never interpolated from raw input), plus `currency, type`, ordered by period.

Both take the viewer and call `TransactionScope::apply()` before anything else.

- [ ] **Step 6: Run the report tests and confirm they pass**

Run: `make test args="--filter='SummaryReportTest|TrendReportTest'"`
Expected: PASS.

- [ ] **Step 7: Run the whole suite to catch the list refactor**

Run: `make test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add scoped summary and trend reports"
```

---

### Task 10: CSV export

**Files:**
- Create: `app/Http/Controllers/Api/TransactionExportController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ExportTest.php`

**Interfaces:**
- Consumes: Task 9 filters and scope.
- Produces: `GET /api/exports/transactions` streaming `text/csv` with the header row `date,type,amount,currency,category,staff,department,note` followed by one row per transaction plus one column per active dimension.

Note for the human: the spec mentions XLSX as well. XLSX needs a new Composer dependency (`openspout/openspout` or `maatwebsite/excel`), which the Global Constraints forbid without approval. This task ships CSV only; XLSX stays open until approved.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Api/ExportTest.php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('exports the scoped and filtered transactions as csv', function () {
    $staff = User::factory()->create(['name' => 'Alisher']);
    $category = Category::factory()->create(['name' => 'Taksi']);
    Transaction::factory()->for($staff)->create([
        'category_id' => $category->id, 'amount_minor' => 120000, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->create(['occurred_on' => today()]);
    Sanctum::actingAs($staff);

    $response = $this->get('/api/exports/transactions')->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('date,type,amount,currency,category,staff,department,note')
        ->and($csv)->toContain('Taksi')
        ->and(substr_count(trim($csv), "\n"))->toBe(1);
});

it('exports everything for an owner', function () {
    Transaction::factory()->count(3)->create(['occurred_on' => today()]);
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $csv = $this->get('/api/exports/transactions')->assertOk()->streamedContent();

    expect(substr_count(trim($csv), "\n"))->toBe(3);
});
```

- [ ] **Step 2: Run and confirm failure**

Run: `make test args="--filter=ExportTest"`
Expected: FAIL, route missing.

- [ ] **Step 3: Write the streaming controller**

It builds the same scoped and filtered query, then returns `response()->streamDownload()` writing with `fputcsv` while chunking the query with `chunkById(500)` so memory stays flat. Amounts are written with `Money::toDecimal()`.

- [ ] **Step 4: Run the test and confirm it passes**

Run: `make test args="--filter=ExportTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: export scoped transactions to csv"
```

---

### Task 11: Telegram bot quick entry with confirmation

**Files:**
- Create: `app/Services/Telegram/TelegramClient.php`, `app/Services/Telegram/AmountNoteParser.php`, `app/Services/Telegram/ParsedEntry.php`, `app/Services/Telegram/DraftPresenter.php`
- Create: `app/Actions/Drafts/StartDraft.php`, `app/Actions/Drafts/ConfirmDraft.php`
- Create: `app/Http/Controllers/TelegramWebhookController.php`, `app/Http/Middleware/VerifyTelegramWebhook.php`
- Create: `app/Console/Commands/PruneEntryDraftsCommand.php`
- Create: `lang/uz/bot.php`, `lang/ru/bot.php`, `lang/en/bot.php`
- Modify: `routes/web.php`, `routes/console.php`
- Test: `tests/Unit/AmountNoteParserTest.php`, `tests/Feature/Telegram/WebhookTest.php`, `tests/Feature/Telegram/DraftConfirmationTest.php`

**Interfaces:**
- Consumes: Tasks 5, 6, 7 (`CreateTransaction`, `TransactionInput`, sticky defaults logic from `BootstrapController`, extracted into `app/Support/StickyDefaults.php` with `StickyDefaults::for(User $user): array` and reused by both).
- Produces: `AmountNoteParser::parse(string $text): ?ParsedEntry` where `ParsedEntry` is a readonly object `{string $amount, ?string $note}`; `TelegramClient::sendMessage(int $chatId, string $text, ?array $keyboard = null): array`, `::editMessageText(int $chatId, int $messageId, string $text, ?array $keyboard = null): void`, `::answerCallbackQuery(string $id, ?string $text = null): void`; `StartDraft::handle(User $user, ParsedEntry $entry): EntryDraft`; `ConfirmDraft::handle(EntryDraft $draft, User $user): Transaction`; `POST /telegram/webhook`.

Callback data grammar (kept under Telegram's 64 byte limit): `d:<draftId8>:ok`, `d:<draftId8>:cat`, `d:<draftId8>:c:<categoryId>`, `d:<draftId8>:dim:<dimensionId>:<valueId>`, `d:<draftId8>:x`. `<draftId8>` is the first 8 characters of the draft uuid, looked up with a `where('id', 'like', $prefix.'%')` restricted to the calling user.

- [ ] **Step 1: Write the failing parser test**

```php
<?php

// tests/Unit/AmountNoteParserTest.php

use App\Services\Telegram\AmountNoteParser;

it('parses a bare amount', function () {
    $entry = (new AmountNoteParser)->parse('120000');

    expect($entry->amount)->toBe('120000')
        ->and($entry->note)->toBeNull();
});

it('parses an amount with a note', function () {
    $entry = (new AmountNoteParser)->parse('120000 taksi Chilonzor');

    expect($entry->amount)->toBe('120000')
        ->and($entry->note)->toBe('taksi Chilonzor');
});

it('normalises grouped and decimal separators', function () {
    expect((new AmountNoteParser)->parse('120 000')->amount)->toBe('120000')
        ->and((new AmountNoteParser)->parse("120'000")->amount)->toBe('120000')
        ->and((new AmountNoteParser)->parse('12,50 kofe')->amount)->toBe('12.50');
});

it('returns null when there is no leading amount', function () {
    expect((new AmountNoteParser)->parse('salom'))->toBeNull()
        ->and((new AmountNoteParser)->parse(''))->toBeNull();
});
```

- [ ] **Step 2: Run and confirm failure**

Run: `make test args="--filter=AmountNoteParserTest"`
Expected: FAIL.

- [ ] **Step 3: Write the parser**

It matches a leading run of digits, spaces, apostrophes, dots and commas, strips group separators, converts a single trailing comma group into a decimal point, rejects a result that is not a positive number, and treats the remainder as the note (null when empty).

- [ ] **Step 4: Run the parser test and confirm it passes**

Run: `make test args="--filter=AmountNoteParserTest"`
Expected: PASS.

- [ ] **Step 5: Write the failing webhook tests**

```php
<?php

// tests/Feature/Telegram/WebhookTest.php

use App\Models\EntryDraft;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.telegram.webhook_secret', 'hook-secret');
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
});

function sendUpdate(array $update, string $secret = 'hook-secret')
{
    return test()->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)
        ->postJson('/telegram/webhook', $update);
}

function textUpdate(int $telegramId, string $text): array
{
    return [
        'update_id' => 1,
        'message' => [
            'message_id' => 10,
            'chat' => ['id' => $telegramId],
            'from' => ['id' => $telegramId, 'first_name' => 'Alisher', 'language_code' => 'uz'],
            'text' => $text,
        ],
    ];
}

it('rejects an update without the secret header', function () {
    sendUpdate(textUpdate(111, '1000'), 'wrong')->assertStatus(403);
});

it('creates a draft and writes nothing when a known user sends an amount', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    App\Models\Category::factory()->create();

    sendUpdate(textUpdate(111, '120000 taksi'))->assertOk();

    expect(EntryDraft::count())->toBe(1)
        ->and(Transaction::count())->toBe(0)
        ->and(EntryDraft::sole()->payload['note'])->toBe('taksi');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage'));
});

it('answers with a hint when the message has no amount', function () {
    User::factory()->create(['telegram_id' => 111]);

    sendUpdate(textUpdate(111, 'salom'))->assertOk();

    expect(EntryDraft::count())->toBe(0);
});

it('creates a pending user when registration is open and refuses to record', function () {
    Setting::put('registration_open', true);

    sendUpdate(textUpdate(222, '1000'))->assertOk();

    expect(User::where('telegram_id', 222)->exists())->toBeTrue()
        ->and(EntryDraft::count())->toBe(0);
});

it('creates nothing when registration is closed', function () {
    Setting::put('registration_open', false);

    sendUpdate(textUpdate(333, '1000'))->assertOk();

    expect(User::where('telegram_id', 333)->exists())->toBeFalse();
});
```

```php
<?php

// tests/Feature/Telegram/DraftConfirmationTest.php

use App\Models\Category;
use App\Models\EntryDraft;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.telegram.webhook_secret', 'hook-secret');
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
});

function callbackUpdate(int $telegramId, string $data): array
{
    return [
        'update_id' => 2,
        'callback_query' => [
            'id' => 'cb-1',
            'from' => ['id' => $telegramId, 'first_name' => 'Alisher', 'language_code' => 'uz'],
            'message' => ['message_id' => 42, 'chat' => ['id' => $telegramId]],
            'data' => $data,
        ],
    ];
}

it('writes the transaction only on confirmation', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '120000 taksi'));
    $draft = EntryDraft::sole();

    expect(Transaction::count())->toBe(0);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    $transaction = Transaction::sole();

    expect($transaction->amount_minor)->toBe(120000)
        ->and($transaction->note)->toBe('taksi')
        ->and($transaction->user_id)->toBe($user->id)
        ->and($transaction->idempotency_key)->toBe($draft->id)
        ->and(EntryDraft::count())->toBe(0);
});

it('does not double write when confirmation is tapped twice', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));
    $draftId = EntryDraft::sole()->id;

    sendUpdate(callbackUpdate(111, 'd:'.substr($draftId, 0, 8).':ok'));
    sendUpdate(callbackUpdate(111, 'd:'.substr($draftId, 0, 8).':ok'));

    expect(Transaction::count())->toBe(1);
});

it('refuses an expired draft', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();
    $draft->update(['expires_at' => now()->subMinute()]);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(0);
});

it('refuses a draft belonging to another user', function () {
    User::factory()->create(['telegram_id' => 111]);
    User::factory()->create(['telegram_id' => 999]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(999, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(0)
        ->and(EntryDraft::count())->toBe(1);
});

it('cancels a draft', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':x'))->assertOk();

    expect(EntryDraft::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

it('changes the category of a draft without writing', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    $other = Category::factory()->create(['name' => 'Ovqat']);
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':c:'.$other->id))->assertOk();

    expect($draft->fresh()->payload['category_id'])->toBe($other->id)
        ->and(Transaction::count())->toBe(0);
});

it('asks for required dimensions before showing the preview', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    $branch = App\Models\Dimension::factory()->create(['key' => 'branch', 'is_required' => true]);
    $value = App\Models\DimensionValue::factory()->create(['dimension_id' => $branch->id]);
    sendUpdate(textUpdate(111, '1000'));
    $draft = EntryDraft::sole();

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::count())->toBe(0);

    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).":dim:{$branch->id}:{$value->id}"));
    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'))->assertOk();

    expect(Transaction::sole()->dimensionValues->pluck('id')->all())->toBe([$value->id]);
});
```

- [ ] **Step 6: Run and confirm failure**

Run: `make test args="--filter='WebhookTest|DraftConfirmationTest'"`
Expected: FAIL, route missing.

- [ ] **Step 7: Write the webhook middleware, client, presenter and controller**

`VerifyTelegramWebhook` compares the `X-Telegram-Bot-Api-Secret-Token` header with `config('services.telegram.webhook_secret')` using `hash_equals` and aborts with 403 otherwise. The route is registered in `routes/web.php` with that middleware and without CSRF.

`TelegramClient` wraps `Http::baseUrl(config('services.telegram.api_url').'/bot'.$token)` with the three methods above.

`DraftPresenter` renders the preview text from the draft payload using `lang/*/bot.php` keys in the user's locale, and builds the inline keyboard rows: confirm, change category, open the mini app, cancel.

`TelegramWebhookController` handles two update shapes. For a message: resolve the user through `ResolveTelegramUser` (a pending or rejected user gets the corresponding message and nothing else happens), parse the text, and on success create a draft through `StartDraft` (payload from `StickyDefaults::for()` merged with the parsed amount and note, `expires_at` one hour out) and send the preview. For a callback: parse the callback data, load the draft restricted to that user, then act on the verb. `ok` fills in any missing required dimension by asking for it instead of writing; when nothing is missing it calls `ConfirmDraft`, which calls `CreateTransaction` with `idempotency_key` set to the draft id, deletes the draft and edits the message into the saved summary. Every branch answers the callback query so the client stops spinning.

- [ ] **Step 8: Write the prune command and schedule it**

`PruneEntryDraftsCommand` (`transactions:prune-drafts`) deletes drafts with `expires_at` in the past, reports the count with `$this->comment()`, and is scheduled hourly in `routes/console.php`.

- [ ] **Step 9: Run the bot tests and confirm they pass**

Run: `make test args="--filter='WebhookTest|DraftConfirmationTest|AmountNoteParserTest'"`
Expected: PASS.

- [ ] **Step 10: Run the whole suite, analyze and format**

Run: `make test`, `make analyze`, `make pint`
Expected: all clean.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: add telegram bot quick entry with confirmation"
```

---

### Task 12: Filament admin panel

**Files:**
- Create: `app/Filament/Resources/UserResource.php`, `DepartmentResource.php`, `CategoryResource.php`, `DimensionResource.php` (with a values relation manager), `TransactionResource.php` (with a read only revisions relation manager)
- Create: `app/Filament/Pages/ManageSettings.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`, `app/Models/User.php` (implement `FilamentUser`)
- Test: `tests/Feature/Filament/AdminAccessTest.php`

**Interfaces:**
- Consumes: every model and enum built so far.
- Produces: an admin panel at `/admin` open only to `UserRole::Admin` and `UserRole::Owner`, where owners get read only access to transactions and reports while admins additionally manage users, departments, categories, dimensions and settings.

- [ ] **Step 1: Write the failing access test**

```php
<?php

// tests/Feature/Filament/AdminAccessTest.php

use App\Enums\UserRole;
use App\Models\User;

it('lets an admin into the panel', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get('/admin')
        ->assertSuccessful();
});

it('keeps staff and managers out of the panel', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))->get('/admin')->assertForbidden();
});
```

- [ ] **Step 2: Run and confirm failure**

Run: `make test args="--filter=AdminAccessTest"`
Expected: FAIL.

- [ ] **Step 3: Implement panel access**

```php
public function canAccessPanel(Panel $panel): bool
{
    if (! $this->isActive()) {
        return false;
    }

    return in_array($this->role, [UserRole::Admin, UserRole::Owner], true);
}
```

- [ ] **Step 4: Run the access test and confirm it passes**

Run: `make test args="--filter=AdminAccessTest"`
Expected: PASS.

- [ ] **Step 5: Build the resources**

`UserResource` lists telegram id, name, username, role, status and department, filters by status, and offers an "Activate" bulk and row action that sets `status` to `Active`; role and department are editable there. Pending users are the default filter so activation is the first thing an admin sees.

`DepartmentResource` manages departments and their managers (a multi select onto `department_manager`).

`CategoryResource` manages the tree (parent select, `applies_to`, `is_active`, `sort`).

`DimensionResource` manages dimensions with a values relation manager. The `is_required` field carries the helper text: every required dimension adds one tap to bot entry, so mark only what is truly mandatory.

`TransactionResource` is read only (no create, no edit for owners; admins may edit through the API path only) and lists date, staff, department, category, amount with currency, note, with filters mirroring the API. Its revisions relation manager shows action, actor, timestamp and the snapshot as JSON, read only.

`ManageSettings` is a simple page with the `registration_open` toggle persisted through `Setting::put()`.

- [ ] **Step 6: Run the whole suite, analyze and format**

Run: `make test`, `make analyze`, `make pint`
Expected: all clean.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add filament admin panel"
```

---

### Task 13: Seeders, webhook registration command and the end to end check

**Files:**
- Create: `database/seeders/ReferenceDataSeeder.php`, modify `database/seeders/DatabaseSeeder.php`
- Create: `app/Console/Commands/SetTelegramWebhookCommand.php`
- Modify: `README.md`
- Test: `tests/Feature/EndToEndEntryTest.php`

**Interfaces:**
- Consumes: everything above.
- Produces: `php artisan db:seed` creating the starter departments, categories, dimensions and one admin from `ADMIN_TELEGRAM_ID`; `php artisan telegram:set-webhook` pointing Telegram at `config('app.url').'/telegram/webhook'` with the secret token.

- [ ] **Step 1: Write the failing end to end test**

```php
<?php

// tests/Feature/EndToEndEntryTest.php

use App\Models\Category;
use App\Models\EntryDraft;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('services.telegram.webhook_secret', 'hook-secret');
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
});

it('records a bot entry and shows it in the report of the same staff member', function () {
    $user = User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create(['name' => 'Taksi']);

    sendUpdate(textUpdate(111, '120000 taksi'));
    $draft = EntryDraft::sole();
    sendUpdate(callbackUpdate(111, 'd:'.substr($draft->id, 0, 8).':ok'));

    expect(Transaction::count())->toBe(1);

    Sanctum::actingAs($user);

    $this->getJson('/api/reports/summary?from='.today()->toDateString()
        .'&to='.today()->toDateString().'&group_by=category')
        ->assertOk()
        ->assertJsonPath('groups.0.label', 'Taksi')
        ->assertJsonPath('groups.0.amount_minor', 120000);

    $this->getJson('/api/transactions')->assertOk()->assertJsonCount(1, 'data');
});

it('hides that transaction from an unrelated staff member', function () {
    User::factory()->create(['telegram_id' => 111]);
    Category::factory()->create();
    sendUpdate(textUpdate(111, '5000'));
    sendUpdate(callbackUpdate(111, 'd:'.substr(EntryDraft::sole()->id, 0, 8).':ok'));

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/transactions')->assertOk()->assertJsonCount(0, 'data');
});
```

- [ ] **Step 2: Run and confirm it fails only where expected**

Run: `make test args="--filter=EndToEndEntryTest"`
Expected: FAIL until the seeder and helpers are shared; the helper functions `sendUpdate`, `textUpdate` and `callbackUpdate` move from the Task 11 test files into `tests/Pest.php`.

- [ ] **Step 3: Write the seeder**

`ReferenceDataSeeder` creates the departments (Sales, Warehouse, Office), an expense category tree (Transport with Taksi and Yoqilgi, Ofis with Kanselyariya and Kommunal, Marketing, Boshqa), one income category (Boshqa kirim), the `branch` dimension with a few values, and, when `ADMIN_TELEGRAM_ID` is set, an active admin user with that telegram id.

- [ ] **Step 4: Write the webhook command**

`SetTelegramWebhookCommand` calls `setWebhook` with the URL and `secret_token`, prints the Telegram response, and fails loudly when `ok` is false.

- [ ] **Step 5: Run the whole suite, analyze and format**

Run: `make test`, `make analyze`, `make pint`
Expected: all clean.

- [ ] **Step 6: Update the README with the bot setup steps**

Document: create the bot with BotFather, set `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET` and `ADMIN_TELEGRAM_ID`, expose the app over HTTPS in development, run `make artisan cmd="telegram:set-webhook"`, then open the bot and send an amount.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: seed reference data and register the telegram webhook"
```

---

## After the plan

The backend is done when every task is committed, `make test`, `make analyze` and `make pint` are clean, and the end to end test passes. This work touches authentication and personal spend data, so per ROLES.md it is `high-risk`: it needs the cross family review (Codex CLI as primary reviewer, since Claude built it), the security specialist pass on Task 5 and Task 8, and one complementary flagship pass with an adversarial failure analysis mandate.

Open item for the human: XLSX export was deferred in Task 10 because it needs a new Composer dependency. Approve `openspout/openspout` (lighter, MIT) or `maatwebsite/excel`, or leave the export at CSV.

The React mini app gets its own plan once this API is running, covering the amount keypad entry screen, the report screens on `/api/reports/*`, Telegram theme integration and the uz/ru/en strings.
