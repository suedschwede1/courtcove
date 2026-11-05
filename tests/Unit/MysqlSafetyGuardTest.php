<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MysqlTestCase;

/** Pure logic, never touches a database. */
class MysqlSafetyGuardTest extends TestCase
{
    #[Test]
    public function it_accepts_the_local_throwaway_database(): void
    {
        foreach (['127.0.0.1', 'localhost', '::1'] as $host) {
            MysqlTestCase::assertSafeDatabase('booking_test', $host, null);
            MysqlTestCase::assertSafeDatabase('booking_test', $host, '');
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_rejects_a_non_test_database_name(): void
    {
        $this->expectException(RuntimeException::class);
        MysqlTestCase::assertSafeDatabase('booking', '127.0.0.1', null);
    }

    #[Test]
    public function it_rejects_a_non_local_host(): void
    {
        $this->expectException(RuntimeException::class);
        MysqlTestCase::assertSafeDatabase('booking_test', 'db.example.net', null);
    }

    #[Test]
    public function it_rejects_a_database_url(): void
    {
        $this->expectException(RuntimeException::class);
        MysqlTestCase::assertSafeDatabase('booking_test', '127.0.0.1', 'mysql://u:p@elsewhere/booking_test');
    }
}
