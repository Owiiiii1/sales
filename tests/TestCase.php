<?php

namespace Tests;

use App\Support\TestingDatabaseGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        TestingDatabaseGuard::enforceFromEnvironment();

        parent::setUp();

        TestingDatabaseGuard::enforceFromApplication($this->app);
        $this->withoutVite();
    }
}
