<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Bezahlt am" (paid on) of a membership due: set from the payment date when a payment
 * settles the due, defaulted to today when an admin marks a due paid by hand, cleared when
 * the due goes back to open, editable inline on paid dues, and shown wherever dues are listed.
 */
class MemberDuePaidOnTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function fiscalYear(string $name = '2026', string $start = '2026-01-01', string $end = '2026-12-31'): FiscalYear
    {
        return FiscalYear::create(['name' => $name, 'starts_on' => $start, 'ends_on' => $end]);
    }

    private function due(array $attributes = [], ?FiscalYear $fiscalYear = null, ?Member $member = null): MemberDue
    {
        $member ??= Member::factory()->create();
        $fiscalYear ??= FiscalYear::query()->first() ?? $this->fiscalYear();
        $category = FeeCategory::query()->first() ?? FeeCategory::create(['name' => 'Erwachsene']);

        return MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
            ...$attributes,
        ]);
    }

    #[Test]
    public function the_migration_adds_a_nullable_bezahlt_am_column_and_can_run_twice(): void
    {
        $this->assertTrue(Schema::hasColumn('member_dues', 'bezahlt_am'));

        $migration = require base_path('app/Modules/Members/Database/Migrations/2026_11_06_090001_add_bezahlt_am_to_member_dues_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('member_dues', 'bezahlt_am'));
        $this->assertNull($this->due()->fresh()->bezahlt_am);
    }

    #[Test]
    public function recording_a_payment_sets_bezahlt_am_to_the_payment_date_on_every_covered_due(): void
    {
        $member = Member::factory()->create();
        $due1 = $this->due([], $this->fiscalYear('2025', '2025-01-01', '2025-12-31'), $member);
        $due2 = $this->due(['amount' => 130], $this->fiscalYear('2026', '2026-01-01', '2026-12-31'), $member);
        $other = $this->due([], FiscalYear::where('name', '2026')->first());

        $this->actingAs($this->admin())->post("/members/{$member->id}/payments", [
            'amount' => 250,
            'paid_at' => '2026-10-15',
            'member_due_ids' => [$due1->id, $due2->id],
        ])->assertRedirect(route('members.edit', $member));

        $this->assertSame('2026-10-15', $due1->fresh()->bezahlt_am?->toDateString());
        $this->assertSame('2026-10-15', $due2->fresh()->bezahlt_am?->toDateString());
        $this->assertNull($other->fresh()->bezahlt_am);
    }

    #[Test]
    public function a_partial_amount_alone_does_not_set_bezahlt_am_only_the_settling_payment_does(): void
    {
        $member = Member::factory()->create();
        $due = $this->due(['teilbetrag' => 50], null, $member);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'teilbetrag', 'value' => '70'])
            ->assertOk();
        $this->assertNull($due->fresh()->bezahlt_am);

        // A payment must cover the whole due, so a short payment is refused and sets nothing.
        $this->post("/members/{$member->id}/payments", [
            'amount' => 50, 'paid_at' => '2026-10-01', 'member_due_ids' => [$due->id],
        ])->assertSessionHasErrors('amount');
        $this->assertNull($due->fresh()->bezahlt_am);

        $this->post("/members/{$member->id}/payments", [
            'amount' => 120, 'paid_at' => '2026-10-20', 'member_due_ids' => [$due->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20', $due->fresh()->bezahlt_am?->toDateString());
    }

    #[Test]
    public function a_payment_date_in_the_future_or_malformed_is_rejected(): void
    {
        $member = Member::factory()->create();
        $due = $this->due([], null, $member);
        $this->actingAs($this->admin());

        foreach (['2026-11-07', '2026-13-01', 'gestern', '1999-12-31'] as $bad) {
            $this->post("/members/{$member->id}/payments", [
                'amount' => 120, 'paid_at' => $bad, 'member_due_ids' => [$due->id],
            ])->assertSessionHasErrors('paid_at');
        }
        $this->post("/members/{$member->id}/payments", [
            'amount' => 120, 'paid_at' => ['2026-10-01'], 'member_due_ids' => [$due->id],
        ])->assertSessionHasErrors('paid_at');

        $this->assertSame(0, Payment::count());
        $this->assertNull($due->fresh()->bezahlt_am);
    }

    #[Test]
    public function marking_a_due_paid_inline_defaults_bezahlt_am_to_today_and_reopening_clears_it(): void
    {
        $due = $this->due();
        $this->actingAs($this->admin());

        $this->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'bezahlt'])
            ->assertOk()
            ->assertJson(['value' => 'bezahlt', 'bezahlt_am' => '2026-11-06', 'bezahlt_am_display' => '06.11.2026']);
        $this->assertSame('2026-11-06', $due->fresh()->bezahlt_am?->toDateString());

        $this->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'ausstehend'])
            ->assertOk()
            ->assertJson(['value' => 'ausstehend', 'bezahlt_am' => null, 'bezahlt_am_display' => '–']);
        $this->assertNull($due->fresh()->bezahlt_am);
    }

    #[Test]
    public function marking_an_already_dated_due_paid_again_keeps_its_date(): void
    {
        $due = $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-03-02']);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'bezahlt'])
            ->assertOk()
            ->assertJson(['bezahlt_am' => '2026-03-02']);

        $this->assertSame('2026-03-02', $due->fresh()->bezahlt_am?->toDateString());
    }

    #[Test]
    public function setting_the_status_to_overdue_also_clears_bezahlt_am(): void
    {
        $due = $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-03-02']);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'ueberfaellig'])
            ->assertOk();

        $this->assertNull($due->fresh()->bezahlt_am);
    }

    #[Test]
    public function admin_can_change_bezahlt_am_of_a_paid_due_inline(): void
    {
        $due = $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-11-06']);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'bezahlt_am', 'value' => '2026-04-30'])
            ->assertOk()
            ->assertJson(['value' => '30.04.2026', 'bezahlt_am' => '2026-04-30']);

        $this->assertSame('2026-04-30', $due->fresh()->bezahlt_am?->toDateString());
    }

    #[Test]
    public function bezahlt_am_cannot_be_set_on_an_open_due(): void
    {
        $due = $this->due();

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'bezahlt_am', 'value' => '2026-04-30'])
            ->assertStatus(422)
            ->assertJson(['message' => __('members::admin.dues.bezahlt_am_requires_paid')]);

        $this->assertNull($due->fresh()->bezahlt_am);
    }

    /** @return array<string, array{0: mixed}> */
    public static function invalidDates(): array
    {
        return [
            'future' => ['2026-11-07'],
            'far future' => ['2099-01-01'],
            'impossible day' => ['2026-02-30'],
            'wrong format' => ['30.04.2026'],
            'text' => ['gestern'],
            'too old' => ['1999-12-31'],
            'empty' => [''],
            'null' => [null],
            'array' => [['2026-04-30']],
        ];
    }

    #[Test]
    #[DataProvider('invalidDates')]
    public function an_invalid_bezahlt_am_is_rejected_with_422_never_500(mixed $value): void
    {
        $due = $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-03-02']);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'bezahlt_am', 'value' => $value])
            ->assertStatus(422);

        $this->assertSame('2026-03-02', $due->fresh()->bezahlt_am?->toDateString());
    }

    #[Test]
    public function bulk_mark_paid_sets_the_chosen_date_and_keeps_existing_dates(): void
    {
        $open = $this->due();
        $dated = $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-02-01']);

        $this->actingAs($this->admin())->post('/members/dues/bulk-mark-paid', [
            'due_ids' => [$open->id, $dated->id],
            'bezahlt_am' => '2026-10-31',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-10-31', $open->fresh()->bezahlt_am?->toDateString());
        $this->assertSame('2026-02-01', $dated->fresh()->bezahlt_am?->toDateString());
    }

    #[Test]
    public function bulk_mark_paid_without_a_date_uses_today(): void
    {
        $open = $this->due();

        $this->actingAs($this->admin())->post('/members/dues/bulk-mark-paid', [
            'due_ids' => [$open->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-11-06', $open->fresh()->bezahlt_am?->toDateString());
    }

    #[Test]
    public function bulk_mark_paid_rejects_a_bad_date_without_changing_anything(): void
    {
        $open = $this->due();
        $this->actingAs($this->admin());

        foreach (['2027-01-01', 'kaputt', ['2026-10-01']] as $bad) {
            $this->post('/members/dues/bulk-mark-paid', ['due_ids' => [$open->id], 'bezahlt_am' => $bad])
                ->assertSessionHasErrors('bezahlt_am');
        }

        $this->assertSame('ausstehend', $open->fresh()->status);
        $this->assertNull($open->fresh()->bezahlt_am);
    }

    #[Test]
    public function paid_through_sets_bezahlt_am_on_the_row_and_the_rows_it_creates(): void
    {
        $fy1 = $this->fiscalYear('2026', '2026-01-01', '2026-12-31');
        $fy2 = $this->fiscalYear('2027', '2027-01-01', '2027-12-31');
        $due = $this->due([], $fy1);

        $this->actingAs($this->admin())
            ->patchJson("/members/dues/{$due->id}/paid-through", ['fiscal_year_id' => $fy2->id])
            ->assertOk();

        $this->assertSame('2026-11-06', $due->fresh()->bezahlt_am?->toDateString());
        $created = MemberDue::where('member_id', $due->member_id)->where('fiscal_year_id', $fy2->id)->first();
        $this->assertSame('2026-11-06', $created?->bezahlt_am?->toDateString());
    }

    #[Test]
    public function a_user_without_the_members_privilege_cannot_set_bezahlt_am(): void
    {
        $due = $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-03-02']);

        $this->actingAs(User::factory()->create(['status' => 'enabled']))
            ->patchJson("/members/dues/{$due->id}/field", ['field' => 'bezahlt_am', 'value' => '2026-04-30'])
            ->assertForbidden();

        $this->assertSame('2026-03-02', $due->fresh()->bezahlt_am?->toDateString());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function locales(): array
    {
        return ['de' => ['de', 'Bezahlt am'], 'en' => ['en', 'Paid on']];
    }

    #[Test]
    #[DataProvider('locales')]
    public function the_dues_list_shows_a_bezahlt_am_column(string $locale, string $label): void
    {
        $fy = $this->fiscalYear();
        $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-03-02'], $fy);

        $response = $this->actingAs($this->admin())
            ->withUnencryptedCookie('locale', $locale)
            ->get('/members/dues?status=alle&fiscal_year_id='.$fy->id)
            ->assertOk()
            ->assertSee('>'.$label.'</th>', false)
            ->assertSee('data-field="bezahlt_am"', false)
            ->assertSee('02.03.2026');

        // The bulk action offers a "Bezahlt am" date defaulting to today.
        $response->assertSee('name="bezahlt_am"', false)->assertSee('value="2026-11-06"', false);
    }

    #[Test]
    #[DataProvider('locales')]
    public function the_member_dues_card_shows_bezahlt_am(string $locale, string $label): void
    {
        $member = Member::factory()->create();
        $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-03-02'], null, $member);

        $this->actingAs($this->admin())
            ->withUnencryptedCookie('locale', $locale)
            ->get("/members/{$member->id}/edit")
            ->assertOk()
            ->assertSee('>'.$label.'</th>', false)
            ->assertSee('data-field="bezahlt_am"', false)
            ->assertSee('02.03.2026');
    }

    #[Test]
    #[DataProvider('locales')]
    public function the_payment_form_labels_its_date_field_bezahlt_am(string $locale, string $label): void
    {
        $member = Member::factory()->create();
        $this->due([], null, $member);

        $this->actingAs($this->admin())
            ->withUnencryptedCookie('locale', $locale)
            ->get("/members/{$member->id}/payments/create")
            ->assertOk()
            ->assertSee('<label class="ds-field__label" for="pf-paid-at">'.$label.'</label>', false)
            ->assertSee('max="2026-11-06"', false);
    }

    #[Test]
    public function the_dues_list_does_not_query_per_row(): void
    {
        $fy = $this->fiscalYear();
        for ($i = 0; $i < 3; $i++) {
            $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-03-02'], $fy);
        }
        $admin = $this->admin();
        $count = function () use ($admin, $fy): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get('/members/dues?fiscal_year_id='.$fy->id)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $few = $count();
        for ($i = 0; $i < 6; $i++) {
            $this->due(['status' => 'bezahlt', 'bezahlt_am' => '2026-03-02'], $fy);
        }

        $this->assertSame($few, $count());
    }
}
