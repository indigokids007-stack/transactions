<?php

use App\Enums\UserStatus;
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
