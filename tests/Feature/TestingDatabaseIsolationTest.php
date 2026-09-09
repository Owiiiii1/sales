<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use PDOException;
use Tests\TestCase;

class TestingDatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_isolated_testing_database_and_user(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sales_testing', config('database.connections.mysql.database'));
        $this->assertSame('sales_testing', config('database.connections.mysql.username'));
        $this->assertNotSame('sales', config('database.connections.mysql.database'));
        $this->assertNotSame('sales', config('database.connections.mysql.username'));
    }

    public function test_testing_user_can_use_sales_testing_but_not_production_sales(): void
    {
        $host = (string) config('database.connections.mysql.host');
        $username = (string) config('database.connections.mysql.username');
        $password = (string) config('database.connections.mysql.password');

        $this->assertSame('sales_testing', $username);
        $this->assertNotSame('', $password);

        $testing = new PDO(
            "mysql:host={$host};dbname=sales_testing;charset=utf8mb4",
            $username,
            $password,
        );
        $this->assertSame(1, (int) $testing->query('SELECT 1')->fetchColumn());

        $denied = false;

        try {
            new PDO(
                "mysql:host={$host};dbname=sales;charset=utf8mb4",
                $username,
                $password,
            );
        } catch (PDOException) {
            $denied = true;
        }

        $this->assertTrue($denied, 'The testing DB user must not open production database sales.');
    }
}
