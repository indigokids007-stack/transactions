<?php

use App\Models\Setting;

it('stores and reads a setting', function () {
    Setting::put('registration_open', false);

    expect(Setting::get('registration_open'))->toBeFalse();
});

it('returns the default when a setting is missing', function () {
    expect(Setting::get('registration_open', true))->toBeTrue();
});
