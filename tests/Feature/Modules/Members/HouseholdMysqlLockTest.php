<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\DuesGenerator;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;
use Tests\Support\SecondConnection;

/**
 * The household write paths on a real MySQL server with two sessions: a second session plays a year close
 * (or a concurrent payment) that holds the row lock; the household write of the default session must wait
 * for it (error 1205 with a short timeout) and, after the other session commits, see the new state.
 * SQLite ignores FOR UPDATE, so only this proves the year-row lock order of the family-fee code.
 */
#[Group('mysql')]
class HouseholdMysqlLockTest extends MysqlTestCase
{
    private static bool $migrated = false;

    private FiscalYear $year;

    private FeeCategory $family;

    private MemberHousehold $household;

    /** @var list<Member> */
    private array $members = [];

    private ?Connection $second = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        foreach (['household_due_payment', 'household_due_member', 'payment_dues', 'household_dues', 'member_dues', 'payments', 'member_changes'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('members')->update(['household_id' => null]);
        DB::table('member_households')->delete();
        foreach (['members', 'fee_category_rates', 'fee_categories', 'fiscal_years', 'bs_users'] as $table) {
            DB::table($table)->delete();
        }

        $this->travelTo('2026-11-06 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        FeeCategoryRate::create(['fee_category_id' => $this->family->id, 'fiscal_year_id' => $this->year->id, 'amount' => 235]);
        $this->members = [
            Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']),
            Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']),
        ];
        $this->household = app(HouseholdService::class)->create([$this->members[0]->id, $this->members[1]->id], $this->family->id, 'Familie Muster');
    }

    protected function tearDown(): void
    {
        SecondConnection::release();
        $this->second = null;
        parent::tearDown();
    }

    private function second(): Connection
    {
        return $this->second ??= SecondConnection::make();
    }

    /** The other request: a year close that holds the year row (not committed yet). */
    private function secondHoldsYear(): void
    {
        $this->second()->beginTransaction();
        $this->second()->select('SELECT id FROM fiscal_years WHERE id = ? FOR UPDATE', [$this->year->id]);
    }

    private function secondClosesAndCommits(): void
    {
        $this->second()->update('UPDATE fiscal_years SET abgeschlossen_am = ?, abgeschlossen_von = 1 WHERE id = ?', ['2026-11-06 10:00:00', $this->year->id]);
        $this->second()->commit();
    }

    private function assertLockWaitTimeout(callable $statement, string $message): void
    {
        try {
            $statement();
            $this->fail('expected a lock wait timeout: '.$message);
        } catch (QueryException $e) {
            $this->assertSame(1205, $e->errorInfo[1], $message.': '.$e->getMessage());
        }
    }

    #[Test]
    public function generation_waits_for_a_running_close_and_then_refuses_the_closed_year(): void
    {
        SecondConnection::waitAtMost(1);
        $this->secondHoldsYear();

        $this->assertLockWaitTimeout(fn () => app(DuesGenerator::class)->generate($this->year), 'generation behind the close');
        $this->assertSame(0, HouseholdDue::count());

        $this->secondClosesAndCommits();
        try {
            app(DuesGenerator::class)->generate($this->year->fresh() ?? $this->year);
            $this->fail('the closed year must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fiscal_year_id', $e->errors());
        }
        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function a_joiner_waits_for_a_running_close_and_is_then_not_covered(): void
    {
        $due = HouseholdDue::create(['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235]);
        $clara = Member::factory()->create(['firstname' => 'Clara', 'lastname' => 'Muster']);
        SecondConnection::waitAtMost(1);
        $this->secondHoldsYear();

        $this->assertLockWaitTimeout(fn () => app(HouseholdService::class)->addMember($this->household, $clara), 'joiner behind the close');
        $this->assertNull($clara->fresh()?->household_id);

        $this->secondClosesAndCommits();
        app(HouseholdService::class)->addMember($this->household, $clara);

        $this->assertSame($this->household->id, $clara->fresh()?->household_id);
        $this->assertSame(0, $due->coveredMembers()->count());
    }

    #[Test]
    public function a_payment_waits_for_a_concurrent_payment_of_the_same_household_due_and_is_then_refused(): void
    {
        $due = HouseholdDue::create(['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235]);
        $due->coveredMembers()->attach($this->members[0]->id, ['fiscal_year_id' => $this->year->id]);
        $admin = User::factory()->create(['status' => 'admin']);
        SecondConnection::waitAtMost(1);

        // The other request: a payment that holds the year row and the due row, then settles the due.
        $second = $this->second();
        $second->beginTransaction();
        $second->select('SELECT id FROM fiscal_years WHERE id = ? FOR UPDATE', [$this->year->id]);
        $second->select('SELECT id FROM household_dues WHERE id = ? FOR UPDATE', [$due->id]);

        $payload = ['amount' => '235.00', 'paid_at' => '2026-11-06', 'household_due_ids' => [$due->id]];
        $this->withoutExceptionHandling();
        $this->assertLockWaitTimeout(fn () => $this->actingAs($admin)->post("/members/{$this->members[0]->id}/payments", $payload), 'payment behind the other payment');
        $this->assertSame(0, DB::table('payments')->count());

        $paymentId = $second->table('payments')->insertGetId(['member_id' => $this->members[1]->id, 'amount' => '235.00', 'paid_at' => '2026-11-06', 'created_at' => now(), 'updated_at' => now()]);
        $second->table('household_due_payment')->insert(['payment_id' => $paymentId, 'household_due_id' => $due->id]);
        $second->update("UPDATE household_dues SET status = 'bezahlt' WHERE id = ?", [$due->id]);
        $second->commit();

        try {
            $this->actingAs($admin)->post("/members/{$this->members[0]->id}/payments", $payload);
            $this->fail('the paid household due must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('household_due_ids.0', $e->errors());
        }
        $this->assertSame(1, DB::table('payments')->count());
    }

    #[Test]
    public function the_household_due_field_endpoint_waits_for_a_running_close(): void
    {
        $due = HouseholdDue::create(['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235]);
        $admin = User::factory()->create(['status' => 'admin']);
        SecondConnection::waitAtMost(1);
        $this->secondHoldsYear();

        $this->withoutExceptionHandling();
        $this->assertLockWaitTimeout(fn () => $this->actingAs($admin)->patchJson("/members/household-dues/{$due->id}/field", ['field' => 'status', 'value' => 'bezahlt']), 'inline status behind the close');

        $this->secondClosesAndCommits();
        $this->actingAs($admin)->patchJson("/members/household-dues/{$due->id}/field", ['field' => 'status', 'value' => 'bezahlt'])->assertStatus(422);
        $this->assertSame('ausstehend', $due->fresh()?->status);
    }
}
