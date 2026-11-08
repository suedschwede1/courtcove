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
use App\Modules\Members\Support\BulkDuePayer;
use App\Modules\Members\Support\HouseholdService;
use App\Modules\Members\Support\PaymentCanceller;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bulk "Als bezahlt markieren" on the dues list: one real payment per due (as "Zahlung erfassen"), each due in its
 * own transaction under the fiscal-year row lock; already paid / closed-year / other-year rows are skipped and
 * reported; "all of the current filter" is resolved again on the server (across pages, capped).
 */
class DuesBulkPayTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    private FiscalYear $year;

    private FeeCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-08 10:00:00');
        $this->admin = User::factory()->create(['status' => 'admin']);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->category = FeeCategory::create(['name' => 'Vollmitglied']);
    }

    private function due(?Member $member = null, float $amount = 120, ?FiscalYear $year = null, array $extra = []): MemberDue
    {
        $member ??= Member::factory()->create();

        return MemberDue::create(array_merge([
            'member_id' => $member->id, 'fiscal_year_id' => ($year ?? $this->year)->id, 'fee_category_id' => $this->category->id,
            'amount' => $amount, 'status' => 'ausstehend',
        ], $extra));
    }

    /** @param array<string, mixed> $payload */
    private function bulk(array $payload): TestResponse
    {
        return $this->actingAs($this->admin)->from(route('members.dues.index', ['fiscal_year_id' => $this->year->id]))
            ->post(route('members.dues.bulk-pay'), array_merge(['mode' => 'selected', 'fiscal_year_id' => $this->year->id, 'paid_at' => '2026-11-01'], $payload));
    }

    #[Test]
    public function selected_dues_get_one_real_payment_each(): void
    {
        $a = $this->due(amount: 120);
        $b = $this->due(amount: 80);

        $this->bulk(['due_ids' => [$a->id, $b->id], 'note' => 'Sammelüberweisung'])->assertRedirect()
            ->assertSessionHas('status', __('members::admin.dues.bulk_pay_result', ['paid' => 2, 'skipped' => 0]));

        foreach ([[$a, '120.00'], [$b, '80.00']] as [$due, $amount]) {
            $due->refresh();
            $this->assertSame(['bezahlt', '2026-11-01'], [$due->status, $due->bezahlt_am?->toDateString()]);
            $payment = $due->payments()->sole();
            $this->assertSame([$due->member_id, $amount, '2026-11-01', 'Sammelüberweisung'], [$payment->member_id, (string) $payment->amount, $payment->paid_at->toDateString(), $payment->note]);
        }
        $this->assertSame(2, Payment::query()->count());
    }

    #[Test]
    public function paid_closed_year_and_unknown_rows_are_skipped_and_reported(): void
    {
        $open = $this->due();
        $paid = $this->due(extra: ['status' => 'bezahlt', 'bezahlt_am' => '2026-02-01']);
        $byPayment = $this->due();
        $p = Payment::create(['member_id' => $byPayment->member_id, 'amount' => 120, 'paid_at' => '2026-03-01']);
        $p->dues()->attach($byPayment->id);
        $otherYear = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $other = $this->due(year: $otherYear);
        $closedYear = FiscalYear::create(['name' => '2024', 'starts_on' => '2024-01-01', 'ends_on' => '2024-12-31']);
        $closed = $this->due(year: $closedYear);
        $closedYear->close($this->admin->uid);

        $this->bulk(['due_ids' => [$open->id, $paid->id, $byPayment->id, $other->id, $closed->id, 999999]])
            ->assertSessionHas('status', __('members::admin.dues.bulk_pay_result', ['paid' => 1, 'skipped' => 5]));

        $report = session('bulk_pay_report');
        $this->assertSame(1, $report['paid']);
        $reasons = collect($report['skipped'])->countBy('reason')->all();
        $this->assertSame(['already_paid' => 2, 'other_year' => 2, 'not_found' => 1], $reasons, 'a due of another (even closed) year never counts here');
        $this->assertSame('bezahlt', $open->fresh()->status);
        $this->assertSame(2, Payment::query()->count(), 'only the one new payment');
        $this->assertSame('ausstehend', $other->fresh()->status);
    }

    #[Test]
    public function a_closed_selected_year_refuses_every_row_but_nothing_breaks(): void
    {
        $a = $this->due();
        $this->year->close($this->admin->uid);

        $this->bulk(['due_ids' => [$a->id]])->assertSessionHas('status', __('members::admin.dues.bulk_pay_result', ['paid' => 0, 'skipped' => 1]));

        $this->assertSame('closed_year', session('bulk_pay_report')['skipped'][0]['reason']);
        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function all_of_the_current_filter_is_resolved_on_the_server_across_pages(): void
    {
        $dues = collect(range(1, 30))->map(fn (int $i) => $this->due(Member::factory()->create(['lastname' => 'Muster'.$i])));
        $alreadyPaid = $this->due(Member::factory()->create(['lastname' => 'Muster99']), extra: ['status' => 'bezahlt', 'bezahlt_am' => '2026-02-01']);
        $notMatching = $this->due(Member::factory()->create(['lastname' => 'Anders']));

        // The page shows 25 rows; "all of the filter" must pay all 30 open "Muster" dues, and ignore client ids.
        $this->actingAs($this->admin)->get(route('members.dues.index', ['fiscal_year_id' => $this->year->id, 'status' => 'offen', 'search' => 'Muster']))
            ->assertOk()->assertSee('data-payable-count="30"', false);

        $this->bulk(['mode' => 'filter', 'status' => 'offen', 'search' => 'Muster', 'due_ids' => [$notMatching->id]])
            ->assertSessionHas('status', __('members::admin.dues.bulk_pay_result', ['paid' => 30, 'skipped' => 0]));

        $this->assertSame(30, Payment::query()->count());
        $this->assertSame(0, MemberDue::query()->whereKey($dues->pluck('id'))->open()->count());
        $this->assertSame('ausstehend', $notMatching->fresh()->status);
        $this->assertSame(0, $alreadyPaid->payments()->count());
    }

    #[Test]
    public function the_filter_mode_takes_only_the_open_rows_of_a_wider_status_filter(): void
    {
        $open = $this->due();
        $paid = collect(range(1, 3))->map(fn () => $this->due(extra: ['status' => 'bezahlt', 'bezahlt_am' => '2026-02-01']));

        // Status "alle" lists paid rows too, but "Alle der Filterung" pays (and counts towards the limit) only open ones.
        $this->bulk(['mode' => 'filter', 'status' => 'alle'])
            ->assertSessionHas('status', __('members::admin.dues.bulk_pay_result', ['paid' => 1, 'skipped' => 0]));
        $this->assertSame(1, $open->payments()->count());
        $this->assertSame(0, $paid->sum(fn (MemberDue $due) => $due->payments()->count()));
    }

    #[Test]
    public function enter_in_the_bulk_bar_opens_the_confirmation_instead_of_submitting(): void
    {
        $this->due();
        $html = $this->actingAs($this->admin)->get(route('members.dues.index', ['fiscal_year_id' => $this->year->id]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<form[^>]*id="dues-bulk-form"[^>]*x-on:keydown\.enter=/s', $html);
        $this->assertStringContainsString('submitting || !confirming', $html);
    }

    #[Test]
    public function more_rows_than_the_limit_are_refused_with_a_message(): void
    {
        $limit = BulkDuePayer::LIMIT;
        $members = Member::factory()->count($limit + 1)->create();
        foreach ($members as $member) {
            $this->due($member);
        }

        $this->bulk(['mode' => 'filter', 'status' => 'offen'])
            ->assertSessionHasErrors(['bulk' => __('members::admin.dues.bulk_too_many', ['count' => $limit + 1, 'limit' => $limit])]);
        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function a_household_due_is_paid_once_however_often_it_is_selected(): void
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $anna = Member::factory()->create(['firstname' => 'Anna']);
        $ben = Member::factory()->create(['firstname' => 'Ben']);
        $household = app(HouseholdService::class)->create([$anna->id, $ben->id], $family->id, 'Familie Muster');
        $household->forceFill(['payer_member_id' => $ben->id])->save();
        $hdue = HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $family->id, 'amount' => 235, 'status' => 'ausstehend']);
        $hdue->coveredMembers()->attach([$anna->id => ['fiscal_year_id' => $this->year->id], $ben->id => ['fiscal_year_id' => $this->year->id]]);

        $this->bulk(['household_due_ids' => [$hdue->id, $hdue->id]])
            ->assertSessionHas('status', __('members::admin.dues.bulk_pay_result', ['paid' => 1, 'skipped' => 0]));

        $payment = $hdue->payments()->sole();
        $this->assertSame([$ben->id, '235.00'], [$payment->member_id, (string) $payment->amount], 'the household payer pays');
        $this->assertSame('bezahlt', $hdue->fresh()->status);

        // Also via "all of the filter": the household due once, no second payment.
        $this->bulk(['mode' => 'filter', 'status' => 'alle']);
        $this->assertSame(1, Payment::query()->count());
    }

    #[Test]
    public function connected_members_keep_their_own_dues_and_each_gets_its_own_payment(): void
    {
        $vollzahler = Member::factory()->create(['firstname' => 'Vera']);
        $anschluss = Member::factory()->create(['firstname' => 'Anton', 'connected_to_member_id' => $vollzahler->id]);
        $a = $this->due($vollzahler, 200);
        $b = $this->due($anschluss, 50);

        $this->bulk(['due_ids' => [$a->id, $b->id]]);

        $this->assertSame($vollzahler->id, $a->payments()->sole()->member_id);
        $this->assertSame($anschluss->id, $b->payments()->sole()->member_id);
    }

    #[Test]
    public function a_double_submit_does_not_pay_twice(): void
    {
        $a = $this->due();

        $this->bulk(['due_ids' => [$a->id]]);
        $this->bulk(['due_ids' => [$a->id]])->assertSessionHas('status', __('members::admin.dues.bulk_pay_result', ['paid' => 0, 'skipped' => 1]));

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('already_paid', session('bulk_pay_report')['skipped'][0]['reason']);
    }

    #[Test]
    public function a_bulk_payment_can_be_cancelled_like_a_single_one(): void
    {
        $a = $this->due();
        $this->bulk(['due_ids' => [$a->id]]);

        app(PaymentCanceller::class)->cancel($a->payments()->sole(), 'Falsch', $this->admin->uid);

        $this->assertFalse($a->fresh()->isPaid());
        $this->assertSame('ausstehend', $a->fresh()->status);
    }

    #[Test]
    public function the_paid_on_date_defaults_to_today_and_never_lies_in_the_future(): void
    {
        $a = $this->due();
        $this->bulk(['due_ids' => [$a->id], 'paid_at' => '2026-11-09'])->assertSessionHasErrors('paid_at');
        $this->assertSame(0, Payment::query()->count());

        $this->bulk(['due_ids' => [$a->id], 'paid_at' => null])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-08', $a->payments()->sole()->paid_at->toDateString());
    }

    #[Test]
    public function malformed_input_is_rejected_without_a_500(): void
    {
        $a = $this->due();

        foreach ([['mode' => 'everything'], ['paid_at' => ['2026-11-01']], ['due_ids' => 'x'], ['due_ids' => [['nested']]], ['note' => ['x']], ['search' => ['x'], 'mode' => 'filter'], ['note' => str_repeat('n', 256)]] as $override) {
            $this->bulk(array_merge(['due_ids' => [$a->id]], $override))->assertStatus(302)->assertSessionHasErrors();
        }
        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function only_the_members_right_may_bulk_pay(): void
    {
        $a = $this->due();
        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.booking']);

        $this->actingAs($assist)->post(route('members.dues.bulk-pay'), ['mode' => 'selected', 'fiscal_year_id' => $this->year->id, 'due_ids' => [$a->id]])->assertForbidden();
        $this->post('/logout');
        $this->post(route('members.dues.bulk-pay'), ['mode' => 'selected', 'fiscal_year_id' => $this->year->id, 'due_ids' => [$a->id]])->assertRedirect(route('login'));

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function the_list_offers_the_bulk_bar_confirmation_and_the_result_report(): void
    {
        $a = $this->due(Member::factory()->create(['lastname' => 'Muster']));
        $b = $this->due(extra: ['status' => 'bezahlt', 'bezahlt_am' => '2026-02-01']);

        $html = $this->actingAs($this->admin)->get(route('members.dues.index', ['fiscal_year_id' => $this->year->id, 'status' => 'alle']))->assertOk()
            ->assertSee('id="dues-bulk-pay-confirm"', false)
            ->assertSee('data-payable-count="1"', false)
            ->assertSee('data-payable-sum="120.00"', false)
            ->assertSee('data-amount="120.00"', false)
            ->getContent();
        $this->assertStringContainsString('name="note"', $html);

        $this->bulk(['due_ids' => [$a->id, $b->id]]);
        $this->actingAs($this->admin)->get(route('members.dues.index', ['fiscal_year_id' => $this->year->id]))->assertOk()
            ->assertSee('data-testid="bulk-pay-report"', false)
            ->assertSeeText(__('members::admin.dues.bulk_skip.already_paid'));
    }

    #[Test]
    public function the_texts_exist_in_german_and_english(): void
    {
        foreach (['bulk_pay', 'bulk_pay_result', 'bulk_pay_confirm_title', 'bulk_pay_confirm_text', 'bulk_pay_confirm', 'bulk_select_filter', 'bulk_filter_selected',
            'bulk_clear_selection', 'bulk_too_many', 'bulk_note', 'bulk_report_title', 'bulk_skip.already_paid', 'bulk_skip.closed_year', 'bulk_skip.other_year',
            'bulk_skip.not_found', 'bulk_skip.failed'] as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertNotSame('members::admin.dues.'.$key, __('members::admin.dues.'.$key, [], $locale), $locale.' '.$key);
            }
        }
    }

    #[Test]
    public function payments_are_linked_through_the_existing_tables(): void
    {
        $a = $this->due();
        $this->bulk(['due_ids' => [$a->id]]);

        $this->assertSame(1, DB::table('payment_dues')->where('member_due_id', $a->id)->count());
    }
}
