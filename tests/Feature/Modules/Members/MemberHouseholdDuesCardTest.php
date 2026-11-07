<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The member's dues card shows the covering household dues (chip to the household, editable status) and paid-until counts them. */
class MemberHouseholdDuesCardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private Member $anna;

    private HouseholdDue $due;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $this->anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $household = app(HouseholdService::class)->create([$this->anna->id], $family->id, 'Familie Muster');
        $this->due = HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $family->id, 'amount' => 235]);
        $this->due->coveredMembers()->attach($this->anna->id, ['fiscal_year_id' => $this->year->id]);
    }

    #[Test]
    public function the_dues_card_shows_the_covering_household_due_with_an_editable_status(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'admin']))->get("/members/{$this->anna->id}/edit")
            ->assertOk()
            ->assertSeeText(__('members::admin.households.chip'))
            ->assertSeeText('235,00 €')
            ->assertSee('data-household-due="'.$this->due->id.'"', false)
            // Since 2026-11-08 the status can be reset/changed here too (same endpoint and rules as the household page).
            ->assertSee('saveHouseholdDueField('.$this->due->id.", 'status'", false);
    }

    #[Test]
    public function paid_until_considers_paid_household_coverage(): void
    {
        $this->assertNull($this->anna->fresh()?->paidUntil());

        $this->due->update(['status' => 'bezahlt']);

        $this->assertSame('2026-12-31', $this->anna->fresh()?->paidUntil()?->toDateString());
    }
}
