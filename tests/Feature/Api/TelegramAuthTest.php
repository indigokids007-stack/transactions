<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    config()->set('services.telegram.bot_token', 'test-bot-token');
    cache()->clear();
});

afterEach(fn () => cache()->clear());

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

it('requires init_data', function () {
    $this->postJson('/api/auth/telegram', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('init_data');
});

it('never issues a token to a pending user', function () {
    Setting::put('registration_open', true);

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])->assertOk();

    expect(PersonalAccessToken::count())->toBe(0);
});

it('rate limits repeated attempts', function () {
    foreach (range(1, 20) as $ignored) {
        $this->postJson('/api/auth/telegram', ['init_data' => 'nonsense'])->assertStatus(401);
    }

    $this->postJson('/api/auth/telegram', ['init_data' => 'nonsense'])->assertStatus(429);
});

it('revokes the previous mini app token so a second login leaves exactly one', function () {
    User::factory()->create(['telegram_id' => 111, 'status' => UserStatus::Active]);

    $first = $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])->json('token');
    $second = $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])->json('token');

    expect($second)->not->toBe($first)
        ->and(PersonalAccessToken::count())->toBe(1)
        ->and(PersonalAccessToken::findToken($first))->toBeNull()
        ->and(PersonalAccessToken::findToken($second))->not->toBeNull();
});

it('exposes no sanctum route that skips the status check', function () {
    $this->getJson('/api/user')->assertNotFound();
});

it('reports can_manage false for a manager with no departments attached', function () {
    User::factory()->create([
        'telegram_id' => 111,
        'status' => UserStatus::Active,
        'role' => UserRole::Manager,
    ]);

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])
        ->assertOk()
        ->assertJsonPath('user.permissions.can_see_all', false)
        ->assertJsonPath('user.permissions.can_manage', false);
});

it('reports can_manage true once a department is attached', function () {
    $manager = User::factory()->create([
        'telegram_id' => 111,
        'status' => UserStatus::Active,
        'role' => UserRole::Manager,
    ]);
    $manager->managedDepartments()->attach(Department::factory()->create());

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])
        ->assertOk()
        ->assertJsonPath('user.permissions.can_manage', true);
});

it('reports both permissions for an owner', function () {
    User::factory()->create([
        'telegram_id' => 111,
        'status' => UserStatus::Active,
        'role' => UserRole::Owner,
    ]);

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])
        ->assertOk()
        ->assertJsonPath('user.permissions.can_see_all', true)
        ->assertJsonPath('user.permissions.can_manage', true);
});

it('never overwrites a stored locale with the telegram language', function () {
    User::factory()->create([
        'telegram_id' => 111,
        'status' => UserStatus::Active,
        'locale' => 'ru',
    ]);

    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData()])
        ->assertOk()
        ->assertJsonPath('user.locale', 'ru');

    expect(User::where('telegram_id', 111)->value('locale'))->toBe('ru');
});

it('takes the locale from telegram once, when it creates the user', function () {
    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData([
        'user' => json_encode(['id' => 111, 'first_name' => 'Alisher', 'language_code' => 'ru']),
    ])])
        ->assertOk()
        ->assertJsonPath('user.locale', 'ru');
});

it('falls back to uzbek for an unsupported telegram language', function () {
    $this->postJson('/api/auth/telegram', ['init_data' => buildInitData([
        'user' => json_encode(['id' => 111, 'first_name' => 'Alisher', 'language_code' => 'fr']),
    ])])
        ->assertOk()
        ->assertJsonPath('user.locale', 'uz');
});
