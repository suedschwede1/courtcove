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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Household dues on the Beiträge page: listed once with their members, edited inline, paid through and in bulk. */
class HouseholdDueStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $family;

    private FeeCategory $adults;

    private MemberHousehold $h;

    private Member $anna;

    private Member $ben;

    private HouseholdDue $due;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'faellig_am' => '2026-12-01']);
        $this->family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $this->adults = FeeCategory::create(['name' => 'Erwachsene']);
        FeeCategoryRate::create(['fee_category_id' => $this->family->id, 'fiscal_year_id' => $this->year->id, 'amount' => 235]);
        $this->anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
        $this->ben = Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']);
        $this->h = app(HouseholdService::class)->create([$this->anna->id, $this->ben->id], $this->family->id, 'Familie Muster');
        $this->due = HouseholdDue::create(['household_id' => $this->h->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->family->id, 'amount' => 235, 'status' => 'ausstehend', 'faellig_am' => '2026-12-01']);
        $this->due->coveredMembers()->attach([$this->anna->id => ['fiscal_year_id' => $this->year->id], $this->ben->id => ['fiscal_year_id' => $this->year->id]]);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function the_status_cell_updates_a_household_due(): void
    {
        $this->actingAs($this->admin())->patchJson("/members/household-dues/{$this->due->id}/field", ['field' => 'status', 'value' => 'bezahlt'])
            ->assertOk()->assertJson(['value' => 'bezahlt', 'bezahlt_am' => '2026-11-06']);

        $this->assertSame('bezahlt', $this->due->fresh()?->status);
        $this->assertSame('2026-11-06', $this->due->fresh()?->bezahlt_am?->toDateString());
    }

    #[Test]
    public function partial_amount_comment_and_paid_on_follow_the_member_due_rules(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->patchJson("/members/household-dues/{$this->due->id}/field", ['field' => 'teilbetrag', 'value' => '50'])->assertOk()->assertJson(['value' => '50,00 €']);
        $this->actingAs($admin)->patchJson("/members/household-dues/{$this->due->id}/field", ['field' => 'kommentar', 'value' => 'Rest im Dezember'])->assertOk();
        $this->actingAs($admin)->patchJson("/members/household-dues/{$this->due->id}/field", ['field' => 'bezahlt_am', 'value' => '2026-10-01'])->assertStatus(422);
        $this->actingAs($admin)->patchJson("/members/household-dues/{$this->due->id}/field", ['field' => 'amount', 'value' => '1'])->assertStatus(422);
        $this->actingAs($admin)->patchJson("/members/household-dues/{$this->due->id}/field", ['field' => 'status', 'value' => ['bezahlt']])->assertStatus(422);
        $this->actingAs($admin)->patchJson('/members/household-dues/999/field', ['field' => 'status', 'value' => 'bezahlt'])->assertNotFound();

        $due = $this->due->fresh();
        $this->assertSame('50.00', $due?->teilbetrag);
        $this->assertSame('Rest im Dezember', $due?->kommentar);
        $this->assertSame('235.00', $due?->amount);
    }

    #[Test]
    public function a_household_due_of_a_closed_year_is_locked(): void
    {
        $this->year->close(1);

        $this->actingAs($this->admin())->patchJson("/members/household-dues/{$this->due->id}/field", ['field' => 'status', 'value' => 'bezahlt'])
            ->assertStatus(422)->assertJson(['message' => $this->year->lockedMessage()]);
        $this->assertSame('ausstehend', $this->due->fresh()?->status);
    }

    #[Test]
    public function paid_through_creates_the_next_household_due_with_coverage(): void
    {
        $y2027 = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);

        $this->actingAs($this->admin())->patchJson("/members/household-dues/{$this->due->id}/paid-through", ['fiscal_year_id' => $y2027->id])->assertOk();

        $this->assertDatabaseHas('household_dues', ['household_id' => $this->h->id, 'fiscal_year_id' => $y2027->id, 'status' => 'bezahlt', 'bezahlt_bis_fiscal_year_id' => $y2027->id]);
        $this->assertSame(2, HouseholdDue::where('fiscal_year_id', $y2027->id)->sole()->coveredMembers()->count());
        $this->assertSame('bezahlt', $this->due->fresh()?->status);
    }

    #[Test]
    public function paid_through_into_a_closed_year_or_backwards_is_refused(): void
    {
        $old = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $y2027 = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        $admin = $this->admin();
        $this->actingAs($admin)->patchJson("/members/household-dues/{$this->due->id}/paid-through", ['fiscal_year_id' => $old->id])->assertStatus(422);
        $y2027->close(1);
        $this->actingAs($admin)->patchJson("/members/household-dues/{$this->due->id}/paid-through", ['fiscal_year_id' => $y2027->id])->assertStatus(422);

        $this->assertSame(1, HouseholdDue::count());
        $this->assertSame('ausstehend', $this->due->fresh()?->status);
    }

    #[Test]
    public function bulk_mark_paid_handles_member_and_household_dues_together(): void
    {
        $dora = Member::factory()->create(['firstname' => 'Dora', 'lastname' => 'Beispiel']);
        $memberDue = MemberDue::create(['member_id' => $dora->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->adults->id, 'amount' => 150]);

        $this->actingAs($this->admin())->post('/members/dues/bulk-mark-paid', ['due_ids' => [$memberDue->id], 'household_due_ids' => [$this->due->id]])
            ->assertSessionHas('status', __('members::admin.dues.bulk_marked_paid', ['count' => 2]));

        $this->assertSame('bezahlt', $memberDue->fresh()?->status);
        $this->assertSame('bezahlt', $this->due->fresh()?->status);
    }

    #[Test]
    public function bulk_mark_paid_accepts_household_dues_alone_and_refuses_an_empty_selection(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/members/dues/bulk-mark-paid', [])->assertSessionHasErrors('due_ids');
        $this->actingAs($admin)->post('/members/dues/bulk-mark-paid', ['household_due_ids' => ['x']])->assertSessionHasErrors('household_due_ids.0');
        $this->actingAs($admin)->post('/members/dues/bulk-mark-paid', ['household_due_ids' => [$this->due->id]])
            ->assertSessionHas('status', __('members::admin.dues.bulk_marked_paid', ['count' => 1]));

        $this->assertSame('bezahlt', $this->due->fresh()?->status);
    }

    #[Test]
    public function the_dues_page_lists_household_dues_once_with_their_members(): void
    {
        $this->actingAs($this->admin())->get('/members/dues?fiscal_year_id='.$this->year->id)
            ->assertOk()
            ->assertSeeText(__('members::admin.dues.household_card', ['year' => '2026', 'count' => 1]))
            ->assertSeeText('Familie Muster')
            ->assertSeeText('Anna M., Ben M.')
            ->assertSee('name="household_due_ids[]" value="'.$this->due->id.'"', false);
    }

    #[Test]
    public function search_finds_a_household_by_a_covered_member(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/members/dues?fiscal_year_id='.$this->year->id.'&search=Ben')->assertSeeText('Familie Muster');
        $this->actingAs($admin)->get('/members/dues?fiscal_year_id='.$this->year->id.'&search=Zeta')->assertDontSeeText('Familie Muster');
    }

    #[Test]
    public function the_status_filter_applies_to_household_dues(): void
    {
        $this->due->update(['status' => 'bezahlt']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/members/dues?fiscal_year_id='.$this->year->id)->assertDontSeeText('Familie Muster');
        $this->actingAs($admin)->get('/members/dues?fiscal_year_id='.$this->year->id.'&status=bezahlt')->assertSeeText('Familie Muster');
    }

    #[Test]
    public function the_outstanding_total_counts_the_household_due_once(): void
    {
        $this->actingAs($this->admin())->get('/members/dues?fiscal_year_id='.$this->year->id)
            ->assertViewHas('outstandingTotal', 235.0);
    }

    #[Test]
    public function closing_a_year_counts_open_household_dues_for_the_confirmation(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $this->actingAs($admin)->from("/members/fiscal-years/{$this->year->id}/edit")
            ->post("/members/fiscal-years/{$this->year->id}/close")
            ->assertSessionHasErrors(['confirm_open_dues' => __('members::admin.fiscal_years.close_confirm_required', ['count' => 1])]);

        $this->assertFalse($this->year->fresh()?->isClosed());
    }
}
