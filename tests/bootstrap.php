<?php

/**
 * Force the testing environment before Laravel reads .env.
 * This host's shell often has APP_ENV=production; without this, PHPUnit can
 * RefreshDatabase the production `sales` database.
 */
$testing = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'true',
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => 'sales_testing',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
];

foreach ($testing as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
