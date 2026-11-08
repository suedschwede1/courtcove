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
use App\Modules\Members\Models\Payment;
use App\Modules\Members\Support\DuesSummary;
use App\Modules\Members\Support\HouseholdService;
use App\Modules\Members\Support\PaymentCanceller;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Zahlung stornieren": a payment is never deleted; it gets a storno (who, when, optional reason), its dues are
 * released (no longer count as paid anywhere, set to "ausstehend", "Bezahlt am" cleared), all in one
 * transaction under the fiscal-year row lock; refused in a closed year; logged in the member change log.
 */
class PaymentCancelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    private FiscalYear $year;

    private FiscalYear $nextYear;

    private FeeCategory $category;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-08 10:00:00');
        $this->admin = User::factory()->create(['status' => 'admin']);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->nextYear = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        $this->category = FeeCategory::create(['name' => 'Vollmitglied']);
        $this->member = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster', 'status' => 'aktiv', 'email' => 'anna@example.test']);
    }

    private function due(FiscalYear $year, float $amount, array $extra = []): MemberDue
    {
        return MemberDue::create(array_merge([
            'member_id' => $this->member->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $this->category->id,
            'amount' => $amount, 'status' => 'ausstehend',
        ], $extra));
    }

    /** A payment through the real form ("Zahlung erfassen") covering the given dues. */
    private function pay(array $dueIds, float $amount, array $householdDueIds = []): Payment
    {
        $payload = ['amount' => $amount, 'paid_at' => '2026-03-01', 'note' => 'Überweisung'];
        if ($dueIds !== []) {
            $payload['member_due_ids'] = $dueIds;
        }
        if ($householdDueIds !== []) {
            $payload['household_due_ids'] = $householdDueIds;
        }
        $this->actingAs($this->admin)->post(route('members.payments.store', $this->member), $payload)->assertSessionHasNoErrors();

        return Payment::query()->latest('id')->firstOrFail();
    }

    private function cancel(Payment $payment, ?string $reason = 'Doppelt gebucht', ?Member $via = null): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('members.payments.cancel', ['member' => ($via ?? $this->member)->id, 'payment' => $payment->id]), ['reason' => $reason]);
    }

    #[Test]
    public function a_cancel_keeps_the_payment_marks_it_cancelled_and_releases_all_its_dues(): void
    {
        $a = $this->due($this->year, 120);
        $b = $this->due($this->nextYear, 130);
        $payment = $this->pay([$a->id, $b->id], 250);

        $this->cancel($payment)->assertRedirect(route('members.edit', $this->member))->assertSessionHas('status', __('members::admin.payments.cancelled'));

        $payment->refresh();
        $this->assertNotNull($payment->cancelled_at);
        $this->assertSame($this->admin->uid, $payment->cancelled_by);
        $this->assertSame('Doppelt gebucht', $payment->cancel_reason);
        $this->assertTrue($payment->isCancelled());
        $this->assertSame(1, Payment::query()->count(), 'never deleted');
        $this->assertSame(2, DB::table('payment_dues')->where('payment_id', $payment->id)->count(), 'the history of covered dues stays');
        foreach ([$a, $b] as $due) {
            $due->refresh();
            $this->assertSame(['ausstehend', null], [$due->status, $due->bezahlt_am]);
            $this->assertFalse($due->isPaid());
        }
    }

    #[Test]
    public function a_cancelled_payment_counts_nowhere_as_paid(): void
    {
        $due = $this->due($this->year, 120, ['teilbetrag' => 20]);
        $payment = $this->pay([$due->id], 120);
        $this->assertSame(0, MemberDue::query()->open()->count());
        $this->assertSame('2026-12-31', $this->member->fresh()->paidUntil()?->toDateString());

        $this->cancel($payment);

        $due->refresh();
        $this->assertSame(1, MemberDue::query()->open()->count(), 'scopeOpen');
        $this->assertSame(0, MemberDue::query()->paid()->count(), 'scopePaid');
        $this->assertSame('100.00', $due->outstandingAmount(), 'amount minus Teilbetrag, payment no longer counted');
        $this->assertFalse(MemberDue::query()->withExists('payments')->find($due->id)->payments_exists);
        $this->assertNull($this->member->fresh()->paidUntil(), 'bezahlt bis');
        $this->assertSame(1, Member::query()->withOpenDues()->count(), 'Beitrag offen');
        $summary = DuesSummary::forFiscalYear($this->year);
        $this->assertSame([1, 0], [$summary->openCount(), $summary->paidCount()], 'DuesSummary');
        $this->assertNull($due->linkedPaymentText(), 'no active payment hint');

        $this->actingAs($this->admin)->get('/members?filter=offen')->assertOk()->assertSee('Muster');
    }

    #[Test]
    public function the_released_dues_can_be_paid_again(): void
    {
        $due = $this->due($this->year, 120);
        $first = $this->pay([$due->id], 120);
        $this->cancel($first);

        $second = $this->pay([$due->id], 120);

        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue($due->fresh()->isPaid());
        $this->assertSame('bezahlt', $due->fresh()->status);
    }

    #[Test]
    public function a_closed_year_refuses_the_cancel_and_changes_nothing(): void
    {
        $due = $this->due($this->year, 120);
        $payment = $this->pay([$due->id], 120);
        $this->year->close($this->admin->uid);

        $this->cancel($payment)->assertRedirect()->assertSessionHasErrors('payment');

        $this->assertNull($payment->fresh()->cancelled_at);
        $this->assertSame('bezahlt', $due->fresh()->status);
        $this->assertSame(0, MemberChange::query()->where('action', 'payment_cancel')->count());
    }

    #[Test]
    public function without_the_members_right_the_cancel_is_refused(): void
    {
        $due = $this->due($this->year, 120);
        $payment = $this->pay([$due->id], 120);
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.booking']);

        // Since 3d0a006 the Members area checks the stored right only (admin mode counts for calendar/bookings only).
        $this->actingAs($assist)->post(route('members.payments.cancel', ['member' => $this->member->id, 'payment' => $payment->id]))->assertForbidden();
        $this->post('/logout');
        $this->post(route('members.payments.cancel', ['member' => $this->member->id, 'payment' => $payment->id]))->assertRedirect(route('login'));

        $this->assertNull($payment->fresh()->cancelled_at);
    }

    #[Test]
    public function a_second_cancel_changes_nothing(): void
    {
        $due = $this->due($this->year, 120);
        $payment = $this->pay([$due->id], 120);
        $this->cancel($payment);
        $cancelledAt = $payment->fresh()->cancelled_at;
        $this->travel(5)->minutes();

        $this->cancel($payment, 'Noch einmal')->assertRedirect()->assertSessionHas('status', __('members::admin.payments.already_cancelled'));

        $this->assertEquals($cancelledAt, $payment->fresh()->cancelled_at);
        $this->assertSame('Doppelt gebucht', $payment->fresh()->cancel_reason);
        $this->assertSame(1, MemberChange::query()->where('action', 'payment_cancel')->count());
    }

    #[Test]
    public function the_cancel_is_logged_on_the_member(): void
    {
        $due = $this->due($this->year, 120);
        $payment = $this->pay([$due->id], 120);

        $this->cancel($payment);

        $change = MemberChange::query()->where('member_id', $this->member->id)->where('action', 'payment_cancel')->sole();
        $this->assertSame($this->admin->uid, $change->admin_user_id);
        $this->assertSame($payment->id, $change->after['payment_cancelled']['id']);
        $this->actingAs($this->admin)->get(route('members.edit', $this->member))
            ->assertSeeText(__('members::admin.history.payment_cancelled', ['date' => '01.03.2026', 'amount' => '120,00 €', 'reason' => ' (Doppelt gebucht)']));
    }

    #[Test]
    public function a_payment_of_another_member_cannot_be_cancelled_through_this_member(): void
    {
        $due = $this->due($this->year, 120);
        $payment = $this->pay([$due->id], 120);
        $other = Member::factory()->create();

        $this->cancel($payment, null, $other)->assertNotFound();
        $this->assertNull($payment->fresh()->cancelled_at);
    }

    #[Test]
    public function a_household_payment_cancel_releases_the_household_due_and_logs_payer_and_covered_members(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $ben = Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']);
        $household = app(HouseholdService::class)->create([$this->member->id, $ben->id], $family->id, 'Familie Muster');
        $hdue = HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $family->id, 'amount' => 235, 'status' => 'ausstehend']);
        $hdue->coveredMembers()->attach([$this->member->id => ['fiscal_year_id' => $this->year->id], $ben->id => ['fiscal_year_id' => $this->year->id]]);
        $payment = $this->pay([], 235, [$hdue->id]);
        $this->assertTrue($hdue->fresh()->isPaid());

        // Visible (and cancellable) also on the covered member's page.
        $this->actingAs($this->admin)->get(route('members.edit', $ben))->assertOk()->assertSee('data-payment="'.$payment->id.'"', false);
        $this->cancel($payment, null, $ben)->assertRedirect(route('members.edit', $ben));

        $hdue->refresh();
        $this->assertSame(['ausstehend', null], [$hdue->status, $hdue->bezahlt_am]);
        $this->assertFalse($hdue->isPaid());
        $this->assertSame(1, HouseholdDue::query()->open()->count());
        $this->assertSame(1, DB::table('household_due_payment')->where('payment_id', $payment->id)->count());
        $logged = MemberChange::query()->where('action', 'payment_cancel')->pluck('member_id')->sort()->values()->all();
        $this->assertSame(collect([$this->member->id, $ben->id])->sort()->values()->all(), $logged);
    }

    // --- member page ----------------------------------------------------------------------------------

    #[Test]
    public function the_member_page_lists_payments_with_cancel_action_and_cancelled_state(): void
    {
        $a = $this->due($this->year, 120);
        $b = $this->due($this->nextYear, 130);
        $payment = $this->pay([$a->id, $b->id], 250);

        $html = $this->actingAs($this->admin)->get(route('members.edit', $this->member))->assertOk()
            ->assertSeeText(__('members::admin.payments.list_title'))
            ->assertSee('data-payment="'.$payment->id.'"', false)
            ->assertSeeText('01.03.2026')
            ->assertSeeText('250,00 €')
            ->assertSeeText('2026')
            ->assertSee('action="'.route('members.payments.cancel', ['member' => $this->member->id, 'payment' => $payment->id]).'"', false)
            ->getContent();
        $this->assertStringContainsString('id="payment-cancel"', $html, 'confirmation modal');

        $this->cancel($payment);

        $this->actingAs($this->admin)->get(route('members.edit', $this->member))->assertOk()
            ->assertSeeText(__('members::admin.payments.status_cancelled'))
            ->assertSeeText('Doppelt gebucht')
            ->assertDontSee('action="'.route('members.payments.cancel', ['member' => $this->member->id, 'payment' => $payment->id]).'"', false);
    }

    #[Test]
    public function the_reset_warning_points_to_the_cancel_action(): void
    {
        $this->assertStringContainsString(__('members::admin.payments.cancel'), __('members::admin.dues.reset_payment_hint', ['date' => 'x', 'amount' => 'y']));
        $this->assertStringContainsString(__('members::admin.payments.cancel', [], 'en'), __('members::admin.dues.reset_payment_hint', ['date' => 'x', 'amount' => 'y'], 'en'));
    }

    #[Test]
    public function invalid_reason_input_is_rejected_without_a_500(): void
    {
        $due = $this->due($this->year, 120);
        $payment = $this->pay([$due->id], 120);

        $this->actingAs($this->admin)->post(route('members.payments.cancel', ['member' => $this->member->id, 'payment' => $payment->id]), ['reason' => ['x']])
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post(route('members.payments.cancel', ['member' => $this->member->id, 'payment' => $payment->id]), ['reason' => str_repeat('a', 501)])
            ->assertSessionHasErrors('reason');
        $this->assertNull($payment->fresh()->cancelled_at);
    }

    #[Test]
    public function the_change_log_action_fits_the_column(): void
    {
        // member_changes.action is varchar(16); SQLite does not enforce it, MySQL does (caught by the lock test).
        $this->assertLessThanOrEqual(16, strlen(PaymentCanceller::CHANGE_ACTION));
    }

    #[Test]
    public function the_texts_exist_in_german_and_english(): void
    {
        foreach (['payments.list_title', 'payments.cancel', 'payments.cancel_title', 'payments.cancel_text', 'payments.cancel_reason', 'payments.cancel_confirm',
            'payments.cancelled', 'payments.already_cancelled', 'payments.status_cancelled', 'payments.status_active', 'payments.cancelled_by', 'payments.covered', 'payments.none',
            'history.payment_cancelled'] as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertNotSame('members::admin.'.$key, __('members::admin.'.$key, [], $locale), $locale.' '.$key);
            }
        }
    }
}
