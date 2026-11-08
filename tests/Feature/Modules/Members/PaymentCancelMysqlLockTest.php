<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Events\FiscalYearsLocking;
use App\Modules\Members\Models\FeeCategory;
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
 * "Zahlung stornieren" against a concurrent "Geschäftsjahr abschließen" with two real MySQL connections (as in
 * FiscalYearCloseMysqlLockTest): a close committed after the cancel's first reads is seen by its locking read
 * and the cancel is refused; a close waits while a cancel transaction holds the year row; a cancel waits for an
 * open close and is refused after it commits. Never both: no cancelled payment in a closed year.
 */
#[Group('mysql')]
class PaymentCancelMysqlLockTest extends MysqlTestCase
{
    private static bool $migrated = false;

    private const TABLES = ['member_changes', 'payment_dues', 'payments', 'member_dues', 'members', 'fee_categories', 'fiscal_years'];

    private bool $ready = false;

    private User $admin;

    private FiscalYear $year;

    private Member $member;

    private MemberDue $due;

    private Payment $payment;

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

        config(['database.connections.mysql_second' => config('database.connections.mysql')]);
        DB::purge('mysql_second');
        $this->ready = true;

        $this->travelTo('2026-11-08 10:00:00');
        $this->admin = User::factory()->create(['status' => 'admin']);
        $category = FeeCategory::create(['name' => 'Erwachsene']);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->member = Member::factory()->create(['fee_category_id' => $category->id]);
        $this->due = MemberDue::create([
            'member_id' => $this->member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $category->id,
            'amount' => 100, 'status' => 'bezahlt', 'bezahlt_am' => '2026-03-01',
        ]);
        $this->payment = Payment::create(['member_id' => $this->member->id, 'amount' => 100, 'paid_at' => '2026-03-01']);
        $this->payment->dues()->attach($this->due->id);
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

    private function closeOnSecondConnection(): void
    {
        $second = DB::connection('mysql_second');
        $second->transaction(function () use ($second): void {
            $second->select('select id from fiscal_years where id = ? for update', [$this->year->id]);
            $second->update('update fiscal_years set abgeschlossen_am = ?, abgeschlossen_von = ? where id = ?', [now()->toDateTimeString(), 999, $this->year->id]);
        });
    }

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

    private function cancelUrl(): string
    {
        return route('members.payments.cancel', ['member' => $this->member->id, 'payment' => $this->payment->id]);
    }

    private function committedClosedAt(): ?string
    {
        return DB::connection('mysql_second')->table('fiscal_years')->where('id', $this->year->id)->value('abgeschlossen_am');
    }

    #[Test]
    public function a_cancel_is_refused_when_another_session_closed_the_year_after_its_first_reads(): void
    {
        Event::listen(FiscalYearsLocking::class, fn () => $this->closeOnSecondConnection());

        $this->actingAs($this->admin)->post($this->cancelUrl())->assertSessionHasErrors('payment');

        $this->assertNotNull($this->committedClosedAt());
        $this->assertNull($this->payment->fresh()->cancelled_at);
        $this->assertSame('bezahlt', $this->due->fresh()->status);
    }

    #[Test]
    public function a_close_waits_while_a_cancel_transaction_holds_the_year(): void
    {
        $closeError = 'not tried';
        DB::listen(function (QueryExecuted $query) use (&$closeError): void {
            if ($closeError === 'not tried' && str_starts_with($query->sql, 'update `payments` set `cancelled_at`')) {
                $closeError = $this->tryCloseOnSecondConnection();
            }
        });

        $this->actingAs($this->admin)->post($this->cancelUrl())->assertSessionHasNoErrors();

        $this->assertSame(1205, $closeError, 'the close hit the lock wait timeout');
        $this->assertNotNull($this->payment->fresh()->cancelled_at);
        $this->assertSame('ausstehend', $this->due->fresh()->status);
        $this->assertNull($this->tryCloseOnSecondConnection(), 'after the commit the close goes through');
    }

    #[Test]
    public function a_cancel_waits_for_an_open_close_and_is_refused_after_it_commits(): void
    {
        $second = DB::connection('mysql_second');
        $second->beginTransaction();
        $second->select('select id from fiscal_years where id = ? for update', [$this->year->id]);
        $second->update('update fiscal_years set abgeschlossen_am = ?, abgeschlossen_von = ? where id = ?', [now()->toDateTimeString(), 999, $this->year->id]);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $this->withoutExceptionHandling()->actingAs($this->admin)->post($this->cancelUrl());
            $this->fail('expected a lock wait timeout');
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1]);
        }
        $this->assertNull($this->payment->fresh()->cancelled_at, 'the blocked cancel left nothing behind');

        $second->commit();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

        $this->withExceptionHandling()->actingAs($this->admin)->post($this->cancelUrl())->assertSessionHasErrors('payment');
        $this->assertNull($this->payment->fresh()->cancelled_at);
        $this->assertSame('bezahlt', $this->due->fresh()->status);
    }
}
