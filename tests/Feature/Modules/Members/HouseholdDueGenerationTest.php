<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Generation with households: one household due per household and year from two billable members
 * (aktiv + ruhend), never a household due and an own due for the same member, and every member that
 * gets no due is reported with a reason instead of being skipped silently.
 */
class HouseholdDueGenerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $family;

    private FeeCategory $adults;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->admin = User::factory()->create(['status' => 'admin']);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'faellig_am' => '2026-03-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $this->adults = FeeCategory::create(['name' => 'Erwachsene']);
        FeeCategoryRate::create(['fee_category_id' => $this->family->id, 'fiscal_year_id' => $this->year->id, 'amount' => 235]);
        FeeCategoryRate::create(['fee_category_id' => $this->adults->id, 'fiscal_year_id' => $this->year->id, 'amount' => 150]);
    }

    /** @param  list<string>  $statuses */
    private function household(array $statuses, ?FeeCategory $memberCategory = null, ?FeeCategory $tariff = null): MemberHousehold
    {
        $names = ['Anna', 'Ben', 'Clara', 'Dora'];
        $household = MemberHousehold::create(['fee_category_id' => ($tariff ?? $this->family)->id]);
        foreach ($statuses as $i => $status) {
            Member::factory()->create([
                'firstname' => $names[$i], 'lastname' => 'Muster', 'status' => $status,
                'left_at' => $status === 'ausgetreten' ? '2025-12-31' : null,
                'fee_category_id' => $memberCategory?->id,
            ])->forceFill(['household_id' => $household->id])->save();
        }

        return $household->load(['members' => fn ($q) => $q->orderBy('id')]);
    }

    private function generate(): TestResponse
    {
        return $this->actingAs($this->admin)->post('/members/dues/generate', ['fiscal_year_id' => $this->year->id])->assertRedirect();
    }

    #[Test]
    public function one_household_due_per_household_and_year_from_two_billable_members(): void
    {
        $household = $this->household(['aktiv', 'ruhend'], $this->adults);

        $this->generate()->assertSessionHas('dues_report', fn (array $r) => $r['createdHouseholds'] === 1 && $r['createdMembers'] === 0);

        $due = HouseholdDue::where('household_id', $household->id)->sole();
        $this->assertSame('235.00', $due->amount);
        $this->assertSame('ausstehend', $due->status);
        $this->assertSame('2026-03-31', $due->faellig_am?->toDateString());
        $this->assertSame(2, $due->coveredMembers()->count());
        $this->assertSame(0, MemberDue::where('fiscal_year_id', $this->year->id)->count());
    }

    #[Test]
    public function a_household_with_one_billable_member_is_reported_and_billed_individually(): void
    {
        $this->household(['aktiv', 'ausgetreten'], memberCategory: $this->adults);

        $this->generate()->assertSessionHas('dues_report', fn (array $r) => count($r['skipped']['household_too_small']) === 1);

        $this->assertSame(0, HouseholdDue::count());
        $this->assertSame('150.00', MemberDue::sole()->amount);
    }

    #[Test]
    public function generation_never_covers_a_member_with_an_own_due_and_never_bills_a_covered_member(): void
    {
        $household = $this->household(['aktiv', 'aktiv', 'aktiv'], $this->adults);
        [$anna, $ben, $clara] = $household->members->all();
        MemberDue::create(['member_id' => $anna->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => 150, 'status' => 'ausstehend']);
        MemberDue::create(['member_id' => $ben->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => 150, 'status' => 'bezahlt']);

        $this->generate()->assertSessionHas('dues_report', fn (array $r) => count($r['skipped']['own_due_replaceable']) === 1 && count($r['skipped']['own_due_paid']) === 1);
        $this->generate(); // second run: idempotent

        $due = HouseholdDue::sole();
        $this->assertSame([$clara->id], $due->coveredMembers()->pluck('members.id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(2, MemberDue::where('fiscal_year_id', $this->year->id)->count());
        $this->assertDatabaseMissing('member_dues', ['member_id' => $clara->id]);
    }

    #[Test]
    public function no_household_due_is_created_when_every_billable_member_already_has_an_own_due(): void
    {
        $household = $this->household(['aktiv', 'aktiv'], $this->adults);
        foreach ($household->members as $member) {
            MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => 150]);
        }

        $this->generate()->assertSessionHas('dues_report', fn (array $r) => count($r['skipped']['own_due_replaceable']) === 2);

        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function the_current_fiscal_year_is_generated_too(): void
    {
        $this->household(['aktiv', 'aktiv']);
        $this->generate();
        $this->assertSame(1, HouseholdDue::where('fiscal_year_id', $this->year->id)->count());
    }

    #[Test]
    public function new_members_without_a_previous_due_use_their_member_category(): void
    {
        Member::factory()->create(['fee_category_id' => $this->adults->id]);
        $this->generate()->assertSessionHas('dues_report', fn (array $r) => $r['createdFromMemberCategory'] === 1);
        $this->assertSame('150.00', MemberDue::sole()->amount);
    }

    #[Test]
    public function skipped_members_are_reported_with_reasons_not_silently(): void
    {
        Member::factory()->create(['fee_category_id' => null]);
        $this->generate()
            ->assertSessionHas('status', __('members::admin.dues.generated', ['count' => 0]))
            ->assertSessionHas('dues_report', fn (array $r) => count($r['skipped']['no_category']) === 1);
        $this->actingAs($this->admin)->get('/members/dues?fiscal_year_id='.$this->year->id)
            ->assertSeeText(__('members::admin.dues.report.no_category', ['count' => 1]));
    }

    #[Test]
    public function a_household_without_rate_or_with_a_deactivated_tariff_is_reported_and_its_members_are_not_billed_individually(): void
    {
        $noRate = FeeCategory::create(['name' => 'Familie neu', 'billing_unit' => 'household']);
        $old = FeeCategory::create(['name' => 'Familie alt', 'billing_unit' => 'household']);
        FeeCategoryRate::create(['fee_category_id' => $old->id, 'fiscal_year_id' => $this->year->id, 'amount' => 200]);
        $old->deactivate();
        $this->household(['aktiv', 'aktiv'], $this->adults, $noRate);
        $this->household(['aktiv', 'aktiv'], $this->adults, $old);

        $this->generate()->assertSessionHas('dues_report', fn (array $r) => count($r['skipped']['household_no_rate']) === 1
            && count($r['skipped']['household_deactivated']) === 1);

        $this->assertSame(0, HouseholdDue::count());
        $this->assertSame(0, MemberDue::count());
    }

    #[Test]
    public function a_grouping_household_without_tariff_bills_its_members_individually(): void
    {
        $household = MemberHousehold::create([]);
        $anna = Member::factory()->create(['fee_category_id' => $this->adults->id]);
        $anna->forceFill(['household_id' => $household->id])->save();

        $this->generate();

        $this->assertSame(0, HouseholdDue::count());
        $this->assertSame($anna->id, MemberDue::sole()->member_id);
    }

    #[Test]
    public function an_existing_household_due_covers_newly_billable_members_on_a_second_run(): void
    {
        $household = $this->household(['aktiv', 'aktiv']);
        $this->generate();
        $dora = Member::factory()->create(['firstname' => 'Dora', 'lastname' => 'Muster']);
        $dora->forceFill(['household_id' => $household->id])->save();

        $this->generate()->assertSessionHas('dues_report', fn (array $r) => $r['createdHouseholds'] === 0);

        $this->assertSame(1, HouseholdDue::count());
        $this->assertSame(3, HouseholdDue::sole()->coveredMembers()->count());
    }

    #[Test]
    public function a_closed_year_is_refused_as_before(): void
    {
        $this->household(['aktiv', 'aktiv']);
        $this->year->close($this->admin->uid);

        $this->actingAs($this->admin)->post('/members/dues/generate', ['fiscal_year_id' => $this->year->id])->assertSessionHasErrors('fiscal_year_id');
        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function the_report_renders_names_with_links(): void
    {
        $household = $this->household(['aktiv'], $this->adults);

        $response = $this->generate();
        $this->followRedirects($response)
            ->assertSeeText(__('members::admin.dues.report.household_too_small', ['count' => 1]))
            ->assertSee(route('members.households.show', $household->id), false)
            ->assertSeeText(__('members::admin.dues.report.summary', ['year' => '2026', 'total' => 1, 'members' => 1, 'households' => 0]));
    }

    #[Test]
    public function the_service_and_generator_together_never_double_bill_a_joiner(): void
    {
        $household = $this->household(['aktiv', 'aktiv'], $this->adults);
        $this->generate();
        $emil = Member::factory()->create(['firstname' => 'Emil', 'lastname' => 'Probe', 'fee_category_id' => $this->adults->id]);
        app(HouseholdService::class)->addMember($household, $emil);

        $this->generate();

        $this->assertSame(0, MemberDue::where('member_id', $emil->id)->count());
        $this->assertTrue(HouseholdDue::sole()->coveredMembers()->whereKey($emil->id)->exists());
    }
}
