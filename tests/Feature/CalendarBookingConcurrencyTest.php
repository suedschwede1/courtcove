<?php

use App\Exceptions\BookingValidationException;
use App\Exceptions\EventBookingConflictException;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Option;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\EventService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('mysql')]
class CalendarBookingConcurrencyTest extends TestCase
{
    private static bool $migrated = false;

    private $second;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Isolated MySQL only.');
        }
        $this->assertStringStartsWith('bookingnew_review_', DB::connection()->getDatabaseName());
        if (! self::$migrated) {
            $this->artisan('migrate:fresh')->assertSuccessful();
            self::$migrated = true;
        }
        $this->travelTo('2026-11-09 10:00:00');
        config(['mail.default' => 'array', 'queue.default' => 'sync']);
        config(['database.connections.review_second' => config('database.connections.mysql')]);
        $this->second = DB::connection('review_second');
        DB::statement('SET SESSION innodb_lock_wait_timeout=1');
    }

    protected function tearDown(): void
    {
        if ($this->second) {
            while ($this->second->transactionLevel() > 0) {
                $this->second->rollBack();
            }DB::disconnect('review_second');
        }
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function insertOnSecond(User $owner, Square $court): void
    {
        $bid = $this->second->table('bs_bookings')->insertGetId(['uid' => $owner->uid, 'sid' => $court->sid, 'status' => 'single', 'status_billing' => 'pending', 'visibility' => 'public', 'quantity' => 2, 'created' => '2026-11-09 10:00:00']);
        $this->second->table('bs_reservations')->insert(['bid' => $bid, 'date' => '2026-11-10', 'time_start' => '10:00:00', 'time_end' => '11:00:00']);
    }

    private function create(User $user, Square $court): Booking
    {
        return app(BookingService::class)->createSingle($user, $court, 2, Carbon::parse('2026-11-10 10:00'), Carbon::parse('2026-11-10 11:00'));
    }

    #[Test]
    public function a_competing_booking_committed_before_the_court_mutex_is_seen_and_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $court = Square::factory()->create();
        $injected = false;
        DB::connection()->beforeExecuting(function ($sql) use (&$injected, $other, $court): void {
            if ($injected || ! str_contains($sql, 'from `bs_squares`') || ! str_ends_with($sql, 'for update')) {
                return;
            }
            $injected = true;
            $this->second->beginTransaction();
            $this->second->select('SELECT sid FROM bs_squares WHERE sid=? FOR UPDATE', [$court->sid]);
            $this->insertOnSecond($other, $court);
            $this->second->commit();
        });
        try {
            $this->create($user, $court);
            $this->fail('Overlapping booking unexpectedly accepted.');
        } catch (BookingValidationException $e) {
            $this->assertSame(__('booking.messages.slot_occupied'), $e->getMessage());
        }
        $this->assertTrue($injected);
        $this->assertSame(1, Booking::where('sid', $court->sid)->count());
    }

    #[Test]
    public function the_court_mutex_blocks_a_second_writer_while_the_first_is_uncommitted(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $court = Square::factory()->create();
        $this->second->beginTransaction();
        $this->second->select('SELECT sid FROM bs_squares WHERE sid=? FOR UPDATE', [$court->sid]);
        $this->insertOnSecond($other, $court);
        try {
            $this->create($user, $court);
            $this->fail('Expected lock contention.');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1]);
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->second->commit();
        try {
            $this->create($user, $court);
            $this->fail('Committed competing booking not seen.');
        } catch (BookingValidationException $e) {
            $this->assertSame(__('booking.messages.slot_occupied'), $e->getMessage());
        }
        $this->assertSame(1, Booking::where('sid', $court->sid)->count());
    }

    #[Test]
    public function a_parallel_booking_on_a_different_court_still_respects_the_global_user_limit(): void
    {
        $user = User::factory()->create();
        $court = Square::factory()->create();
        $otherCourt = Square::factory()->create();
        $injected = false;
        Option::updateOrCreate(['key' => 'booking.max_active_bookings', 'locale' => null], ['value' => '1']);
        DB::connection()->beforeExecuting(function ($sql) use (&$injected, $user, $otherCourt): void {
            if ($injected || ! str_contains($sql, 'from `bs_users`') || ! str_ends_with($sql, 'for update')) {
                return;
            }
            $injected = true;
            $this->second->beginTransaction();
            $this->second->select('SELECT uid FROM bs_users WHERE uid=? FOR UPDATE', [$user->uid]);
            $this->insertOnSecond($user, $otherCourt);
            $this->second->commit();
        });
        try {
            $this->create($user, $court);
            $this->fail('Global limit bypassed.');
        } catch (BookingValidationException $e) {
            $this->assertSame(__('booking.messages.max_active_bookings_reached'), $e->getMessage());
        }
        $this->assertTrue($injected);
        $this->assertSame(1, Booking::where('uid', $user->uid)->count());
    }

    #[Test]
    public function an_event_writer_sees_a_booking_committed_before_its_court_lock(): void
    {
        $user = User::factory()->create();
        $court = Square::factory()->create();
        $injected = false;
        DB::connection()->beforeExecuting(function ($sql) use (&$injected, $user, $court): void {
            if ($injected || ! str_contains($sql, 'from `bs_squares`') || ! str_ends_with($sql, 'for update')) {
                return;
            }
            $injected = true;
            $this->second->beginTransaction();
            $this->second->select('SELECT sid FROM bs_squares WHERE sid=? FOR UPDATE', [$court->sid]);
            $this->insertOnSecond($user, $court);
            $this->second->commit();
        });
        try {
            app(EventService::class)->create(['sid' => $court->sid, 'date_start' => '2026-11-10', 'date_end' => '2026-11-10', 'time_start' => '10:00', 'time_end' => '11:00', 'name' => 'Rejected closure']);
            $this->fail('Conflicting event accepted.');
        } catch (EventBookingConflictException $e) {
            $this->assertCount(1, $e->conflicts);
        }
        $this->assertTrue($injected);
        $this->assertSame(0, Event::where('sid', $court->sid)->count());
    }

    #[Test]
    public function an_edit_does_not_use_a_snapshot_from_before_a_competing_event_committed(): void
    {
        $user = User::factory()->create();
        $court = Square::factory()->create();
        $booking = $this->create($user, $court);
        $injected = false;
        DB::connection()->beforeExecuting(function ($sql) use (&$injected, $court): void {
            if ($injected || ! str_contains($sql, 'from `bs_squares`') || ! str_ends_with($sql, 'for update')) {
                return;
            }
            $injected = true;
            $this->second->beginTransaction();
            $this->second->select('SELECT sid FROM bs_squares WHERE sid=? FOR UPDATE', [$court->sid]);
            $this->second->table('bs_events')->insert(['sid' => $court->sid, 'status' => 'enabled', 'datetime_start' => '2026-11-10 12:00:00', 'datetime_end' => '2026-11-10 13:00:00', 'capacity' => 0]);
            $this->second->commit();
        });
        try {
            app(BookingService::class)->updateSeries($booking, ['sid' => $court->sid, 'quantity' => 2], ['uid' => $user->uid, 'ownerName' => null], [Carbon::parse('2026-11-10 12:00')], 3600, null, 'single_occurrence');
            $this->fail('Edit ignored a newly committed event.');
        } catch (BookingValidationException $e) {
            $this->assertSame(__('booking.messages.repeat_event_conflict'), $e->getMessage());
        }
        $this->assertTrue($injected);
        $this->assertSame('10:00:00', $booking->reservations()->first()->time_start);
    }
}
