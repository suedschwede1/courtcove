<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdRuleException;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * All household writes go through HouseholdService: one household per member, payer rules, coverage
 * for joiners without surcharge (decision 6), coverage and dues kept until year end on leave or
 * dissolve (decision 7), and every membership change logged per member.
 */
class HouseholdServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private HouseholdService $service;

    private FiscalYear $year;

    private FeeCategory $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->service = app(HouseholdService::class);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
    }

    private function due(MemberHousehold $household, ?FiscalYear $year = null): HouseholdDue
    {
        return HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => ($year ?? $this->year)->id, 'fee_category_id' => $this->family->id, 'amount' => 235]);
    }

    #[Test]
    public function a_member_cannot_belong_to_two_households(): void
    {
        $anna = Member::factory()->create();
        $this->service->create([$anna->id], $this->family->id, null);
        $second = $this->service->create([], $this->family->id, null);

        $this->expectException(HouseholdRuleException::class);
        $this->service->addMember($second, $anna->fresh() ?? $anna);
    }

    #[Test]
    public function the_payer_must_belong_to_the_household_and_is_cleared_on_leave_and_departure(): void
    {
        [$anna, $ben, $dora] = Member::factory()->count(3)->create()->all();
        $household = $this->service->create([$anna->id, $ben->id], $this->family->id, null);

        $this->assertThrows(fn () => $this->service->setPayer($household, $dora), HouseholdRuleException::class);
        $this->service->setPayer($household, $anna->fresh());
        $this->assertSame($anna->id, $household->fresh()?->payer_member_id);
        $this->assertSame(1, MemberChange::where('member_id', $anna->id)->where('action', 'household_payer')->count());

        $this->service->removeMember($household->fresh() ?? $household, $anna->fresh() ?? $anna);
        $this->assertNull($household->fresh()?->payer_member_id);
        $this->assertSame(2, MemberChange::where('member_id', $anna->id)->where('action', 'household_payer')->count());

        $this->service->setPayer($household->fresh() ?? $household, $ben->fresh());
        $this->service->memberDeparted($ben->fresh() ?? $ben);
        $this->assertNull($household->fresh()?->payer_member_id);
    }

    #[Test]
    public function setting_no_payer_clears_it(): void
    {
        [$anna, $ben] = Member::factory()->count(2)->create()->all();
        $household = $this->service->create([$anna->id, $ben->id], $this->family->id, null);
        $this->service->setPayer($household, $anna->fresh());

        $this->service->setPayer($household->fresh() ?? $household, null);

        $this->assertNull($household->fresh()?->payer_member_id);
    }

    #[Test]
    public function joining_mid_year_is_covered_without_surcharge(): void
    {
        [$anna, $ben, $clara] = Member::factory()->count(3)->create()->all();
        $household = $this->service->create([$anna->id, $ben->id], $this->family->id, null);
        $due = $this->due($household);

        $this->service->addMember($household, $clara);

        $this->assertTrue($due->coveredMembers()->whereKey($clara->id)->exists());
        $this->assertSame('235.00', $due->fresh()?->amount);
    }

    #[Test]
    public function a_joiner_with_an_own_due_or_coverage_elsewhere_is_not_covered_again(): void
    {
        [$anna, $ben] = Member::factory()->count(2)->create()->all();
        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        MemberDue::create(['member_id' => $anna->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $adults->id, 'amount' => 150]);
        $old = $this->service->create([$ben->id], $this->family->id, null);
        $this->due($old)->coveredMembers()->attach($ben->id, ['fiscal_year_id' => $this->year->id]);
        $this->service->removeMember($old, $ben->fresh() ?? $ben);

        $household = $this->service->create([], $this->family->id, null);
        $due = $this->due($household);
        $this->service->addMember($household, $anna->fresh() ?? $anna);
        $this->service->addMember($household, $ben->fresh() ?? $ben);

        $this->assertSame(0, $due->coveredMembers()->count());
        $this->assertSame(1, MemberDue::count());
    }

    #[Test]
    public function a_joiner_is_not_covered_in_a_closed_fiscal_year(): void
    {
        $anna = Member::factory()->create();
        $household = $this->service->create([], $this->family->id, null);
        $due = $this->due($household);
        $this->year->close(1);

        $this->service->addMember($household, $anna);

        $this->assertSame($household->id, $anna->fresh()?->household_id);
        $this->assertSame(0, $due->coveredMembers()->count());
    }

    #[Test]
    public function departed_and_anonymised_members_cannot_join(): void
    {
        $household = $this->service->create([], $this->family->id, null);
        $departed = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2025-12-31']);
        $anonymised = Member::factory()->create(['status' => 'ruhend', 'anonymized_at' => now()]);

        $this->assertThrows(fn () => $this->service->addMember($household, $departed), HouseholdRuleException::class);
        $this->assertThrows(fn () => $this->service->addMember($household, $anonymised), HouseholdRuleException::class);
        $this->assertSame(0, $household->members()->count());
    }

    #[Test]
    public function only_active_per_household_categories_can_be_the_tariff(): void
    {
        $adults = FeeCategory::create(['name' => 'Erwachsene']);
        $old = FeeCategory::create(['name' => 'Alt-Familie', 'billing_unit' => 'household']);
        $old->deactivate();

        $this->assertThrows(fn () => $this->service->create([], $adults->id, null), HouseholdRuleException::class);
        $this->assertThrows(fn () => $this->service->create([], $old->id, null), HouseholdRuleException::class);
        $this->assertSame(0, MemberHousehold::count());

        $grouping = $this->service->create([], null, '  ');
        $this->assertNull($grouping->fee_category_id);
        $this->assertNull($grouping->name);
    }

    #[Test]
    public function dissolving_keeps_household_dues_and_coverage_until_year_end(): void
    {
        [$anna, $ben] = Member::factory()->count(2)->create()->all();
        $household = $this->service->create([$anna->id, $ben->id], $this->family->id, null);
        $due = $this->due($household);
        $due->coveredMembers()->attach([$anna->id => ['fiscal_year_id' => $this->year->id], $ben->id => ['fiscal_year_id' => $this->year->id]]);
        $this->service->setPayer($household, $anna->fresh());

        $this->service->removeMember($household, $ben->fresh() ?? $ben);
        $this->service->dissolve($household->fresh() ?? $household);

        $household = $household->fresh();
        $this->assertNotNull($household?->dissolved_at);
        $this->assertNull($household->payer_member_id);
        $this->assertDatabaseHas('household_dues', ['id' => $due->id, 'amount' => 235]);
        $this->assertSame(2, $due->coveredMembers()->count());
        $this->assertNull($anna->fresh()?->household_id);
        $this->assertSame($due->id, $ben->fresh()?->effectiveDueFor($this->year)?->id);
    }

    #[Test]
    public function a_dissolved_household_accepts_no_new_members(): void
    {
        $household = $this->service->create([], $this->family->id, null);
        $this->service->dissolve($household);

        $this->expectException(HouseholdRuleException::class);
        $this->service->addMember($household->fresh() ?? $household, Member::factory()->create());
    }

    #[Test]
    public function household_membership_changes_are_logged_per_member(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $this->actingAs($admin);
        $anna = Member::factory()->create();
        $household = $this->service->create([$anna->id], $this->family->id, null);

        $change = MemberChange::where('member_id', $anna->id)->latest('id')->first();
        $this->assertNotNull($change);
        $this->assertSame('updated', $change->action);
        $this->assertNull($change->before['household_id']);
        $this->assertSame($household->id, $change->after['household_id']);
        $this->assertSame($admin->uid, $change->admin_user_id);

        $this->service->removeMember($household, $anna->fresh() ?? $anna);
        $last = MemberChange::where('member_id', $anna->id)->latest('id')->first();
        $this->assertSame($household->id, $last?->before['household_id']);
        $this->assertNull($last?->after['household_id']);
    }

    #[Test]
    public function anonymising_the_last_member_dissolves_and_unnames_the_household(): void
    {
        $anna = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2020-01-01']);
        $household = $this->service->create([], $this->family->id, 'Familie Muster');
        $anna->forceFill(['household_id' => $household->id])->save();
        $household->forceFill(['payer_member_id' => $anna->id])->save();

        $this->service->detachForAnonymisation($anna);

        $household = $household->fresh();
        $this->assertNull($household?->name);
        $this->assertNotNull($household?->dissolved_at);
        $this->assertNull($household?->payer_member_id);
        $this->assertNull($anna->fresh()?->household_id);
    }

    #[Test]
    public function anonymising_one_of_several_members_keeps_the_household(): void
    {
        $anna = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2020-01-01']);
        $ben = Member::factory()->create();
        $household = $this->service->create([$ben->id], $this->family->id, 'Familie Muster');
        $anna->forceFill(['household_id' => $household->id])->save();

        $this->service->detachForAnonymisation($anna);

        $this->assertSame('Familie Muster', $household->fresh()?->name);
        $this->assertNull($household->fresh()?->dissolved_at);
    }

    #[Test]
    public function a_departure_entered_on_the_member_clears_the_payer_but_keeps_the_membership(): void
    {
        [$anna, $ben] = Member::factory()->count(2)->create()->all();
        $household = $this->service->create([$anna->id, $ben->id], $this->family->id, null);
        $this->service->setPayer($household, $anna->fresh());

        $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->patchJson("/members/{$anna->id}/field", ['field' => 'left_at', 'value' => '2026-11-01'])
            ->assertOk();

        $this->assertNull($household->fresh()?->payer_member_id);
        $this->assertSame($household->id, $anna->fresh()?->household_id);
        $this->assertSame('ausgetreten', $anna->fresh()?->status);
    }

    #[Test]
    public function anonymising_a_member_on_the_page_removes_the_household_link_in_the_same_snapshot(): void
    {
        $anna = Member::factory()->create(['status' => 'ausgetreten', 'left_at' => '2020-01-01']);
        $ben = Member::factory()->create();
        $household = $this->service->create([$ben->id], $this->family->id, null);
        $anna->forceFill(['household_id' => $household->id])->save();

        $this->actingAs(User::factory()->create(['status' => 'admin']))
            ->post("/members/{$anna->id}/anonymize", ['confirmation' => 'ANONYMISIEREN'])
            ->assertRedirect();

        $this->assertNull($anna->fresh()?->household_id);
        $change = MemberChange::where('member_id', $anna->id)->latest('id')->first();
        $this->assertSame('anonymized', $change?->action);
        $this->assertSame($household->id, $change?->before['household_id']);
        $this->assertNull($change?->after['household_id']);
    }

    #[Test]
    public function the_current_fiscal_year_contains_today_else_is_the_last_one(): void
    {
        FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $this->assertSame($this->year->id, $this->service->currentFiscalYear()?->id);

        $this->travelTo('2028-02-01');
        $this->assertSame($this->year->id, $this->service->currentFiscalYear()?->id);
    }
}
