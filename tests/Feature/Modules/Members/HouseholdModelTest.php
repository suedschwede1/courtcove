<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Modules\Members\Models\Concerns\HasDueStatus;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Models\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Household models: label, billable members (aktiv + ruhend), coverage relations, the effective
 * due per member and the "replaceable own due" rule (unpaid, no payment, no partial amount).
 */
class HouseholdModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function year(string $name = '2026'): FiscalYear
    {
        return FiscalYear::create(['name' => $name, 'starts_on' => "{$name}-01-01", 'ends_on' => "{$name}-12-31"]);
    }

    private function join(Member $member, MemberHousehold $household): Member
    {
        $member->forceFill(['household_id' => $household->id])->save();

        return $member;
    }

    #[Test]
    public function label_falls_back_to_sorted_unique_last_names(): void
    {
        $household = MemberHousehold::create([]);
        $this->join(Member::factory()->create(['lastname' => 'Muster']), $household);
        $this->join(Member::factory()->create(['lastname' => 'Beispiel']), $household);
        $this->join(Member::factory()->create(['lastname' => 'Muster']), $household);

        $this->assertSame('Beispiel / Muster', $household->fresh()?->label());
        $household->update(['name' => 'Familie Muster']);
        $this->assertSame('Familie Muster', $household->fresh()?->label());
    }

    #[Test]
    public function label_of_an_empty_unnamed_household_uses_its_number(): void
    {
        $household = MemberHousehold::create([]);

        $this->assertSame("Haushalt #{$household->id}", $household->label());
    }

    #[Test]
    public function billable_members_are_active_and_dormant_but_not_departed_or_anonymised(): void
    {
        $household = MemberHousehold::create([]);
        foreach (['aktiv', 'ruhend', 'ausgetreten'] as $status) {
            $this->join(Member::factory()->create(['status' => $status]), $household);
        }
        $this->join(Member::factory()->create(['status' => 'ruhend', 'anonymized_at' => now()]), $household);

        $this->assertSame(['aktiv', 'ruhend'], $household->billableMembers()->orderBy('status')->pluck('status')->all());
        $this->assertCount(4, $household->members);
        $this->assertSame(['aktiv', 'ruhend'], MemberHousehold::BILLABLE_STATUSES);
        $this->assertSame(2, MemberHousehold::MIN_BILLABLE_MEMBERS);
    }

    #[Test]
    public function payer_category_and_dissolved_state_are_exposed(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $household = MemberHousehold::create(['fee_category_id' => $family->id]);
        $anna = $this->join(Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']), $household);
        $household->forceFill(['payer_member_id' => $anna->id])->save();
        $dissolved = MemberHousehold::create([]);
        $dissolved->forceFill(['dissolved_at' => now()])->save();

        $household = $household->fresh();
        $this->assertNotNull($household);
        $this->assertTrue($household->payer?->is($anna));
        $this->assertTrue($household->feeCategory?->is($family));
        $this->assertTrue($anna->fresh()?->household?->is($household));
        $this->assertFalse($household->isDissolved());
        $this->assertTrue($dissolved->fresh()?->isDissolved());
        $this->assertSame([$household->id], MemberHousehold::query()->current()->pluck('id')->all());
    }

    #[Test]
    public function household_id_is_not_mass_assignable(): void
    {
        $household = MemberHousehold::create([]);
        $member = Member::factory()->create();

        $member->update(['household_id' => $household->id]);

        $this->assertNull($member->fresh()?->household_id);
    }

    #[Test]
    public function effective_due_prefers_the_own_due_and_falls_back_to_the_covering_household_due(): void
    {
        $year = $this->year();
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $household = MemberHousehold::create(['fee_category_id' => $family->id]);
        $anna = Member::factory()->create();
        $due = HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $family->id, 'amount' => 235]);
        $due->coveredMembers()->attach($anna->id, ['fiscal_year_id' => $year->id]);

        $this->assertTrue($anna->effectiveDueFor($year)?->is($due));
        $this->assertTrue($anna->effectiveDueFor($year->id)?->is($due));
        $this->assertTrue($family->isPerHousehold());
        $this->assertNull(Member::factory()->create()->effectiveDueFor($year));

        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        $ben = Member::factory()->create();
        $own = MemberDue::create(['member_id' => $ben->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $adults->id, 'amount' => 150]);
        $this->assertTrue($ben->effectiveDueFor($year)?->is($own));
        $this->assertNull($ben->effectiveDueFor($this->year('2027')));
    }

    #[Test]
    public function a_household_due_has_the_shared_status_rules_coverage_and_payments(): void
    {
        $year = $this->year();
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $household = MemberHousehold::create(['fee_category_id' => $family->id]);
        $anna = Member::factory()->create();
        $due = HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $family->id, 'amount' => 235, 'faellig_am' => '2026-03-01']);
        $due->coveredMembers()->attach($anna->id, ['fiscal_year_id' => $year->id]);

        $this->assertContains(HasDueStatus::class, class_uses_recursive(HouseholdDue::class));
        $this->assertSame(MemberDue::STATUSES, HouseholdDue::STATUSES);
        $due = $due->fresh();
        $this->assertNotNull($due);
        $this->assertSame('ausstehend', $due->status);
        $this->assertSame('235.00', $due->amount);
        $this->assertTrue($due->household->is($household));
        $this->assertTrue($due->fiscalYear->is($year));
        $this->assertTrue($due->feeCategory->is($family));
        $this->assertSame($year->id, (int) $due->coveredMembers->first()?->pivot?->getAttribute('fiscal_year_id'));
        $this->assertTrue($anna->coveringHouseholdDues()->first()?->is($due));
        $this->assertTrue($household->dues()->first()?->is($due));
        $this->assertSame(1, HouseholdDue::query()->open()->count());

        $payment = Payment::create(['member_id' => $anna->id, 'amount' => 235, 'paid_at' => '2026-04-01']);
        $due->payments()->attach($payment->id);
        $this->assertTrue($due->fresh()?->isPaid());
        $this->assertSame(0, HouseholdDue::query()->open()->count());
        $this->assertSame(1, HouseholdDue::query()->paid()->count());
    }

    #[Test]
    public function fee_categories_bill_per_member_by_default(): void
    {
        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);

        $this->assertFalse($adults->fresh()?->isPerHousehold());
        $this->assertSame(['member', 'household'], FeeCategory::BILLING_UNITS);
        $this->assertSame([$adults->id], FeeCategory::query()->perMember()->pluck('id')->all());
        $this->assertSame([$family->id], FeeCategory::query()->perHousehold()->pluck('id')->all());
    }

    #[Test]
    public function a_due_is_replaceable_only_when_unpaid_without_payment_and_without_partial_amount(): void
    {
        $year = $this->year();
        $category = FeeCategory::create(['name' => 'Erwachsene']);
        $make = fn (array $a) => MemberDue::create([...['member_id' => Member::factory()->create()->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $category->id, 'amount' => 150, 'status' => 'ausstehend'], ...$a]);

        $this->assertTrue($make([])->isReplaceable());
        $this->assertTrue($make(['teilbetrag' => 0])->isReplaceable());
        $this->assertTrue($make(['status' => 'ueberfaellig'])->isReplaceable());
        $this->assertFalse($make(['status' => 'bezahlt'])->isReplaceable());
        $this->assertFalse($make(['teilbetrag' => 50])->isReplaceable());

        $withPayment = $make([]);
        $payment = Payment::create(['member_id' => $withPayment->member_id, 'amount' => 150, 'paid_at' => '2026-04-01']);
        $withPayment->payments()->attach($payment->id);
        $this->assertFalse($withPayment->fresh()?->isReplaceable());
    }
}
