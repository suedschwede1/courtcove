<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * Base for tests that need a real MySQL server (charset, collation, ENUM and
 * index behavior that SQLite cannot reproduce). Run with: composer test:mysql
 */
abstract class MysqlTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('needs MySQL: composer test:mysql');
        }
    }
}
