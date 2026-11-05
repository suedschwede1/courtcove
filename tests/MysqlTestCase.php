<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Base for tests that need a real MySQL server (charset, collation, ENUM and
 * index behavior that SQLite cannot reproduce). Run with: composer test:mysql
 *
 * These tests drop and rebuild tables, so they refuse to run against anything
 * but the throwaway local database.
 */
abstract class MysqlTestCase extends TestCase
{
    public const SAFE_DATABASE = 'booking_test';

    private const SAFE_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /**
     * @throws RuntimeException when the target is not the local throwaway database
     */
    public static function assertSafeDatabase(string $name, string $host, ?string $url): void
    {
        if ($url !== null && $url !== '') {
            throw new RuntimeException('Refusing to run MySQL tests: DB_URL is set.');
        }

        if ($name !== self::SAFE_DATABASE) {
            throw new RuntimeException('Refusing to run MySQL tests: database must be '.self::SAFE_DATABASE.", got '{$name}'.");
        }

        if (! in_array($host, self::SAFE_HOSTS, true)) {
            throw new RuntimeException("Refusing to run MySQL tests: host must be local, got '{$host}'.");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('needs MySQL: composer test:mysql');
        }

        try {
            self::assertSafeDatabase(
                (string) DB::connection()->getDatabaseName(),
                (string) config('database.connections.mysql.host'),
                config('database.connections.mysql.url'),
            );
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
    }
}
