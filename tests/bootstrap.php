<?php

/*
|--------------------------------------------------------------------------
| Force the test database before the application boots
|--------------------------------------------------------------------------
|
| `make test` passes DB_DATABASE to the container as a real process
| environment variable, which populates PHP's $_SERVER superglobal at
| process start. Laravel's env() helper reads $_SERVER before $_ENV or
| getenv(), so phpunit.xml's <env force="true"> is not sufficient on its
| own: it can set $_ENV and getenv(), but it cannot touch $_SERVER once
| the process is already running.
|
| Setting all three channels here, in the PHPUnit bootstrap file, runs
| before the Laravel application is created for any test, regardless of
| how the suite is invoked (vendor/bin/pest, php artisan test, or
| make test), so RefreshDatabase always targets transactions_test and
| never the transactions development database.
|
*/

putenv('DB_DATABASE=transactions_test');
$_ENV['DB_DATABASE'] = 'transactions_test';
$_SERVER['DB_DATABASE'] = 'transactions_test';

require __DIR__.'/../vendor/autoload.php';
