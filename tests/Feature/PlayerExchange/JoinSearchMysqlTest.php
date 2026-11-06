<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;
use Tests\Support\SecondConnection;

/**
 * Two members joining the last free place at the same time, on a real MySQL server (SQLite ignores
 * FOR UPDATE). The second connection plays the other request: it holds the booking row lock with its
 * signup written but not committed, or commits its signup right before our join takes the lock.
 * In every interleaving exactly one signup exists afterwards.
 *
 * Rows must be committed for the second connection to see them, so there is no wrapping transaction:
 * the involved tables are emptied before every test.
 */
#[Group('mysql')]
class JoinSearchMysqlTest extends MysqlTestCase
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

    private User $first;

    private User $other;

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
        $this->booking = app(BookingService::class)->createSingle(
            $owner, Square::factory()->create(), 2, $start, $start->copy()->addHour(), [], 1,
        );
        $this->search = PlayerSearch::query()->where('bid', $this->booking->bid)->sole();

        $exchange = app(PlayerExchangeService::class);
        $this->first = User::factory()->create(['alias' => 'Max Muster']);
        $this->other = User::factory()->create(['alias' => 'Erika Beispiel']);
        $exchange->optIn($this->first);
        $exchange->optIn($this->other);
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

    /** The other request: holds the booking row and has written its signup for the last place, uncommitted. */
    private function secondHoldsJoin(User $user): void
    {
        $second = $this->second();
        $second->beginTransaction();
        $second->select(self::LOCK_SQL, [$this->booking->bid]);
        $second->insert(
            'INSERT INTO player_exchange_signups (search_id, uid, slot, created_at) VALUES (?, ?, ?, ?)',
            [$this->search->id, $user->uid, 2, '2026-11-06 08:00:00'],
        );
        $second->update('UPDATE player_exchange_searches SET status = ? WHERE id = ?', [PlayerSearch::FILLED, $this->search->id]);
    }

    #[Test]
    public function a_join_waits_for_a_parallel_join_holding_the_booking_row_and_then_finds_no_place(): void
    {
        $this->secondHoldsJoin($this->other);
        SecondConnection::waitAtMost(1);

        try {
            app(PlayerExchangeService::class)->join($this->first, $this->booking);
            $this->fail('expected a lock wait timeout while the other join holds the booking row');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1], $e->getMessage());
        }
        $this->assertSame(0, DB::transactionLevel(), 'the failed join rolled back');

        $this->second()->commit();

        try {
            app(PlayerExchangeService::class)->join($this->first, $this->booking);
            $this->fail('the last place is taken');
        } catch (PlayerExchangeException $e) {
            $this->assertSame(__('booking.messages.player_exchange.full'), $e->getMessage());
        }

        $this->assertSame([(int) $this->other->uid], PlayerSignup::query()->pluck('uid')->map(fn ($uid) => (int) $uid)->all());
    }

    #[Test]
    public function a_parallel_join_committed_right_before_the_lock_is_seen_under_the_lock(): void
    {
        $injected = false;
        // The other join commits after our request has loaded everything it needs, immediately
        // before our join takes the booking row lock: the checks must run on the fresh state.
        DB::connection()->beforeExecuting(function (string $sql) use (&$injected): void {
            if ($injected || ! str_contains($sql, 'from `bs_bookings`') || ! str_ends_with($sql, 'for update')) {
                return;
            }
            $injected = true;
            $this->secondHoldsJoin($this->other);
            $this->second()->commit();
        });

        try {
            app(PlayerExchangeService::class)->join($this->first, $this->booking);
            $this->fail('the last place was taken in parallel');
        } catch (PlayerExchangeException $e) {
            $this->assertSame(__('booking.messages.player_exchange.full'), $e->getMessage());
        }

        $this->assertTrue($injected, 'the parallel join ran');
        $this->assertSame(1, PlayerSignup::query()->count());
        $this->assertSame((int) $this->other->uid, (int) PlayerSignup::query()->value('uid'));
        $this->assertSame([], $this->booking->fresh(['meta'])->player_names, 'the refused join wrote no name');
    }

    #[Test]
    public function without_a_parallel_join_the_last_place_is_taken_and_the_search_filled(): void
    {
        $signup = app(PlayerExchangeService::class)->join($this->first, $this->booking);

        $this->assertSame(2, (int) $signup->slot);
        $this->assertSame(PlayerSearch::FILLED, $this->search->fresh()->status);
        $this->assertSame(['Max Muster'], $this->booking->fresh(['meta'])->player_names);
    }
}
