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
use App\Modules\Members\Support\HouseholdService;
use App\Modules\Members\Support\MemberChangeSummary;
use App\Modules\Members\Support\MemberConnectionService;
use App\Modules\Members\Support\PaymentCanceller;
use App\Modules\Members\Support\PaymentProtocol;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Club decision 2026-11-08: "Zahlung erfassen" and the bulk "Als bezahlt markieren" write the member change protocol:
 * who and when (the entry itself), amount, covered dues (year, category), payment date and a short note. A bulk run
 * writes one entry per payment with a shared run id; household payments are logged for the payer and every covered
 * member (as the storno does).
 */
class PaymentProtocolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    private FiscalYear $year;

    private FiscalYear $previous;

    private FeeCategory $category;

    private Member $anna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-08 10:00:00');
        $this->admin = User::factory()->create(['status' => 'admin']);
        $this->previous = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->category = FeeCategory::create(['name' => 'Vollzahler']);
        $this->anna = Member::factory()->create(['firstname' => 'Anna', 'lastname' => 'Muster']);
    }

    private function due(Member $member, FiscalYear $year, float $amount = 120): MemberDue
    {
        return MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $this->category->id, 'amount' => $amount, 'status' => 'ausstehend']);
    }

    /** @return array<string, mixed> */
    private function entryOf(Member $member): array
    {
        return MemberChange::query()->where('member_id', $member->id)->where('action', PaymentProtocol::CHANGE_ACTION)->sole()->after['payment_recorded'];
    }

    private function householdDue(): array
    {
        $family = FeeCategory::create(['name' => 'Familie', 'billing_unit' => 'household']);
        $ben = Member::factory()->create(['firstname' => 'Ben', 'lastname' => 'Muster']);
        $household = app(HouseholdService::class)->create([$this->anna->id, $ben->id], $family->id, 'Familie Muster');
        $household->forceFill(['payer_member_id' => $ben->id])->save();
        $hdue = HouseholdDue::create(['household_id' => $household->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $family->id, 'amount' => 235, 'status' => 'ausstehend']);
        $hdue->coveredMembers()->attach([$this->anna->id => ['fiscal_year_id' => $this->year->id], $ben->id => ['fiscal_year_id' => $this->year->id]]);

        return [$hdue, $ben];
    }

    #[Test]
    public function a_single_payment_is_logged_with_amount_dues_date_and_note(): void
    {
        $a = $this->due($this->anna, $this->previous, 110);
        $b = $this->due($this->anna, $this->year, 120);

        $this->actingAs($this->admin)->post(route('members.payments.store', $this->anna), [
            'amount' => 230, 'paid_at' => '2026-11-01', 'note' => 'Überweisung Sparkasse', 'member_due_ids' => [$a->id, $b->id],
        ])->assertSessionHasNoErrors();

        $change = MemberChange::query()->where('member_id', $this->anna->id)->where('action', PaymentProtocol::CHANGE_ACTION)->sole();
        $this->assertSame($this->admin->getAuthIdentifier(), $change->admin_user_id, 'who');
        $entry = $change->after['payment_recorded'];
        $this->assertSame(Payment::query()->sole()->id, $entry['id']);
        $this->assertSame(['230.00', '2026-11-01', 'Überweisung Sparkasse', null], [$entry['amount'], $entry['paid_at'], $entry['note'], $entry['batch']]);
        $this->assertSame([['kind' => 'member', 'year' => '2025', 'category' => 'Vollzahler', 'amount' => '110.00'], ['kind' => 'member', 'year' => '2026', 'category' => 'Vollzahler', 'amount' => '120.00']], $entry['dues']);

        $line = __('members::admin.history.payment_recorded', ['amount' => '230,00 €', 'date' => '01.11.2026', 'dues' => '2025 (Vollzahler), 2026 (Vollzahler)'])
            .' · '.__('members::admin.history.payment_note', ['note' => 'Überweisung Sparkasse']);
        $this->actingAs($this->admin)->get(route('members.edit', $this->anna))->assertOk()->assertSeeText($line);

        // The protocol across members never shows the note (personal values hidden there).
        $this->actingAs($this->admin)->get(route('members.changes.index'))->assertOk()
            ->assertSeeText(__('members::admin.history.payment_recorded', ['amount' => '230,00 €', 'date' => '01.11.2026', 'dues' => '2025 (Vollzahler), 2026 (Vollzahler)']))
            ->assertDontSeeText('Überweisung Sparkasse');
    }

    #[Test]
    public function a_long_note_is_kept_short_in_the_protocol(): void
    {
        $due = $this->due($this->anna, $this->year);
        $this->actingAs($this->admin)->post(route('members.payments.store', $this->anna), [
            'amount' => 120, 'paid_at' => '2026-11-01', 'note' => str_repeat('x', 200), 'member_due_ids' => [$due->id],
        ])->assertSessionHasNoErrors();

        $note = $this->entryOf($this->anna)['note'];
        $this->assertSame(PaymentProtocol::NOTE_LENGTH, mb_strlen($note));
        $this->assertStringEndsWith('…', $note);
        $this->assertSame(200, mb_strlen((string) Payment::query()->sole()->note), 'the payment keeps the full note');
    }

    #[Test]
    public function a_multibyte_note_is_cut_on_characters_not_bytes(): void
    {
        $due = $this->due($this->anna, $this->year);
        $this->actingAs($this->admin)->post(route('members.payments.store', $this->anna), [
            'amount' => 120, 'paid_at' => '2026-11-01', 'note' => str_repeat('Äö', 60), 'member_due_ids' => [$due->id],
        ])->assertSessionHasNoErrors();

        $note = $this->entryOf($this->anna)['note'];
        $this->assertSame(PaymentProtocol::NOTE_LENGTH, mb_strlen($note));
        $this->assertTrue(mb_check_encoding($note, 'UTF-8'));
        $this->assertSame(str_repeat('Äö', 39).'Ä…', $note);
    }

    #[Test]
    public function a_failing_protocol_write_rolls_back_only_that_bulk_payment(): void
    {
        $ben = Member::factory()->create(['firstname' => 'Ben']);
        [$annaDue, $benDue] = [$this->due($this->anna, $this->year), $this->due($ben, $this->year)];
        MemberChange::creating(function (MemberChange $change) use ($ben): void {
            if ($change->member_id === $ben->id) {
                throw new \RuntimeException('protocol down');
            }
        });

        $this->actingAs($this->admin)->post(route('members.dues.bulk-pay'), ['mode' => 'selected', 'fiscal_year_id' => $this->year->id, 'due_ids' => [$annaDue->id, $benDue->id]])
            ->assertSessionHas('bulk_pay_report', fn (array $r) => $r['paid'] === 1 && $r['skipped'][0]['reason'] === 'failed');

        $this->assertSame('bezahlt', $annaDue->fresh()?->status);
        $this->assertSame(['ausstehend', 0], [$benDue->fresh()?->status, $benDue->payments()->count()], 'no payment without its protocol entry');
        $this->assertSame(1, Payment::query()->count());
    }

    #[Test]
    public function a_household_payment_is_logged_for_the_payer_and_every_covered_member(): void
    {
        [$hdue, $ben] = $this->householdDue();
        $carl = Member::factory()->create(['firstname' => 'Carl']);

        $this->actingAs($this->admin)->post(route('members.payments.store', $ben), [
            'amount' => 235, 'paid_at' => '2026-11-01', 'household_due_ids' => [$hdue->id],
        ])->assertSessionHasNoErrors();

        foreach ([$ben, $this->anna] as $member) {
            $this->assertSame([['kind' => 'household', 'year' => '2026', 'category' => 'Familie', 'amount' => '235.00', 'household' => 'Familie Muster']], $this->entryOf($member)['dues']);
        }
        $this->assertSame(0, MemberChange::query()->where('member_id', $carl->id)->count());
        $this->assertSame(2, MemberChange::query()->where('action', PaymentProtocol::CHANGE_ACTION)->count());
    }

    #[Test]
    public function a_bulk_run_logs_one_entry_per_payment_with_a_shared_run_id(): void
    {
        $ben = Member::factory()->create(['firstname' => 'Ben']);
        $paidAlready = Member::factory()->create(['firstname' => 'Cleo']);
        $dues = [$this->due($this->anna, $this->year), $this->due($ben, $this->year, 130)];
        $done = MemberDue::create(['member_id' => $paidAlready->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->category->id, 'amount' => 120, 'status' => 'bezahlt', 'bezahlt_am' => '2026-02-01']);

        $this->actingAs($this->admin)->post(route('members.dues.bulk-pay'), [
            'mode' => 'selected', 'fiscal_year_id' => $this->year->id, 'paid_at' => '2026-11-01', 'note' => 'Kassa',
            'due_ids' => [$dues[0]->id, $dues[1]->id, $done->id],
        ])->assertSessionHasNoErrors();

        [$annaEntry, $benEntry] = [$this->entryOf($this->anna), $this->entryOf($ben)];
        $this->assertNotNull($annaEntry['batch']);
        $this->assertSame($annaEntry['batch'], $benEntry['batch'], 'one run, one id');
        $this->assertNotSame($annaEntry['id'], $benEntry['id']);
        $this->assertSame(['120.00', '130.00', 'Kassa', 'Kassa'], [$annaEntry['amount'], $benEntry['amount'], $annaEntry['note'], $benEntry['note']]);
        $this->assertSame(0, MemberChange::query()->where('member_id', $paidAlready->id)->count(), 'skipped rows are not logged');

        // A second run gets another id.
        $next = $this->due(Member::factory()->create(['firstname' => 'Dora']), $this->year);
        $this->actingAs($this->admin)->post(route('members.dues.bulk-pay'), ['mode' => 'selected', 'fiscal_year_id' => $this->year->id, 'due_ids' => [$next->id]]);
        $this->assertNotSame($annaEntry['batch'], MemberChange::query()->where('member_id', $next->member_id)->sole()->after['payment_recorded']['batch']);

        $this->actingAs($this->admin)->get(route('members.edit', $this->anna))
            ->assertSeeText(__('members::admin.history.payment_batch', ['batch' => PaymentProtocol::shortBatch($annaEntry['batch'])]));
    }

    #[Test]
    public function a_bulk_household_payment_is_logged_for_the_payer_and_the_covered_members(): void
    {
        [$hdue, $ben] = $this->householdDue();

        $this->actingAs($this->admin)->post(route('members.dues.bulk-pay'), ['mode' => 'selected', 'fiscal_year_id' => $this->year->id, 'household_due_ids' => [$hdue->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->entryOf($ben)['batch'], $this->entryOf($this->anna)['batch']);
        $this->assertSame(2, MemberChange::query()->where('action', PaymentProtocol::CHANGE_ACTION)->count());
    }

    #[Test]
    public function the_lines_render_in_german_and_english_and_hide_personal_parts_when_asked(): void
    {
        [$hdue, $ben] = $this->householdDue();
        $this->actingAs($this->admin)->post(route('members.payments.store', $ben), [
            'amount' => 235, 'paid_at' => '2026-11-01', 'note' => 'Bar', 'household_due_ids' => [$hdue->id],
        ])->assertSessionHasNoErrors();
        $change = MemberChange::query()->where('member_id', $ben->id)->where('action', PaymentProtocol::CHANGE_ACTION)->sole();

        app()->setLocale('de');
        $de = implode(' ', MemberChangeSummary::lines($change, true));
        $this->assertStringContainsString('235,00 €', $de);
        $this->assertStringContainsString('01.11.2026', $de);
        $this->assertStringContainsString('2026 (Familie, Familie Muster)', $de);
        $this->assertStringContainsString('Bar', $de);

        app()->setLocale('en');
        $en = implode(' ', MemberChangeSummary::lines($change, true));
        $this->assertStringContainsString('€235.00', $en);
        $this->assertStringContainsString(__('members::admin.history.payment_note', ['note' => 'Bar']), $en);
        $this->assertStringStartsWith('Payment recorded: €235.00 on 01.11.2026 for 2026 (Familie, Familie Muster)', $en);

        $hidden = implode(' ', MemberChangeSummary::lines($change, false));
        $this->assertStringNotContainsString('Familie Muster', $hidden);
        $this->assertStringNotContainsString('Bar', $hidden);
        $this->assertStringContainsString('€235.00', $hidden);
    }

    #[Test]
    public function every_protocol_action_fits_the_16_character_column(): void
    {
        foreach ([PaymentProtocol::CHANGE_ACTION, PaymentCanceller::CHANGE_ACTION, MemberConnectionService::ENDED_ACTION, MemberConnectionService::SWAP_ACTION] as $action) {
            $this->assertLessThanOrEqual(16, strlen($action), $action);
        }
        // (SQLite does not enforce varchar(16); DuesBulkPayMysqlLockTest writes these entries on MySQL.)
    }
}
