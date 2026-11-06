<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\MysqlTestCase;

/**
 * A second, independent MySQL session on the same local booking_test database, so a test can play two
 * concurrent requests in one PHP process: one session holds a row lock (or an uncommitted write) while
 * the other one runs into it. Rows must be committed for the other session to see them.
 *
 * The lock wait timeout of the second session is 1 second, so a blocked statement fails fast with
 * error 1205 instead of hanging the suite; a test that lets the default connection wait lowers its
 * timeout with waitAtMost().
 */
final class SecondConnection
{
    public const NAME = 'mysql_second';

    /** @throws RuntimeException when the default connection is not the local throwaway MySQL database */
    public static function make(): Connection
    {
        $config = config('database.connections.mysql');

        if (DB::connection()->getDriverName() !== 'mysql' || ! is_array($config)) {
            throw new RuntimeException('SecondConnection needs the mysql default connection.');
        }

        MysqlTestCase::assertSafeDatabase((string) ($config['database'] ?? ''), (string) ($config['host'] ?? ''), $config['url'] ?? null);

        config(['database.connections.'.self::NAME => $config]);
        DB::purge(self::NAME);

        $connection = DB::connection(self::NAME);
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');

        return $connection;
    }

    /** Lowers the lock wait timeout of the default connection (the "other request" of a test). */
    public static function waitAtMost(int $seconds): void
    {
        DB::statement('SET SESSION innodb_lock_wait_timeout = '.max(1, $seconds));
    }

    /** Rolls back whatever either session still holds, closes the second one and resets the default timeout. */
    public static function release(): void
    {
        if (array_key_exists(self::NAME, DB::getConnections())) {
            $second = DB::connection(self::NAME);

            while ($second->transactionLevel() > 0) {
                $second->rollBack();
            }

            DB::disconnect(self::NAME);
        }

        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
    }
}
