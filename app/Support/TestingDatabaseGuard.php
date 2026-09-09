<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Abort Laravel tests before RefreshDatabase if the connection looks like production.
 */
class TestingDatabaseGuard
{
    public const PRODUCTION_DATABASE = 'sales';

    public const TESTING_DATABASE = 'sales_testing';

    public const TESTING_USERNAME = 'sales_testing';

    /**
     * @param  array{APP_ENV?:string, DB_DATABASE?:string, DB_USERNAME?:string}  $env
     */
    public static function enforce(array $env): void
    {
        $appEnv = (string) ($env['APP_ENV'] ?? '');
        $database = (string) ($env['DB_DATABASE'] ?? '');
        $username = (string) ($env['DB_USERNAME'] ?? '');

        if ($database === self::PRODUCTION_DATABASE) {
            throw new RuntimeException(
                'Refusing to run tests: DB_DATABASE is the production database "sales".',
            );
        }

        if ($appEnv !== 'testing') {
            throw new RuntimeException(
                'Refusing to run tests: APP_ENV must be "testing".',
            );
        }

        if ($database !== self::TESTING_DATABASE) {
            throw new RuntimeException(
                'Refusing to run tests: DB_DATABASE must be "sales_testing".',
            );
        }

        if ($username === 'sales' || $username === '') {
            throw new RuntimeException(
                'Refusing to run tests: DB_USERNAME must be the isolated testing user, not production "sales".',
            );
        }

        if ($username !== self::TESTING_USERNAME) {
            throw new RuntimeException(
                'Refusing to run tests: DB_USERNAME must be "sales_testing".',
            );
        }
    }

    public static function enforceFromEnvironment(): void
    {
        self::enforce([
            'APP_ENV' => (string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')),
            'DB_DATABASE' => (string) (getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')),
            'DB_USERNAME' => (string) (getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? '')),
        ]);
    }

    public static function enforceFromApplication(Application $app): void
    {
        $connection = (string) $app['config']->get('database.default');

        self::enforce([
            'APP_ENV' => $app->environment(),
            'DB_DATABASE' => (string) $app['config']->get("database.connections.{$connection}.database"),
            'DB_USERNAME' => (string) $app['config']->get("database.connections.{$connection}.username"),
        ]);
    }
}
