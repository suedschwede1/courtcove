<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationUsageMonthly;
use App\Models\Option;
use App\Services\Notifications\BudgetService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/** Row lock of the monthly usage row on real MySQL: parallel reservations cannot overshoot the budget. */
#[Group('mysql')]
class BudgetServiceMysqlTest extends MysqlTestCase
{
    private const PRICE = 50_000;

    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Europe/Vienna'));
        DB::table('notification_usage_monthly')->delete();
        Option::where('key', 'like', 'whatsapp.%')->delete();
        Option::create(['key' => 'whatsapp.budget_monthly', 'value' => '0.10', 'locale' => null]);
        Option::create(['key' => 'whatsapp.price_estimate', 'value' => '0.05', 'locale' => null]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Option::where('key', 'like', 'whatsapp.%')->delete();
        parent::tearDown();
    }

    #[Test]
    public function parallel_reserve_cannot_overshoot(): void
    {
        $service = app(BudgetService::class);
        $this->assertNotNull($service->reserve(self::PRICE)); // 1 of 2

        $config = config('database.connections.mysql');
        $other = new \PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
            $config['username'],
            $config['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
        $other->beginTransaction();
        $locked = $other->query("SELECT estimated_micros FROM notification_usage_monthly WHERE month = '2026-11' FOR UPDATE")
            ->fetchColumn();
        $this->assertSame(self::PRICE, (int) $locked);

        // The second connection holds the row lock: the reservation must wait and fail fast, not slip through.
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $service->reserve(self::PRICE);
            $this->fail('The reservation must not pass while the row is locked.');
        } catch (QueryException) {
            // expected: lock wait timeout
        }

        $other->exec('UPDATE notification_usage_monthly SET estimated_micros = estimated_micros + '.self::PRICE.", messages_reserved = messages_reserved + 1 WHERE month = '2026-11'");
        $other->commit();

        // The slot was taken by the other connection: the budget is exhausted now.
        $this->assertNull($service->reserve(self::PRICE));
        $this->assertSame(2 * self::PRICE, (int) NotificationUsageMonthly::firstOrFail()->estimated_micros);
    }
}
