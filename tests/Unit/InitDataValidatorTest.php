<?php

use App\Exceptions\InvalidInitDataException;
use App\Services\Telegram\InitDataValidator;
use Carbon\Carbon;

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

/*
 * This fixture is the only test here that does not build its input with buildInitData().
 * The helper derives the secret exactly the way the validator does, so a swap of the HMAC
 * key and message would keep every other test green while rejecting every real Telegram user.
 *
 * The expected hash below was produced by an independent implementation (Python's hmac module,
 * whose key and message arguments are named rather than positional) from Telegram's spec:
 *
 *   secret_key = HMAC_SHA256(key: "WebAppData", message: "123456:TEST-TOKEN")
 *   hash       = hex(HMAC_SHA256(key: secret_key, message: data_check_string))
 *
 * over the data check string:
 *
 *   auth_date=1750000000\nquery_id=AAA\nuser={"id":111,"first_name":"Alisher","language_code":"uz"}
 *
 * Swapping the key and the message yields 12a7e03e58c9b4d3611302e152ce95f6503053a43954fb7190936cbe74a859f0
 * instead, so this assertion pins the byte level contract.
 */
it('matches a hash derived independently of the test helper', function () {
    config()->set('services.telegram.bot_token', '123456:TEST-TOKEN');
    $this->travelTo(Carbon::createFromTimestamp(1750000000));

    $initData = 'auth_date=1750000000&query_id=AAA'
        .'&user=%7B%22id%22%3A111%2C%22first_name%22%3A%22Alisher%22%2C%22language_code%22%3A%22uz%22%7D'
        .'&hash=321a7579828b819bd66eb34e9930a04905ad67ae00c324788bcc43d06232bbdc';

    $payload = (new InitDataValidator)->validate($initData);

    expect($payload['user']['id'])->toBe(111)
        ->and($payload['user']['language_code'])->toBe('uz');
});

it('refuses to validate anything when the bot token is not configured', function () {
    $initData = buildInitData();

    config()->set('services.telegram.bot_token', null);

    (new InitDataValidator)->validate($initData);
})->throws(InvalidInitDataException::class);
