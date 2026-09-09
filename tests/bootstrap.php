<?php

/**
 * Load isolated testing credentials before Laravel reads production .env.
 * Abort immediately if the database name is production `sales`.
 */

$root = dirname(__DIR__);
$testingEnvPath = $root.'/.env.testing';

if (! is_file($testingEnvPath)) {
    fwrite(STDERR, "Refusing to run tests: {$testingEnvPath} is missing. Copy .env.testing.example and set the sales_testing DB password.\n");
    exit(1);
}

$lines = file($testingEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    fwrite(STDERR, "Refusing to run tests: unable to read {$testingEnvPath}.\n");
    exit(1);
}

foreach ($lines as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    if (! str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);

    if (
        (str_starts_with($value, '"') && str_ends_with($value, '"'))
        || (str_starts_with($value, "'") && str_ends_with($value, "'"))
    ) {
        $value = substr($value, 1, -1);
    }

    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$forced = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'true',
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => 'sales_testing',
    'DB_USERNAME' => 'sales_testing',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
];

foreach ($forced as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require $root.'/vendor/autoload.php';

try {
    App\Support\TestingDatabaseGuard::enforceFromEnvironment();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
