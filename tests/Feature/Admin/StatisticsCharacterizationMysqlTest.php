<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\MysqlTestCase;

/**
 * The statistics characterization on a real MySQL in strict mode
 * (ONLY_FULL_GROUP_BY, DATE column compared with Y-m-d bounds, SUM returning
 * DECIMAL). Runs with: composer test:mysql. Skipped on SQLite.
 */
#[Group('mysql')]
class StatisticsCharacterizationMysqlTest extends StatisticsCharacterizationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('needs MySQL: composer test:mysql');
        }

        try {
            MysqlTestCase::assertSafeDatabase(
                (string) DB::connection()->getDatabaseName(),
                (string) config('database.connections.mysql.host'),
                config('database.connections.mysql.url'),
            );
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertStringContainsString('ONLY_FULL_GROUP_BY', (string) DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode);
    }
}
