<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use App\Modules\Members\Support\HouseholdService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A paid due can be reset to "not paid" on the member page (own dues and the covering household dues), with
 * the same rules as the dues list: closed year locked, members right required, "Bezahlt am" cleared. Linked
 * payments stay untouched; the answer and the row carry a non-destructive hint about them.
 */
class MemberPageDueResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-08 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->member = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function paidDue(): MemberDue
    {
        $category = FeeCategory::create(['name' => 'Vollmitglied']);

        return MemberDue::create([
            'member_id' => $this->member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $category->id,
            'amount' => 120, 'status' => 'bezahlt', 'bezahlt_am' => '2026-03-01',
        ]);
    }

    private function householdDue(): HouseholdDue
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $household = app(HouseholdService::class)->create([$this->member->id], $family->id, 'Familie Muster');
        $due = HouseholdDue::create([
            'household_id' => $household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $family->id,
            'amount' => 235, 'status' => 'bezahlt', 'bezahlt_am' => '2026-03-01',
        ]);
        $due->coveredMembers()->attach($this->member->id, ['fiscal_year_id' => $this->year->id]);

        return $due;
    }

    private function payment(float $amount): Payment
    {
        return Payment::create(['member_id' => $this->member->id, 'amount' => $amount, 'paid_at' => '2026-03-01', 'note' => 'Überweisung']);
    }

    // --- own dues -------------------------------------------------------------------------------------

    #[Test]
    public function the_member_page_offers_the_status_select_for_a_paid_own_due(): void
    {
        $due = $this->paidDue();

        $this->actingAs($this->admin())->get("/members/{$this->member->id}/edit")->assertOk()
            ->assertSee("saveDueField({$due->id}, 'status'", false);
    }

    #[Test]
    public function a_paid_own_due_is_reset_and_its_paid_date_cleared(): void
    {
        $due = $this->paidDue();

        $this->actingAs($this->admin())->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'ausstehend'])
            ->assertOk()->assertJsonPath('value', 'ausstehend')->assertJsonPath('bezahlt_am', null)->assertJsonMissingPath('warning');

        $this->assertSame(['ausstehend', null], [$due->fresh()->status, $due->fresh()->bezahlt_am]);
    }

    #[Test]
    public function a_reset_keeps_linked_payments_and_answers_with_a_hint(): void
    {
        $due = $this->paidDue();
        $payment = $this->payment(120);
        $payment->dues()->attach($due->id);

        $response = $this->actingAs($this->admin())->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'ausstehend'])->assertOk();

        $this->assertSame(__('members::admin.dues.reset_payment_hint', ['date' => '01.03.2026', 'amount' => '120,00 €']), $response->json('warning'));
        $this->assertSame('ausstehend', $due->fresh()->status);
        $this->assertSame(1, Payment::query()->count(), 'the payment stays');
        $this->assertSame(1, DB::table('payment_dues')->where('member_due_id', $due->id)->count(), 'the link stays');
    }

    #[Test]
    public function the_member_page_shows_the_linked_payment_next_to_the_due(): void
    {
        $due = $this->paidDue();
        $this->payment(120)->dues()->attach($due->id);

        $this->actingAs($this->admin())->get("/members/{$this->member->id}/edit")->assertOk()
            ->assertSee('data-payment-hint="'.$due->id.'"', false)
            ->assertSeeText(__('members::admin.dues.linked_payment', ['date' => '01.03.2026', 'amount' => '120,00 €']));
    }

    #[Test]
    public function a_closed_year_refuses_the_reset(): void
    {
        $due = $this->paidDue();
        $this->year->close(1);

        $this->actingAs($this->admin())->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'ausstehend'])
            ->assertStatus(422)->assertJsonPath('message', $this->year->fresh()->lockedMessage());
        $this->assertSame('bezahlt', $due->fresh()->status);
    }

    #[Test]
    public function without_the_members_right_the_reset_is_refused(): void
    {
        $due = $this->paidDue();
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.booking']);

        $this->actingAs($assist)->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'ausstehend'])->assertForbidden();
        $this->assertSame('bezahlt', $due->fresh()->status);
    }

    // --- household dues -------------------------------------------------------------------------------

    #[Test]
    public function the_member_page_offers_the_status_select_for_a_covering_household_due(): void
    {
        $due = $this->householdDue();

        $this->actingAs($this->admin())->get("/members/{$this->member->id}/edit")->assertOk()
            ->assertSee('data-household-due="'.$due->id.'"', false)
            ->assertSee("saveHouseholdDueField({$due->id}, 'status'", false);
    }

    #[Test]
    public function a_paid_household_due_is_reset_with_the_payment_kept(): void
    {
        $due = $this->householdDue();
        $this->payment(235)->householdDues()->attach($due->id);

        $response = $this->actingAs($this->admin())->patchJson("/members/household-dues/{$due->id}/field", ['field' => 'status', 'value' => 'ausstehend'])->assertOk();

        $response->assertJsonPath('bezahlt_am', null);
        $this->assertSame(__('members::admin.dues.reset_payment_hint', ['date' => '01.03.2026', 'amount' => '235,00 €']), $response->json('warning'));
        $this->assertSame(['ausstehend', null], [$due->fresh()->status, $due->fresh()->bezahlt_am]);
        $this->assertSame(1, DB::table('household_due_payment')->where('household_due_id', $due->id)->count());
    }

    #[Test]
    public function a_household_due_of_a_closed_year_stays_read_only_on_the_member_page(): void
    {
        $due = $this->householdDue();
        $this->year->close(1);

        $this->actingAs($this->admin())->get("/members/{$this->member->id}/edit")->assertOk()
            ->assertSee('data-household-due="'.$due->id.'"', false)
            ->assertDontSee("saveHouseholdDueField({$due->id}", false);
        $this->actingAs($this->admin())->patchJson("/members/household-dues/{$due->id}/field", ['field' => 'status', 'value' => 'ausstehend'])->assertStatus(422);
    }

    #[Test]
    public function the_texts_exist_in_german_and_english(): void
    {
        foreach (['reset_payment_hint', 'linked_payment'] as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertNotSame('members::admin.dues.'.$key, __('members::admin.dues.'.$key, [], $locale), $locale.' '.$key);
            }
        }
    }
}
