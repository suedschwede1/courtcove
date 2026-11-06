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
use Laravel\Mcp\Request as McpRequest;
use Laravel\Sanctum\Sanctum;
use Modules\Api\Mcp\Tools\CreateDueTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A household due entered by hand (rate fallback, one per household and year, only with an active
 * per-household tariff and 2 billable members), and no individual due for a member the household
 * covers in that year (manual form, API, MCP tool, paid-through).
 */
class HouseholdManualDueTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $family;

    private FeeCategory $adults;

    private MemberHousehold $household;

    private Member $anna;

    private Member $ben;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $this->adults = FeeCategory::create(['name' => 'Erwachsene']);
        FeeCategoryRate::create(['fee_category_id' => $this->family->id, 'fiscal_year_id' => $this->year->id, 'amount' => 235]);
        FeeCategoryRate::create(['fee_category_id' => $this->adults->id, 'fiscal_year_id' => $this->year->id, 'amount' => 150]);
        $this->anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $this->ben = Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']);
        $this->household = app(HouseholdService::class)->create([$this->anna->id, $this->ben->id], $this->family->id, 'Familie Muster');
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function covered(): HouseholdDue
    {
        $due = HouseholdDue::create(['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235]);
        $due->coveredMembers()->attach([$this->anna->id => ['fiscal_year_id' => $this->year->id], $this->ben->id => ['fiscal_year_id' => $this->year->id]]);

        return $due;
    }

    #[Test]
    public function a_household_due_is_created_with_the_rate_and_covers_billable_members(): void
    {
        $this->actingAs($this->admin())->post('/members/household-dues', ['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id])
            ->assertRedirect(route('members.households.show', $this->household->id))
            ->assertSessionHasNoErrors();

        $due = HouseholdDue::sole();
        $this->assertSame('235.00', $due->amount);
        $this->assertSame('ausstehend', $due->status);
        $this->assertSame(2, $due->coveredMembers()->count());
    }

    #[Test]
    public function an_explicit_amount_wins_over_the_rate_and_a_member_with_an_own_due_is_not_covered(): void
    {
        MemberDue::create(['member_id' => $this->ben->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => 150]);

        $this->actingAs($this->admin())->post('/members/household-dues', ['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id, 'amount' => '200.50'])
            ->assertSessionHasNoErrors();

        $due = HouseholdDue::sole();
        $this->assertSame('200.50', $due->amount);
        $this->assertSame([$this->anna->id], $due->coveredMembers()->pluck('members.id')->map(fn ($id) => (int) $id)->all());
    }

    #[Test]
    public function a_second_household_due_for_the_same_year_is_rejected(): void
    {
        $admin = $this->admin();
        $payload = ['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id];
        $this->actingAs($admin)->post('/members/household-dues', $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/members/household-dues', $payload)->assertSessionHasErrors('fiscal_year_id');

        $this->assertSame(1, HouseholdDue::count());
    }

    #[Test]
    public function without_rate_tariff_or_two_billable_members_no_household_due_is_created(): void
    {
        $admin = $this->admin();
        $next = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        $this->actingAs($admin)->post('/members/household-dues', ['household_id' => $this->household->id, 'fiscal_year_id' => $next->id])
            ->assertSessionHasErrors('amount');

        $grouping = app(HouseholdService::class)->create([], null, null);
        $this->actingAs($admin)->post('/members/household-dues', ['household_id' => $grouping->id, 'fiscal_year_id' => $this->year->id])
            ->assertSessionHasErrors('household_id');

        $small = app(HouseholdService::class)->create([Member::factory()->create()->id], $this->family->id, null);
        $this->actingAs($admin)->post('/members/household-dues', ['household_id' => $small->id, 'fiscal_year_id' => $this->year->id])
            ->assertSessionHasErrors('household_id');

        $this->family->deactivate();
        $this->actingAs($admin)->post('/members/household-dues', ['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id])
            ->assertSessionHasErrors('household_id');

        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function a_closed_year_dissolved_household_or_bad_input_is_refused(): void
    {
        $admin = $this->admin();
        $old = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $old->close(1);
        $this->actingAs($admin)->post('/members/household-dues', ['household_id' => $this->household->id, 'fiscal_year_id' => $old->id])
            ->assertSessionHasErrors('fiscal_year_id');
        $this->actingAs($admin)->post('/members/household-dues', ['household_id' => ['x'], 'fiscal_year_id' => [$this->year->id], 'amount' => ['1']])
            ->assertSessionHasErrors(['household_id', 'fiscal_year_id', 'amount']);
        $this->actingAs($admin)->post('/members/household-dues', ['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id, 'amount' => '1000000'])
            ->assertSessionHasErrors('amount');

        app(HouseholdService::class)->dissolve($this->household);
        $this->actingAs($admin)->post('/members/household-dues', ['household_id' => $this->household->id, 'fiscal_year_id' => $this->year->id])
            ->assertSessionHasErrors('household_id');

        $this->assertSame(0, HouseholdDue::count());
    }

    #[Test]
    public function an_individual_due_is_rejected_for_a_covered_member(): void
    {
        $this->covered();

        $this->actingAs($this->admin())->post('/members/dues', ['member_id' => $this->anna->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id])
            ->assertSessionHasErrors(['member_id' => __('members::admin.dues.covered_by_household', ['year' => '2026', 'household' => 'Familie Muster'])]);

        $this->assertSame(0, MemberDue::count());
    }

    #[Test]
    public function the_api_and_the_mcp_tool_refuse_an_individual_due_for_a_covered_member(): void
    {
        $this->covered();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/dues', ['member_id' => $this->anna->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id])->assertStatus(422);
        $result = app(CreateDueTool::class)->handle(new McpRequest(['member_id' => $this->ben->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id]));
        $this->assertTrue($result->isError());

        $this->assertSame(0, MemberDue::count());
    }

    #[Test]
    public function paid_through_skips_years_in_which_the_member_is_covered(): void
    {
        $old = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $own = MemberDue::create(['member_id' => $this->anna->id, 'fiscal_year_id' => $old->id, 'fee_category_id' => $this->adults->id, 'amount' => 150]);
        $this->covered();

        $this->actingAs($this->admin())->patchJson("/members/dues/{$own->id}/paid-through", ['fiscal_year_id' => $this->year->id])->assertOk();

        $this->assertSame(0, MemberDue::where('member_id', $this->anna->id)->where('fiscal_year_id', $this->year->id)->count());
        $this->assertSame('bezahlt', $own->fresh()?->status);
    }

    #[Test]
    public function the_household_page_offers_the_manual_form_for_open_years(): void
    {
        $old = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $old->close(1);

        $this->actingAs($this->admin())->get("/members/households/{$this->household->id}")
            ->assertOk()
            ->assertSeeText(__('members::admin.households.due_create'))
            ->assertSee('action="'.route('members.household-dues.store').'"', false)
            ->assertSee('>2026</option>', false)
            ->assertDontSee('>2025</option>', false);
    }

    #[Test]
    public function a_joiner_is_covered_under_the_year_lock_only_while_the_year_is_open(): void
    {
        $due = $this->covered();
        $clara = Member::factory()->create();

        app(HouseholdService::class)->addMember($this->household, $clara);

        $this->assertTrue($due->coveredMembers()->whereKey($clara->id)->exists());
    }
}
