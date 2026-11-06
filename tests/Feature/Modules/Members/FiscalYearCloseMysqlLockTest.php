<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Events\FiscalYearsLocking;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/**
 * The closed-fiscal-year lock with two real MySQL connections: the app's connection runs the
 * write request (payment, manual due), a second connection closes the year as a concurrent
 * "Geschäftsjahr abschließen" would. SQLite ignores FOR UPDATE, so only MySQL can show that:
 *  - a close committed after validation is seen by the write's locking read and the write is refused
 *    (a plain read inside the same transaction still sees the old snapshot),
 *  - a close cannot get the year row while a write transaction holds it,
 *  - a write cannot get the year row while a close transaction holds it, and is refused afterwards.
 * In no case do the close and a write into the closed year both succeed.
 *
 * Rows must be COMMITTED for the second connection to see them, so there is no wrapping
 * transaction: the members tables are truncated before every test (after the MysqlTestCase guard).
 */
#[Group('mysql')]
class FiscalYearCloseMysqlLockTest extends MysqlTestCase
{
    private static bool $migrated = false;

    private const TABLES = ['payment_dues', 'payments', 'member_dues', 'fee_category_rates', 'members', 'fee_categories', 'fiscal_years'];

    private bool $ready = false;

    private User $admin;

    private FiscalYear $year;

    private FeeCategory $category;

    private Member $member;

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

        // Cloned from the connection MysqlTestCase has just verified: same local booking_test database.
        config(['database.connections.mysql_second' => config('database.connections.mysql')]);
        DB::purge('mysql_second');
        $this->ready = true;

        $this->travelTo('2026-11-06 10:00:00');
        $this->admin = User::factory()->create(['status' => 'admin']);
        $this->category = FeeCategory::create(['name' => 'Erwachsene']);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'faellig_am' => '2026-03-31']);
        FeeCategoryRate::create(['fee_category_id' => $this->category->id, 'fiscal_year_id' => $this->year->id, 'amount' => 100]);
        $this->member = Member::factory()->create(['fee_category_id' => $this->category->id]);
    }

    protected function tearDown(): void
    {
        if ($this->ready) {
            if (DB::connection('mysql_second')->transactionLevel() > 0) {
                DB::connection('mysql_second')->rollBack();
            }
            DB::connection('mysql_second')->statement('SET SESSION innodb_lock_wait_timeout = 50');
            DB::disconnect('mysql_second');
            DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
            $this->admin->delete();
        }

        parent::tearDown();
    }

    private function due(): MemberDue
    {
        return MemberDue::create([
            'member_id' => $this->member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->category->id,
            'amount' => 100, 'status' => 'ausstehend', 'faellig_am' => '2026-03-31',
        ]);
    }

    /** The close as another session runs it: its own transaction on the second connection, committed. */
    private function closeOnSecondConnection(): void
    {
        $second = DB::connection('mysql_second');
        $second->transaction(function () use ($second): void {
            $second->select('select id from fiscal_years where id = ? for update', [$this->year->id]);
            $second->update('update fiscal_years set abgeschlossen_am = ?, abgeschlossen_von = ? where id = ?', [now()->toDateTimeString(), 999, $this->year->id]);
        });
    }

    /** Runs $close on the second connection with a 1 s lock wait and returns the MySQL error code (null = it went through). */
    private function tryCloseOnSecondConnection(): ?int
    {
        $second = DB::connection('mysql_second');
        $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $this->closeOnSecondConnection();

            return null;
        } catch (QueryException $e) {
            return (int) $e->errorInfo[1];
        }
    }

    private function committedClosedAt(): ?string
    {
        return DB::connection('mysql_second')->table('fiscal_years')->where('id', $this->year->id)->value('abgeschlossen_am');
    }

    // ---- close committed between validation and write: the locking read sees it --------------

    #[Test]
    public function a_payment_is_refused_when_another_session_closed_the_year_after_validation(): void
    {
        $due = $this->due();
        $snapshotClosedAt = 'not read';
        Event::listen(FiscalYearsLocking::class, function () use (&$snapshotClosedAt): void {
            // The payment transaction already read (pluck of the dues' years), so its snapshot exists.
            $this->closeOnSecondConnection();
            // A plain read inside the payment transaction still sees the old snapshot: only the lock helps.
            $snapshotClosedAt = DB::table('fiscal_years')->where('id', $this->year->id)->value('abgeschlossen_am');
        });

        $this->actingAs($this->admin)->from("/members/{$this->member->id}/payments/create")
            ->post("/members/{$this->member->id}/payments", ['amount' => 100, 'paid_at' => '2026-10-01', 'member_due_ids' => [$due->id]])
            ->assertSessionHasErrors(['member_due_ids' => __('members::admin.payments.due_in_closed_year')]);

        $this->assertNull($snapshotClosedAt, 'REPEATABLE READ snapshot without the lock');
        $this->assertNotNull($this->committedClosedAt(), 'the close succeeded');
        $this->assertSame(0, Payment::query()->count(), 'the payment did not');
        $this->assertSame('ausstehend', $due->fresh()->status);
    }

    #[Test]
    public function a_manual_due_is_refused_when_another_session_closed_the_year_after_validation(): void
    {
        Event::listen(FiscalYearsLocking::class, fn () => $this->closeOnSecondConnection());

        $this->actingAs($this->admin)->from('/members/dues')->post('/members/dues', [
            'member_id' => $this->member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->category->id,
        ])->assertSessionHasErrors(['fiscal_year_id' => $this->year->lockedMessage()]);

        $this->assertNotNull($this->committedClosedAt());
        $this->assertSame(0, MemberDue::query()->count());
    }

    // ---- write transaction open: the close cannot get the year row -----------------------------

    #[Test]
    public function a_close_waits_while_a_payment_transaction_holds_the_year(): void
    {
        $due = $this->due();
        $closeError = 'not tried';
        DB::listen(function (QueryExecuted $query) use (&$closeError): void {
            if ($closeError === 'not tried' && str_starts_with($query->sql, 'insert into `payments`')) {
                $closeError = $this->tryCloseOnSecondConnection();
            }
        });

        $this->actingAs($this->admin)
            ->post("/members/{$this->member->id}/payments", ['amount' => 100, 'paid_at' => '2026-10-01', 'member_due_ids' => [$due->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1205, $closeError, 'the close hit the lock wait timeout');
        $this->assertNull($this->committedClosedAt());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('bezahlt', $due->fresh()->status);

        // Once the payment is committed the close goes through; the payment was made while the year was open.
        $this->assertNull($this->tryCloseOnSecondConnection());
        $this->assertNotNull($this->committedClosedAt());
    }

    #[Test]
    public function a_close_waits_while_a_manual_due_transaction_holds_the_year(): void
    {
        $closeError = 'not tried';
        DB::listen(function (QueryExecuted $query) use (&$closeError): void {
            if ($closeError === 'not tried' && str_starts_with($query->sql, 'insert into `member_dues`')) {
                $closeError = $this->tryCloseOnSecondConnection();
            }
        });

        $this->actingAs($this->admin)->post('/members/dues', [
            'member_id' => $this->member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->category->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1205, $closeError);
        $this->assertNull($this->committedClosedAt());
        $this->assertSame(1, MemberDue::query()->count());
    }

    // ---- close transaction open: the write cannot get the year row, then is refused ------------

    #[Test]
    public function a_payment_waits_for_an_open_close_and_is_refused_after_it_commits(): void
    {
        $due = $this->due();
        $second = DB::connection('mysql_second');
        $second->beginTransaction();
        $second->select('select id from fiscal_years where id = ? for update', [$this->year->id]);
        $second->update('update fiscal_years set abgeschlossen_am = ?, abgeschlossen_von = ? where id = ?', [now()->toDateTimeString(), 999, $this->year->id]);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $this->withoutExceptionHandling()->actingAs($this->admin)
                ->post("/members/{$this->member->id}/payments", ['amount' => 100, 'paid_at' => '2026-10-01', 'member_due_ids' => [$due->id]]);
            $this->fail('expected a lock wait timeout');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1]);
        }
        $this->assertSame(0, Payment::query()->count(), 'the blocked payment left nothing behind');

        $second->commit();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

        $this->withExceptionHandling()->actingAs($this->admin)->from("/members/{$this->member->id}/payments/create")
            ->post("/members/{$this->member->id}/payments", ['amount' => 100, 'paid_at' => '2026-10-01', 'member_due_ids' => [$due->id]])
            ->assertSessionHasErrors('member_due_ids.0');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame('ausstehend', $due->fresh()->status);
    }

    #[Test]
    public function an_inline_due_change_waits_for_an_open_close_and_is_refused_after_it_commits(): void
    {
        $due = $this->due();
        $second = DB::connection('mysql_second');
        $second->beginTransaction();
        $second->update('update fiscal_years set abgeschlossen_am = ?, abgeschlossen_von = ? where id = ?', [now()->toDateTimeString(), 999, $this->year->id]);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        // updateField turns any exception of the write into its generic 500 JSON answer; nothing is written.
        $this->actingAs($this->admin)->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'bezahlt'])
            ->assertStatus(500);
        $this->assertSame('ausstehend', $due->fresh()->status);

        $second->commit();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

        $this->actingAs($this->admin)->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'bezahlt'])
            ->assertStatus(422)->assertJson(['message' => $this->year->lockedMessage()]);
        $this->assertSame('ausstehend', $due->fresh()->status);
    }
}
