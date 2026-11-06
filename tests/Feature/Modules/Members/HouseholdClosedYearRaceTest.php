<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Events\FiscalYearsLocking;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every household write path re-checks the closed year inside its transaction under the year-row lock
 * (decisions-batch-1 pattern): a listener on FiscalYearsLocking closes the year right before the lock,
 * after validation saw it open, and the write must still be refused without changing anything.
 */
class HouseholdClosedYearRaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $family;

    private MemberHousehold $household;

    /** @var list<Member> */
    private array $members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        FeeCategoryRate::create(['fee_category_id' => $this->family->id, 'fiscal_year_id' => $this->year->id, 'amount' => 235]);
        $this->household = MemberHousehold::create(['fee_category_id' => $this->family->id]);
        $this->members = [];
        foreach (['Anna', 'Ben'] as $name) {
            $member = Member::factory()->create(['firstname' => $name, 'lastname' => 'Muster']);
            $member->forceFill(['household_id' => $this->household->id])->save();
            $this->members[] = $member;
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** Closes $year directly in the database right before the write path locks it. */
    private function closeOnLock(FiscalYear $year): void
    {
        $done = false;
        Event::listen(FiscalYearsLocking::class, function (FiscalYearsLocking $event) use ($year, &$done): void {
            if (! $done && in_array($year->id, $event->fiscalYearIds, true)) {
                $done = true;
                DB::table('fiscal_years')->where('id', $year->id)->update(['abgeschlossen_am' => now(), 'abgeschlossen_von' => 999]);
            }
        });
    }

    #[Test]
    public function generating_household_dues(): void
    {
        $this->closeOnLock($this->year);

        $this->actingAs($this->admin())->post('/members/dues/generate', ['fiscal_year_id' => $this->year->id])
            ->assertSessionHasErrors('fiscal_year_id');

        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function entering_a_household_due_by_hand(): void
    {
        $this->closeOnLock($this->year);

        $this->actingAs($this->admin())->post('/members/household-dues', ['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id])
            ->assertSessionHasErrors('fiscal_year_id');

        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function replacing_own_dues_by_the_household_due(): void
    {
        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        $own = MemberDue::create(['member_id' => $this->members[0]->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $adults->id, 'amount' => 150]);
        $this->closeOnLock($this->year);

        $this->actingAs($this->admin())->post("/members/households/{$this->household->id}/replace-dues", ['fiscal_year_id' => $this->year->id, 'confirm' => 1])
            ->assertSessionHasErrors('fiscal_year_id');

        $this->assertNotNull($own->fresh());
        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function covering_a_joiner(): void
    {
        $due = HouseholdDue::create(['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235]);
        $clara = Member::factory()->create();
        $this->closeOnLock($this->year);

        $this->actingAs($this->admin())->post("/members/households/{$this->household->id}/members", ['member_id' => $clara->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->household->id, $clara->fresh()?->household_id);
        $this->assertSame(0, $due->coveredMembers()->count());
    }
}
