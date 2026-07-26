<?php

use App\Exceptions\InvalidInitDataException;
use App\Services\Telegram\InitDataValidator;

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

it('rejects a payload without a hash', function () {
    (new InitDataValidator)->validate('auth_date=1&user=%7B%22id%22%3A1%7D');
})->throws(InvalidInitDataException::class);

it('rejects array shaped fields instead of crashing', function () {
    (new InitDataValidator)->validate('user[]=a&user[]=b&hash=deadbeef');
})->throws(InvalidInitDataException::class);

it('refuses to validate anything when the bot token is not configured', function () {
    $initData = buildInitData();

    config()->set('services.telegram.bot_token', null);

    (new InitDataValidator)->validate($initData);
})->throws(InvalidInitDataException::class);
