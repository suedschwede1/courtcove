<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Models\Payment;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A payment entered on a member's page can settle the household due covering that member (exact amount rule). */
class HouseholdPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $family;

    private MemberHousehold $h;

    private Member $anna;

    private Member $ben;

    private HouseholdDue $due;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $this->anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $this->ben = Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']);
        $this->h = app(HouseholdService::class)->create([$this->anna->id, $this->ben->id], $this->family->id, 'Familie Muster');
        $this->due = HouseholdDue::create(['household_id' => $this->h->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235]);
        $this->due->coveredMembers()->attach([$this->anna->id => ['fiscal_year_id' => $this->year->id], $this->ben->id => ['fiscal_year_id' => $this->year->id]]);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function the_payment_form_offers_the_open_household_due(): void
    {
        $this->actingAs($this->admin())->get("/members/{$this->anna->id}/payments/create")
            ->assertOk()
            ->assertSeeText(__('members::admin.payments.household_due_label', ['household' => 'Familie Muster', 'year' => '2026', 'amount' => '235,00 €']))
            ->assertSee('name="household_due_ids[]" value="'.$this->due->id.'"', false);
    }

    #[Test]
    public function paying_the_household_due_marks_every_covered_member_paid(): void
    {
        $this->actingAs($this->admin())->post("/members/{$this->anna->id}/payments", ['amount' => '235.00', 'paid_at' => '2026-11-06', 'household_due_ids' => [$this->due->id]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('bezahlt', $this->due->fresh()?->status);
        $this->assertSame('2026-11-06', $this->due->fresh()?->bezahlt_am?->toDateString());
        $this->assertDatabaseHas('household_due_payment', ['household_due_id' => $this->due->id]);
        $this->assertSame($this->anna->id, Payment::sole()->member_id);
        $this->assertTrue($this->ben->fresh()?->effectiveDueFor($this->year)?->isPaid());
    }

    #[Test]
    public function the_amount_must_match_member_and_household_dues_together(): void
    {
        $old = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        $own = MemberDue::create(['member_id' => $this->anna->id, 'fiscal_year_id' => $old->id, 'fee_category_id' => $adults->id, 'amount' => 150]);
        $admin = $this->admin();
        $payload = ['paid_at' => '2026-11-06', 'member_due_ids' => [$own->id], 'household_due_ids' => [$this->due->id]];

        $this->actingAs($admin)->post("/members/{$this->anna->id}/payments", [...$payload, 'amount' => '300.00'])->assertSessionHasErrors('amount');
        $this->assertSame(0, Payment::count());

        $this->actingAs($admin)->post("/members/{$this->anna->id}/payments", [...$payload, 'amount' => '385.00'])->assertSessionHasNoErrors();
        $this->assertSame('bezahlt', $own->fresh()?->status);
        $this->assertSame('bezahlt', $this->due->fresh()?->status);
        $this->assertSame(1, Payment::count());
    }

    #[Test]
    public function a_paid_household_due_cannot_be_allocated_twice(): void
    {
        $admin = $this->admin();
        $payload = ['amount' => '235.00', 'paid_at' => '2026-11-06', 'household_due_ids' => [$this->due->id]];
        $this->actingAs($admin)->post("/members/{$this->anna->id}/payments", $payload)->assertSessionHasNoErrors();

        $this->actingAs($admin)->post("/members/{$this->ben->id}/payments", $payload)->assertSessionHasErrors('household_due_ids.0');
        $this->assertSame(1, Payment::count());
    }

    #[Test]
    public function a_household_due_not_related_to_the_member_or_of_a_closed_year_is_refused(): void
    {
        $dora = Member::factory()->create();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/members/{$dora->id}/payments", ['amount' => '235.00', 'paid_at' => '2026-11-06', 'household_due_ids' => [$this->due->id]])
            ->assertSessionHasErrors('household_due_ids.0');
        $this->actingAs($admin)->post("/members/{$this->anna->id}/payments", ['amount' => '235.00', 'paid_at' => '2026-11-06'])
            ->assertSessionHasErrors('member_due_ids');

        $this->year->close(1);
        $this->actingAs($admin)->post("/members/{$this->anna->id}/payments", ['amount' => '235.00', 'paid_at' => '2026-11-06', 'household_due_ids' => [$this->due->id]])
            ->assertSessionHasErrors('household_due_ids.0');
        $this->assertSame(0, Payment::count());
        $this->actingAs($admin)->get("/members/{$this->anna->id}/payments/create")->assertDontSee('name="household_due_ids[]"', false);
    }

    #[Test]
    public function a_member_who_left_the_household_can_still_pay_the_due_covering_them(): void
    {
        app(HouseholdService::class)->removeMember($this->h, $this->ben);

        $this->actingAs($this->admin())->post("/members/{$this->ben->id}/payments", ['amount' => '235.00', 'paid_at' => '2026-11-06', 'household_due_ids' => [$this->due->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame('bezahlt', $this->due->fresh()?->status);
    }
}
