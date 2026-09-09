<?php

namespace Tests\Unit;

use App\Support\TestingDatabaseGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestingDatabaseGuardTest extends TestCase
{
    public function test_guard_allows_isolated_testing_connection(): void
    {
        $this->expectNotToPerformAssertions();

        TestingDatabaseGuard::enforce([
            'APP_ENV' => 'testing',
            'DB_DATABASE' => 'sales_testing',
            'DB_USERNAME' => 'sales_testing',
        ]);
    }

    public function test_guard_rejects_production_database_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('production database "sales"');

        TestingDatabaseGuard::enforce([
            'APP_ENV' => 'testing',
            'DB_DATABASE' => 'sales',
            'DB_USERNAME' => 'sales_testing',
        ]);
    }

    public function test_guard_rejects_non_testing_environment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV must be "testing"');

        TestingDatabaseGuard::enforce([
            'APP_ENV' => 'production',
            'DB_DATABASE' => 'sales_testing',
            'DB_USERNAME' => 'sales_testing',
        ]);
    }

    public function test_guard_rejects_production_database_user(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_USERNAME must be the isolated testing user');

        TestingDatabaseGuard::enforce([
            'APP_ENV' => 'testing',
            'DB_DATABASE' => 'sales_testing',
            'DB_USERNAME' => 'sales',
        ]);
    }
}
