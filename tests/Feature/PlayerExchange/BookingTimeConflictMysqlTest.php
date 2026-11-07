<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Exceptions\BookingValidationException;
use App\Exceptions\PlayerExchangeException;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;
use Tests\Support\SecondConnection;

/**
 * A member books a court for himself while he joins a player search at an overlapping time, in
 * parallel, on a real MySQL server (SQLite ignores FOR UPDATE). Both paths lock the member's
 * user row before they read (join: booking row, then user row; own booking: court row, then user
 * row), so one waits for the other and then sees it: the member never gets both.
 *
 * Rows must be committed for the second connection to see them, so there is no wrapping
 * transaction: the involved tables are emptied before every test.
 */
#[Group('mysql')]
class BookingTimeConflictMysqlTest extends MysqlTestCase
{
    private static bool $migrated = false;

    private const TABLES = [
        'player_exchange_signups', 'player_exchange_searches', 'bs_bookings_meta', 'bs_reservations',
        'bs_bookings', 'bs_users_meta', 'bs_users', 'bs_squares_meta', 'bs_squares',
    ];

    private bool $ready = false;

    private ?Connection $second = null;

    private Booking $searchBooking;

    private PlayerSearch $search;

    private User $max;

    private Square $court;

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
        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));
        $start = Carbon::parse('2026-11-08 10:00:00');

        $owner = User::factory()->create(['alias' => 'Olga Owner']);
        $this->searchBooking = app(BookingService::class)->createSingle(
            $owner, Square::factory()->create(), 2, $start, $start->copy()->addHour(), [], 1,
        );
        $this->search = PlayerSearch::query()->where('bid', $this->searchBooking->bid)->sole();

        $this->max = User::factory()->create(['alias' => 'Max Muster']);
        app(PlayerExchangeService::class)->optIn($this->max);
        $this->court = Square::factory()->create();
    }

    protected function tearDown(): void
    {
        if ($this->ready) {
            SecondConnection::release();
        }

        parent::tearDown();
    }

    private function second(): Connection
    {
        return $this->second ??= SecondConnection::make();
    }

    /** Max books the other court at the time of the search booking. */
    private function maxBooks(): Booking
    {
        return app(BookingService::class)->createSingle(
            $this->max, $this->court, 2, Carbon::parse('2026-11-08 10:00:00'), Carbon::parse('2026-11-08 11:00:00'), [], 0,
        );
    }

    /** The join request of Max on the second connection: booking row, user row, signup, uncommitted. */
    private function secondHoldsJoin(): void
    {
        $second = $this->second();
        $second->beginTransaction();
        $second->select('SELECT bid FROM bs_bookings WHERE bid = ? FOR UPDATE', [$this->searchBooking->bid]);
        $second->select('SELECT uid FROM bs_users WHERE uid = ? FOR UPDATE', [$this->max->uid]);
        $second->insert(
            'INSERT INTO player_exchange_signups (search_id, uid, slot, created_at) VALUES (?, ?, ?, ?)',
            [$this->search->id, $this->max->uid, 2, '2026-11-06 08:00:00'],
        );
        $second->update('UPDATE player_exchange_searches SET status = ? WHERE id = ?', [PlayerSearch::FILLED, $this->search->id]);
    }

    /** The own booking request of Max on the second connection: court row, user row, booking, uncommitted. */
    private function secondHoldsOwnBooking(): void
    {
        $second = $this->second();
        $second->beginTransaction();
        $second->select('SELECT sid FROM bs_squares WHERE sid = ? FOR UPDATE', [$this->court->sid]);
        $second->select('SELECT uid FROM bs_users WHERE uid = ? FOR UPDATE', [$this->max->uid]);
        $second->insert(
            "INSERT INTO bs_bookings (uid, sid, status, status_billing, visibility, quantity, created) VALUES (?, ?, 'single', 'pending', 'public', 2, ?)",
            [$this->max->uid, $this->court->sid, '2026-11-06 08:00:00'],
        );
        $bid = (int) $second->getPdo()->lastInsertId();
        $second->insert(
            'INSERT INTO bs_reservations (bid, date, time_start, time_end) VALUES (?, ?, ?, ?)',
            [$bid, '2026-11-08', '10:00:00', '11:00:00'],
        );
    }

    private function maxBookingCount(): int
    {
        return Booking::query()->where('uid', $this->max->uid)->count();
    }

    #[Test]
    public function a_booking_waits_for_a_parallel_join_of_the_member_and_is_then_refused(): void
    {
        $this->secondHoldsJoin();
        SecondConnection::waitAtMost(1);

        try {
            $this->maxBooks();
            $this->fail('expected a lock wait timeout while the join holds the member row');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1], $e->getMessage());
        }
        $this->assertSame(0, DB::transactionLevel(), 'the failed booking rolled back');

        $this->second()->commit();

        try {
            $this->maxBooks();
            $this->fail('the member plays at that time');
        } catch (BookingValidationException $e) {
            $this->assertSame(__('booking.messages.player_exchange.booking_busy'), $e->getMessage());
        }

        $this->assertSame(0, $this->maxBookingCount());
        $this->assertSame(1, PlayerSignup::query()->count());
    }

    #[Test]
    public function a_join_committed_while_the_booking_waits_for_the_member_row_is_seen(): void
    {
        $injected = false;
        // The join commits after the booking took the court row and right before it takes the
        // member row: no plain read may have run before, so the check sees the signup.
        DB::connection()->beforeExecuting(function (string $sql) use (&$injected): void {
            if ($injected || ! str_contains($sql, 'from `bs_users`') || ! str_ends_with($sql, 'for update')) {
                return;
            }
            $injected = true;
            $this->secondHoldsJoin();
            $this->second()->commit();
        });

        try {
            $this->maxBooks();
            $this->fail('the member joined a search at that time in parallel');
        } catch (BookingValidationException $e) {
            $this->assertSame(__('booking.messages.player_exchange.booking_busy'), $e->getMessage());
        }

        $this->assertTrue($injected, 'the booking locked the member row and the parallel join ran');
        $this->assertSame(0, $this->maxBookingCount());
        $this->assertSame(1, PlayerSignup::query()->count());
    }

    #[Test]
    public function a_join_waits_for_a_parallel_own_booking_and_is_then_refused(): void
    {
        $this->secondHoldsOwnBooking();
        SecondConnection::waitAtMost(1);

        try {
            app(PlayerExchangeService::class)->join($this->max, $this->searchBooking);
            $this->fail('expected a lock wait timeout while the own booking holds the member row');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1], $e->getMessage());
        }

        $this->second()->commit();

        try {
            app(PlayerExchangeService::class)->join($this->max, $this->searchBooking);
            $this->fail('the member has an own booking at that time');
        } catch (PlayerExchangeException $e) {
            $this->assertSame(__('booking.messages.player_exchange.busy'), $e->getMessage());
        }

        $this->assertSame(1, $this->maxBookingCount());
        $this->assertSame(0, PlayerSignup::query()->count());
        $this->assertSame(PlayerSearch::OPEN, $this->search->fresh()->status);
    }

    #[Test]
    public function the_booking_takes_the_court_and_then_the_member_row_lock_before_any_plain_read(): void
    {
        /** @var list<string> $log */
        $log = [];
        DB::listen(function (QueryExecuted $q) use (&$log): void {
            if ($q->connectionName !== SecondConnection::NAME && $q->connection->transactionLevel() > 0) {
                $log[] = $q->sql;
            }
        });

        $this->maxBooks();

        $this->assertMatchesRegularExpression('/^select \* from `bs_squares` where `bs_squares`\.`sid` = \? for update$/', $log[0] ?? '');
        $this->assertMatchesRegularExpression('/^select \* from `bs_users` where `bs_users`\.`uid` = \? for update$/', $log[1] ?? '');
        $this->assertSame(1, $this->maxBookingCount());
    }
}
