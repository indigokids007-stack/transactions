<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Admin Account
    |--------------------------------------------------------------------------
    |
    | ReferenceDataSeeder uses these to create the first admin account. The
    | telegram id is required for the account to be created at all. Filament's
    | default login page authenticates by email and password, so both of
    | those are required together for the seeded admin to be able to log
    | into the panel; when either is left blank the admin is still seeded
    | (so the telegram bot works for them) but without panel credentials,
    | rather than with a blank or guessable password.
    |
    */

    'telegram_id' => env('ADMIN_TELEGRAM_ID'),

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

];
