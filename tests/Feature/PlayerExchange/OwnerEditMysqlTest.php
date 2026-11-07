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
 * The booker changes a Doppel with a player search to Einzel while a member joins, on a real MySQL
 * server (SQLite ignores FOR UPDATE). The owner edit takes the same booking row lock as join, leave
 * and the lapse, so either the join wins and the reduction is refused, or the reduction wins and the
 * join is refused. A joined member never ends up outside the capacity of the booking.
 *
 * Booking: Doppel (4), typed partner in slot 2, two searched places. The edit keeps the partner and
 * asks for Einzel: that fits only while nobody has joined.
 */
#[Group('mysql')]
class OwnerEditMysqlTest extends MysqlTestCase
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

    private User $owner;

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
        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));
        $start = Carbon::parse('2026-11-08 10:00:00');

        $service = app(BookingService::class);
        $this->owner = User::factory()->create(['alias' => 'Olga Owner']);
        $this->booking = $service->createSingle(
            $this->owner, Square::factory()->create(['capacity' => 4]), 4, $start, $start->copy()->addHour(),
            $service->buildPlayerMeta([2 => 'Paula Partner']), 2,
        );
        $this->search = PlayerSearch::query()->where('bid', $this->booking->bid)->sole();

        $this->member = User::factory()->create(['alias' => 'Max Muster']);
        app(PlayerExchangeService::class)->optIn($this->member);
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

    private function reduceToSingle(): void
    {
        app(BookingService::class)->updateSinglePlayers($this->booking, $this->owner, 2, [2 => 'Paula Partner']);
    }

    /** The parallel join: holds the booking row, writes the signup for slot 3 and the name, uncommitted. */
    private function secondHoldsJoin(): void
    {
        $second = $this->second();
        $second->beginTransaction();
        $second->select(self::LOCK_SQL, [$this->booking->bid]);
        $second->insert(
            'INSERT INTO player_exchange_signups (search_id, uid, slot, created_at) VALUES (?, ?, ?, ?)',
            [$this->search->id, $this->member->uid, 3, '2026-11-06 08:00:00'],
        );
        $names = app(BookingService::class)->buildPlayerMeta([2 => 'Paula Partner', 3 => 'Max Muster']);
        $second->update(
            'UPDATE bs_bookings_meta SET value = ? WHERE bid = ? AND `key` = ?',
            [$names[0]['value'], $this->booking->bid, 'player-names'],
        );
    }

    private function assertRefusedTooMany(): void
    {
        try {
            $this->reduceToSingle();
            $this->fail('a joined member would lose his place');
        } catch (BookingValidationException $e) {
            $this->assertSame(__('booking.messages.player_exchange.edit_too_many'), $e->getMessage());
        }
    }

    private function assertJoinWonAndBookingUnchanged(): void
    {
        $fresh = $this->booking->fresh(['meta']);
        $this->assertSame(4, (int) $fresh->quantity);
        $this->assertSame(['Paula Partner', 'Max Muster'], $fresh->player_names);
        $this->assertSame([3], PlayerSignup::query()->pluck('slot')->map(fn ($s) => (int) $s)->all());
    }

    #[Test]
    public function a_join_committed_right_before_the_edit_lock_is_seen_and_the_reduction_refused(): void
    {
        $injected = false;
        // The join commits after our request has loaded the booking, immediately before the
        // owner edit takes the booking row lock: the capacity check must see the new signup.
        DB::connection()->beforeExecuting(function (string $sql) use (&$injected): void {
            if ($injected || ! str_contains($sql, 'from `bs_bookings`') || ! str_ends_with($sql, 'for update')) {
                return;
            }
            $injected = true;
            $this->secondHoldsJoin();
            $this->second()->commit();
        });

        $this->assertRefusedTooMany();

        $this->assertTrue($injected, 'the parallel join ran');
        $this->assertJoinWonAndBookingUnchanged();
    }

    #[Test]
    public function the_edit_waits_for_a_join_holding_the_booking_row_and_then_refuses(): void
    {
        $this->secondHoldsJoin();
        SecondConnection::waitAtMost(1);

        try {
            $this->reduceToSingle();
            $this->fail('expected a lock wait timeout while the join holds the booking row');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1], $e->getMessage());
        }
        $this->assertSame(0, DB::transactionLevel(), 'the failed edit rolled back');
        $this->assertSame(4, (int) $this->booking->fresh()->quantity);

        $this->second()->commit();

        $this->assertRefusedTooMany();
        $this->assertJoinWonAndBookingUnchanged();
    }

    #[Test]
    public function a_join_waits_for_the_edit_holding_the_booking_row_and_is_refused_after_it(): void
    {
        // The owner edit wins the lock: Einzel with the typed partner, so no searched place is left.
        $second = $this->second();
        $second->beginTransaction();
        $second->select(self::LOCK_SQL, [$this->booking->bid]);
        $second->update('UPDATE bs_bookings SET quantity = 2 WHERE bid = ?', [$this->booking->bid]);
        $second->update('UPDATE player_exchange_searches SET status = ? WHERE id = ?', [PlayerSearch::ENDED, $this->search->id]);
        SecondConnection::waitAtMost(1);

        try {
            app(PlayerExchangeService::class)->join($this->member, $this->booking);
            $this->fail('expected a lock wait timeout while the edit holds the booking row');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1], $e->getMessage());
        }
        $this->assertSame(0, PlayerSignup::query()->count());

        $second->commit();

        try {
            app(PlayerExchangeService::class)->join($this->member, $this->booking);
            $this->fail('the reduction ended the search');
        } catch (PlayerExchangeException $e) {
            $this->assertSame(__('booking.messages.player_exchange.not_open'), $e->getMessage());
        }
        $this->assertSame(0, PlayerSignup::query()->count());
    }

    #[Test]
    public function without_a_join_the_reduction_goes_through_and_a_later_join_is_refused(): void
    {
        $this->reduceToSingle();

        $fresh = $this->booking->fresh(['meta']);
        $this->assertSame(2, (int) $fresh->quantity);
        $this->assertSame(['Paula Partner'], $fresh->player_names);
        $this->assertSame(PlayerSearch::ENDED, $this->search->fresh()->status, 'no place left to search');

        try {
            app(PlayerExchangeService::class)->join($this->member, $this->booking);
            $this->fail('no place left');
        } catch (PlayerExchangeException $e) {
            $this->assertSame(__('booking.messages.player_exchange.not_open'), $e->getMessage());
        }
        $this->assertSame(0, PlayerSignup::query()->count());
    }

    #[Test]
    public function the_edit_takes_the_booking_row_lock_before_any_plain_read(): void
    {
        /** @var list<string> $log */
        $log = [];
        DB::listen(function (QueryExecuted $q) use (&$log): void {
            if ($q->connectionName !== SecondConnection::NAME && $q->connection->transactionLevel() > 0) {
                $log[] = $q->sql;
            }
        });

        app(BookingService::class)->updateSinglePlayers($this->booking, $this->owner, 4, [2 => 'Paula Partner']);

        $this->assertMatchesRegularExpression('/^select \* from `bs_bookings` where `bs_bookings`\.`bid` = \? limit 1 for update$/', $log[0] ?? '');
        $locking = array_values(array_filter($log, fn (string $sql): bool => str_ends_with($sql, 'for update')));
        $this->assertCount(1, $locking, 'no further locking reads: '.implode(' | ', $locking));
    }
}
