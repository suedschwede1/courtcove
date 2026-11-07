<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Mail\BookingCancelled;
use App\Mail\PlayerSearchBookingCancelled;
use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
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
 * The booker cancelling while the lapse of his player search runs, on a real MySQL server (SQLite
 * ignores FOR UPDATE). The second connection plays the lapse. The cancel decides on the locked
 * booking row, not on the model loaded before the lock: a booking the lapse already lifted is not
 * cancelled again and the booker gets no cancel confirmation on top of the "search expired" mail.
 *
 * Rows must be committed for the second connection to see them, so there is no wrapping transaction:
 * the involved tables are emptied before every test.
 */
#[Group('mysql')]
class OwnerCancelMysqlTest extends MysqlTestCase
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
        // The court allows cancelling up to 30 minutes before the start, so the booker may still
        // cancel at the lapse (one hour before the start).
        $square = Square::factory()->create(['range_cancel' => 1800]);
        $this->booking = app(BookingService::class)->createSingle(
            $owner, $square, 2, $start, $start->copy()->addHour(), [], 1,
        );
        $this->search = PlayerSearch::query()->where('bid', $this->booking->bid)->sole();

        // At the deadline: one hour before 10:00, the lapse is due.
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

    /** The lapse in the other request: booking row locked, search lapsed, booking cancelled, uncommitted. */
    private function secondHoldsLapse(): void
    {
        $second = $this->second();
        $second->beginTransaction();
        $second->select(self::LOCK_SQL, [$this->booking->bid]);
        $second->update('UPDATE player_exchange_searches SET status = ? WHERE id = ?', [PlayerSearch::LAPSED, $this->search->id]);
        $second->update("UPDATE bs_bookings SET status = 'cancelled', status_billing = 'cancelled' WHERE bid = ?", [$this->booking->bid]);
    }

    /** Make the lapse commit right before our cancel takes the booking row lock. */
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
    public function a_lapse_committed_right_before_the_owner_cancel_lock_sends_no_cancel_confirmation(): void
    {
        // The request already loaded the booking and passed the "may cancel" check on that copy.
        $this->beforeOurBookingLock(function (): void {
            $this->secondHoldsLapse();
            $this->second()->commit();
        });

        $this->actingAs($this->booking->user)
            ->delete(route('bookings.destroy', $this->booking))
            ->assertSessionHasErrors(['booking' => __('booking.messages.booking_already_cancelled')]);

        $this->assertSame('cancelled', $this->booking->fresh()->status);
        $this->assertSame(PlayerSearch::LAPSED, $this->search->fresh()->status, 'the lapse stays the reason');
        Mail::assertNotQueued(BookingCancelled::class);
        Mail::assertNotQueued(PlayerSearchBookingCancelled::class);
    }

    #[Test]
    public function the_owner_cancel_waits_for_a_running_lapse_and_then_changes_nothing(): void
    {
        $stale = $this->booking->fresh();
        $this->secondHoldsLapse();
        SecondConnection::waitAtMost(1);

        try {
            app(BookingService::class)->cancelSingle($stale);
            $this->fail('expected a lock wait timeout while the lapse holds the booking row');
        } catch (QueryException $e) {
            $this->assertStringContainsString('1205', $e->getMessage());
        }
        $this->assertSame(0, DB::transactionLevel(), 'the blocked cancel rolled back');

        $this->second()->commit();

        $this->assertFalse(app(BookingService::class)->cancelSingle($stale));
        $this->assertSame('cancelled', $this->booking->fresh()->status);
        $this->assertSame(PlayerSearch::LAPSED, $this->search->fresh()->status);
        $this->app->terminate();
        Mail::assertNotQueued(BookingCancelled::class);
        Mail::assertNotQueued(PlayerSearchBookingCancelled::class);
    }

    #[Test]
    public function without_competition_the_owner_cancel_cancels_once_and_mails_once(): void
    {
        $stale = $this->booking->fresh();

        $this->assertTrue(app(BookingService::class)->cancelSingle($stale));
        $this->assertFalse(app(BookingService::class)->cancelSingle($stale));
        $this->app->terminate();

        $this->assertSame('cancelled', $this->booking->fresh()->status);
        $this->assertSame(PlayerSearch::CANCELLED, $this->search->fresh()->status);
        Mail::assertQueued(BookingCancelled::class, 1);
    }
}
