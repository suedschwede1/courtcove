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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/**
 * Bulk "Als bezahlt markieren" against concurrent writers on a second real MySQL connection: a year close that
 * commits while the run is under way turns the remaining rows into "closed_year" skips; a single payment of
 * another session that commits after the run read its rows is seen by the locking paid check ("already_paid"),
 * so a due never ends up with two payments.
 */
#[Group('mysql')]
class DuesBulkPayMysqlLockTest extends MysqlTestCase
{
    private static bool $migrated = false;

    private const TABLES = ['member_changes', 'household_due_payment', 'payment_dues', 'payments', 'member_dues', 'members', 'fee_categories', 'fiscal_years'];

    private bool $ready = false;

    private User $admin;

    private FiscalYear $year;

    private MemberDue $first;

    private MemberDue $second;

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
        foreach (['first', 'second'] as $property) {
            $member = Member::factory()->create(['fee_category_id' => $category->id]);
            $this->{$property} = MemberDue::create([
                'member_id' => $member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $category->id,
                'amount' => 100, 'status' => 'ausstehend',
            ]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->ready) {
            if (DB::connection('mysql_second')->transactionLevel() > 0) {
                DB::connection('mysql_second')->rollBack();
            }
            DB::disconnect('mysql_second');
            $this->admin->delete();
        }

        parent::tearDown();
    }

    private function bulk(): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('members.dues.bulk-pay'), [
            'mode' => 'selected',
            'fiscal_year_id' => $this->year->id,
            'due_ids' => [$this->first->id, $this->second->id],
            'paid_at' => '2026-11-08',
        ]);
    }

    /** @return array<string, int> */
    private function reasons(): array
    {
        return collect(session('bulk_pay_report')['skipped'])->countBy('reason')->all();
    }

    #[Test]
    public function a_close_committed_during_the_run_skips_the_remaining_rows(): void
    {
        $calls = 0;
        Event::listen(FiscalYearsLocking::class, function () use (&$calls): void {
            // The first row goes through; before the second row locks the year, another session closes it.
            if (++$calls === 2) {
                $second = DB::connection('mysql_second');
                $second->transaction(function () use ($second): void {
                    $second->select('select id from fiscal_years where id = ? for update', [$this->year->id]);
                    $second->update('update fiscal_years set abgeschlossen_am = ?, abgeschlossen_von = ? where id = ?', [now()->toDateTimeString(), 999, $this->year->id]);
                });
            }
        });

        $this->bulk()->assertSessionHasNoErrors();

        $this->assertSame(1, session('bulk_pay_report')['paid']);
        $this->assertSame(['closed_year' => 1], $this->reasons());
        $this->assertSame('bezahlt', $this->first->fresh()->status);
        $this->assertSame('ausstehend', $this->second->fresh()->status);
        $this->assertSame(0, $this->second->payments()->count());
    }

    #[Test]
    public function a_payment_of_another_session_is_seen_and_not_doubled(): void
    {
        $paidElsewhere = false;
        Event::listen(FiscalYearsLocking::class, function () use (&$paidElsewhere): void {
            if ($paidElsewhere) {
                return;
            }
            $paidElsewhere = true;
            // "Zahlung erfassen" for the first due commits in another session after the run loaded its rows.
            $second = DB::connection('mysql_second');
            $second->transaction(function () use ($second): void {
                $second->insert('insert into payments (member_id, amount, paid_at, created_at, updated_at) values (?, ?, ?, ?, ?)', [$this->first->member_id, 100, '2026-11-07', now(), now()]);
                $paymentId = (int) $second->getPdo()->lastInsertId();
                $second->insert('insert into payment_dues (payment_id, member_due_id) values (?, ?)', [$paymentId, $this->first->id]);
                $second->update('update member_dues set status = ?, bezahlt_am = ? where id = ?', ['bezahlt', '2026-11-07', $this->first->id]);
            });
        });

        $this->bulk()->assertSessionHasNoErrors();

        $this->assertSame(1, session('bulk_pay_report')['paid']);
        $this->assertSame(['already_paid' => 1], $this->reasons());
        $this->assertSame(1, $this->first->payments()->count(), 'no second payment for the first due');
        $this->assertSame('2026-11-07', $this->first->fresh()->bezahlt_am?->toDateString());
        $this->assertSame(1, $this->second->payments()->count());
        $this->assertSame(2, Payment::query()->count());
    }
}
