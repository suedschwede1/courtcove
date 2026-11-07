<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Mail\PlayerSearchExpired;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;
use Tests\Support\SecondConnection;

/**
 * The lapse racing a join (or a second lapse) on a real MySQL server (SQLite ignores FOR UPDATE).
 * The second connection plays the other request. A booking that got a player in the meantime is
 * never cancelled, and the booker gets at most one "search expired" mail.
 *
 * Rows must be committed for the second connection to see them, so there is no wrapping transaction:
 * the involved tables are emptied before every test.
 */
#[Group('mysql')]
class ExpireSearchMysqlTest extends MysqlTestCase
{
    private static bool $migrated = false;

    private const TABLES = [
        'player_exchange_signups', 'player_exchange_searches', 'bs_bookings_meta', 'bs_reservations',
        'bs_bookings', 'bs_users_meta', 'bs_users', 'bs_squares_meta', 'bs_squares',
    ];

    private const LOCK_SQL = 'SELECT bid FROM bs_bookings WHERE bid = ? FOR UPDATE';

    private bool $ready = false;

    private ?Connection $second = null;

    private Booking $booking;

    private PlayerSearch $search;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->ready = true;

        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com']);
        Carbon::setTestNow(Carbon::parse('2026-11-06 08:00:00'));
        $start = Carbon::parse('2026-11-08 10:00:00');

        $owner = User::factory()->create(['alias' => 'Olga Owner', 'email' => 'owner@example.com']);
        $this->booking = app(BookingService::class)->createSingle(
            $owner, Square::factory()->create(), 2, $start, $start->copy()->addHour(), [], 1,
        );
        $this->search = PlayerSearch::query()->where('bid', $this->booking->bid)->sole();

        $this->member = User::factory()->create(['alias' => 'Max Muster']);
        app(PlayerExchangeService::class)->optIn($this->member);

        // At the deadline: one hour before 10:00.
        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00'));
    }

    protected function tearDown(): void
    {
        if ($this->ready) {
            SecondConnection::release();
        }
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function second(): Connection
    {
        return $this->second ??= SecondConnection::make();
    }

    /** The other request: a join (made just before the deadline) holding the booking row, uncommitted. */
    private function secondHoldsJoin(): void
    {
        $second = $this->second();
        $second->beginTransaction();
        $second->select(self::LOCK_SQL, [$this->booking->bid]);
        $second->insert(
            'INSERT INTO player_exchange_signups (search_id, uid, slot, created_at) VALUES (?, ?, ?, ?)',
            [$this->search->id, $this->member->uid, 2, '2026-11-08 08:59:59'],
        );
        $second->update('UPDATE player_exchange_searches SET status = ? WHERE id = ?', [PlayerSearch::FILLED, $this->search->id]);
    }

    /** Make the other request commit right before our lapse takes the booking row lock. */
    private function beforeOurBookingLock(callable $other): void
    {
        $injected = false;
        DB::connection()->beforeExecuting(function (string $sql) use (&$injected, $other): void {
            if ($injected || ! str_contains($sql, 'from `bs_bookings`') || ! str_ends_with($sql, 'for update')) {
                return;
            }
            $injected = true;
            $other();
        });
    }

    #[Test]
    public function a_join_committed_right_before_the_lapse_lock_keeps_the_booking(): void
    {
        // Our lapse already listed the search as open without players; the join commits before our lock.
        $this->beforeOurBookingLock(function (): void {
            $this->secondHoldsJoin();
            $this->second()->commit();
        });

        $this->assertSame(0, app(PlayerExchangeService::class)->lapseExpired());

        $this->assertSame('single', $this->booking->fresh()->status, 'a booking with a player is never cancelled');
        $this->assertSame(PlayerSearch::FILLED, $this->search->fresh()->status, 'filled is not open: left alone');
        $this->assertSame(1, PlayerSignup::query()->count());
        Mail::assertNotQueued(PlayerSearchExpired::class);
    }

    #[Test]
    public function the_lapse_waits_for_a_join_holding_the_booking_row_and_then_keeps_the_booking(): void
    {
        $this->secondHoldsJoin();
        SecondConnection::waitAtMost(1);

        // Lock wait timeouts are retried and then reported; nothing is cancelled meanwhile.
        $this->assertSame(0, app(PlayerExchangeService::class)->lapseExpired());
        $this->assertSame(0, DB::transactionLevel(), 'the failed lapse rolled back');
        $this->assertSame('single', $this->booking->fresh()->status);

        $this->second()->commit();

        $this->assertSame(0, app(PlayerExchangeService::class)->lapseExpired());
        $this->assertSame('single', $this->booking->fresh()->status);
        $this->assertSame(PlayerSearch::FILLED, $this->search->fresh()->status);
        Mail::assertNotQueued(PlayerSearchExpired::class);
    }

    #[Test]
    public function a_parallel_lapse_committed_right_before_ours_mails_only_once(): void
    {
        $this->beforeOurBookingLock(function (): void {
            // The other run (scheduler, or a calendar load) lapses the same search first.
            $second = $this->second();
            $second->beginTransaction();
            $second->select(self::LOCK_SQL, [$this->booking->bid]);
            $second->update('UPDATE player_exchange_searches SET status = ? WHERE id = ?', [PlayerSearch::LAPSED, $this->search->id]);
            $second->update("UPDATE bs_bookings SET status = 'cancelled', status_billing = 'cancelled' WHERE bid = ?", [$this->booking->bid]);
            $second->commit();
        });

        $this->assertSame(0, app(PlayerExchangeService::class)->lapseExpired());

        $this->assertSame('cancelled', $this->booking->fresh()->status);
        $this->assertSame(PlayerSearch::LAPSED, $this->search->fresh()->status);
        Mail::assertNotQueued(PlayerSearchExpired::class);
    }

    #[Test]
    public function without_competition_the_search_lapses_once_and_the_booker_is_mailed_once(): void
    {
        $this->assertSame(1, app(PlayerExchangeService::class)->lapseExpired());
        $this->assertSame(0, app(PlayerExchangeService::class)->lapseExpired());

        $this->assertSame('cancelled', $this->booking->fresh()->status);
        $this->assertSame(PlayerSearch::LAPSED, $this->search->fresh()->status);
        Mail::assertQueued(PlayerSearchExpired::class, 1);
        Mail::assertQueued(PlayerSearchExpired::class, fn (PlayerSearchExpired $m): bool => $m->hasTo('owner@example.com'));
    }

    /** Count our booking row lock statements (each one is an attempt that waits for the lock). */
    private function countBookingLocks(): \stdClass
    {
        $counter = new \stdClass;
        $counter->locks = 0;
        DB::connection()->beforeExecuting(function (string $sql) use ($counter): void {
            if (str_contains($sql, 'from `bs_bookings`') && str_ends_with($sql, 'for update')) {
                $counter->locks++;
            }
        });

        return $counter;
    }

    #[Test]
    public function lazy_lapses_in_web_requests_wait_for_a_held_booking_lock_only_once(): void
    {
        $this->secondHoldsJoin();
        SecondConnection::waitAtMost(1);
        $counter = $this->countBookingLocks();

        // Calendar load: one lock wait (about one innodb_lock_wait_timeout), then the page renders.
        Cache::forget('player-exchange.lapse');
        $this->actingAs($this->member)->get('/calendar?date=2026-11-08')->assertOk();
        $this->assertSame(1, $counter->locks, 'calendar lapse: a single attempt');

        // Join: one lock wait for the lazy lapse, then the join itself retries (3 attempts) and answers "try again".
        $counter->locks = 0;
        $this->actingAs($this->member)->postJson(route('player-exchange.join', $this->booking))->assertStatus(422);
        $this->assertSame(1 + 3, $counter->locks, 'join pre-lapse: a single attempt');

        // The scheduler command keeps its retries.
        $counter->locks = 0;
        $this->artisan('player-exchange:expire')->assertSuccessful();
        $this->assertSame(3, $counter->locks, 'command: three attempts');

        $this->assertSame('single', $this->booking->fresh()->status);
        Mail::assertNotQueued(PlayerSearchExpired::class);
    }
}
