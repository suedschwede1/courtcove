<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Closing a business year (Geschäftsjahr): club decisions are (1) everything of a closed year is
 * locked, (2) open dues only warn and need an explicit confirmation, (3) no member change log entry.
 * Users with the members privilege close; only administrators reopen.
 */
class FiscalYearCloseTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $closed;

    private FiscalYear $open;

    private FeeCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->category = FeeCategory::create(['name' => 'Erwachsene']);
        $this->closed = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'faellig_am' => '2025-03-31']);
        $this->open = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'faellig_am' => '2026-03-31']);
        foreach ([$this->closed, $this->open] as $year) {
            FeeCategoryRate::create(['fee_category_id' => $this->category->id, 'fiscal_year_id' => $year->id, 'amount' => 100]);
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function membersAssist(): User
    {
        $user = User::factory()->status('assist')->create();
        $user->syncPrivileges(['admin.members']);

        return $user->fresh();
    }

    private function due(FiscalYear $year, array $attributes = [], ?Member $member = null): MemberDue
    {
        $member ??= Member::factory()->create(['fee_category_id' => $this->category->id]);

        return MemberDue::create([
            'member_id' => $member->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $this->category->id,
            'amount' => 100, 'status' => 'ausstehend', 'faellig_am' => $year->faellig_am, ...$attributes,
        ]);
    }

    private function closeYear(FiscalYear $year): void
    {
        $year->forceFill(['abgeschlossen_am' => now(), 'abgeschlossen_von' => 1])->save();
    }

    // ---- schema -------------------------------------------------------------------------

    #[Test]
    public function the_migration_adds_both_columns_and_can_run_twice(): void
    {
        $this->assertTrue(Schema::hasColumns('fiscal_years', ['abgeschlossen_am', 'abgeschlossen_von']));

        $migration = require base_path('app/Modules/Members/Database/Migrations/2026_11_06_090002_add_abgeschlossen_to_fiscal_years_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumns('fiscal_years', ['abgeschlossen_am', 'abgeschlossen_von']));
        $this->assertFalse($this->open->fresh()->isClosed());
    }

    // ---- closing ------------------------------------------------------------------------

    #[Test]
    public function a_year_without_open_dues_is_closed_without_confirmation_and_without_a_change_log_entry(): void
    {
        $this->due($this->open, ['status' => 'bezahlt']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/members/fiscal-years/{$this->open->id}/close")
            ->assertRedirect(route('members.fiscal-years.edit', $this->open))
            ->assertSessionHas('status');

        $year = $this->open->fresh();
        $this->assertTrue($year->isClosed());
        $this->assertSame('2026-11-06 10:00:00', $year->abgeschlossen_am?->format('Y-m-d H:i:s'));
        $this->assertSame($admin->uid, $year->abgeschlossen_von);
        $this->assertSame(0, MemberChange::count());
    }

    #[Test]
    public function open_dues_only_warn_and_need_the_explicit_confirmation(): void
    {
        $this->due($this->open);
        $this->due($this->open, ['status' => 'ueberfaellig']);
        $this->due($this->open, ['status' => 'bezahlt']);
        $this->actingAs($this->admin());

        $this->get("/members/fiscal-years/{$this->open->id}/edit")
            ->assertOk()
            ->assertSee('2 Beiträge sind noch offen.')
            ->assertSee('name="confirm_open_dues"', false);

        $this->post("/members/fiscal-years/{$this->open->id}/close")
            ->assertSessionHasErrors('confirm_open_dues');
        $this->post("/members/fiscal-years/{$this->open->id}/close", ['confirm_open_dues' => ['1']])
            ->assertSessionHasErrors('confirm_open_dues');
        $this->assertFalse($this->open->fresh()->isClosed());

        $this->post("/members/fiscal-years/{$this->open->id}/close", ['confirm_open_dues' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->open->fresh()->isClosed());
    }

    #[Test]
    public function a_members_assistant_can_close_but_not_reopen(): void
    {
        $assist = $this->membersAssist();

        $this->actingAs($assist)->post("/members/fiscal-years/{$this->open->id}/close")->assertSessionHasNoErrors();
        $this->assertTrue($this->open->fresh()->isClosed());

        $this->post("/members/fiscal-years/{$this->open->id}/reopen")->assertForbidden();
        // Authorization comes before the id lookup: no existence probing.
        $this->post('/members/fiscal-years/999999/reopen')->assertForbidden();
        $this->assertTrue($this->open->fresh()->isClosed());

        $this->get("/members/fiscal-years/{$this->open->id}/edit")->assertOk()->assertDontSee('Wieder öffnen');
    }

    #[Test]
    public function an_admin_reopens_a_closed_year(): void
    {
        $this->closeYear($this->closed);

        $this->actingAs($this->admin())
            ->get("/members/fiscal-years/{$this->closed->id}/edit")
            ->assertOk()
            ->assertSee('Abgeschlossen am 06.11.2026')
            ->assertSee('Wieder öffnen');

        $this->post("/members/fiscal-years/{$this->closed->id}/reopen")
            ->assertRedirect(route('members.fiscal-years.edit', $this->closed));

        $year = $this->closed->fresh();
        $this->assertFalse($year->isClosed());
        $this->assertNull($year->abgeschlossen_von);
    }

    #[Test]
    public function users_without_the_members_privilege_cannot_close_or_reopen(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);

        $this->actingAs($user)->post("/members/fiscal-years/{$this->open->id}/close")->assertForbidden();
        $this->post('/members/fiscal-years/999999/close')->assertForbidden();
        $this->post("/members/fiscal-years/{$this->open->id}/reopen")->assertForbidden();
        $this->assertFalse($this->open->fresh()->isClosed());
    }

    #[Test]
    public function odd_ids_and_double_actions_never_give_a_500(): void
    {
        $this->actingAs($this->admin());

        $this->post('/members/fiscal-years/abc/close')->assertNotFound();
        $this->post('/members/fiscal-years/999999/close')->assertNotFound();

        $this->closeYear($this->closed);
        $this->post("/members/fiscal-years/{$this->closed->id}/close")->assertSessionHasErrors('fiscal_year');
        $this->post("/members/fiscal-years/{$this->open->id}/reopen")->assertSessionHasErrors('fiscal_year');
    }

    // ---- lock points --------------------------------------------------------------------

    #[Test]
    public function the_year_itself_cannot_be_edited_once_closed(): void
    {
        $this->closeYear($this->closed);

        $this->actingAs($this->admin())->put("/members/fiscal-years/{$this->closed->id}", [
            'name' => 'Umbenannt', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'faellig_am' => '2025-04-30',
        ])->assertSessionHasErrors('fiscal_year');

        $this->assertSame('2025', $this->closed->fresh()->name);
        $this->assertSame('2025-03-31', $this->closed->fresh()->faellig_am?->toDateString());
    }

    #[Test]
    public function rates_of_a_closed_year_cannot_change_in_the_matrix(): void
    {
        $this->closeYear($this->closed);
        $this->actingAs($this->admin());

        $this->post('/members/fee-categories/rates', ['fiscal_year_id' => $this->closed->id, 'rates' => [$this->category->id => '150']])
            ->assertSessionHasErrors('fiscal_year_id');
        $this->assertSame('100.00', FeeCategoryRate::where('fiscal_year_id', $this->closed->id)->value('amount'));

        $this->post('/members/fee-categories/rates', ['fiscal_year_id' => $this->open->id, 'rates' => [$this->category->id => '150']])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function the_category_edit_page_cannot_change_a_closed_years_rate_but_keeps_working_otherwise(): void
    {
        $this->closeYear($this->closed);
        $this->actingAs($this->admin());

        $this->get("/members/fee-categories/{$this->category->id}/edit")
            ->assertOk()
            ->assertSee('id="fc-rate-'.$this->closed->id.'"', false)
            ->assertSee('2025 (abgeschlossen)');

        $this->put("/members/fee-categories/{$this->category->id}", [
            'name' => 'Erwachsene', 'rates' => [$this->closed->id => '150', $this->open->id => '120'],
        ])->assertSessionHasErrors('rates.'.$this->closed->id);
        $this->assertSame('100.00', FeeCategoryRate::where('fiscal_year_id', $this->closed->id)->value('amount'));
        $this->assertSame('100.00', FeeCategoryRate::where('fiscal_year_id', $this->open->id)->value('amount'));

        // The unchanged stored value of the closed year may be sent back with the form.
        $this->put("/members/fee-categories/{$this->category->id}", [
            'name' => 'Vollzahler', 'rates' => [$this->closed->id => '100.00', $this->open->id => '120'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Vollzahler', $this->category->fresh()->name);
        $this->assertSame('120.00', FeeCategoryRate::where('fiscal_year_id', $this->open->id)->value('amount'));
    }

    #[Test]
    public function no_due_can_be_created_or_generated_for_a_closed_year(): void
    {
        $this->due($this->open);
        $member = Member::factory()->create(['fee_category_id' => $this->category->id]);
        $this->closeYear($this->closed);
        $this->actingAs($this->admin());

        $this->post('/members/dues', ['member_id' => $member->id, 'fiscal_year_id' => $this->closed->id, 'fee_category_id' => $this->category->id])
            ->assertSessionHasErrors('fiscal_year_id');
        $this->post('/members/dues/generate', ['fiscal_year_id' => $this->closed->id])
            ->assertSessionHasErrors('fiscal_year_id');

        $this->assertSame(0, MemberDue::where('fiscal_year_id', $this->closed->id)->count());
    }

    #[Test]
    public function inline_edits_of_a_closed_years_due_are_refused(): void
    {
        $due = $this->due($this->closed, ['status' => 'bezahlt', 'bezahlt_am' => '2025-05-01']);
        $this->closeYear($this->closed);
        $this->actingAs($this->admin());

        foreach ([['status', 'ausstehend'], ['teilbetrag', '10'], ['kommentar', 'neu'], ['bezahlt_am', '2025-06-01']] as [$field, $value]) {
            $this->patchJson("/members/dues/{$due->id}/field", ['field' => $field, 'value' => $value])
                ->assertStatus(422)
                ->assertJson(['message' => 'Geschäftsjahr 2025 ist abgeschlossen. Änderungen sind nicht mehr möglich.']);
        }

        $fresh = $due->fresh();
        $this->assertSame('bezahlt', $fresh->status);
        $this->assertNull($fresh->teilbetrag);
        $this->assertNull($fresh->kommentar);
        $this->assertSame('2025-05-01', $fresh->bezahlt_am?->toDateString());
    }

    #[Test]
    public function bulk_mark_paid_refuses_a_selection_containing_a_closed_years_due(): void
    {
        $closedDue = $this->due($this->closed);
        $openDue = $this->due($this->open);
        $this->closeYear($this->closed);

        $this->actingAs($this->admin())
            ->post('/members/dues/bulk-mark-paid', ['due_ids' => [$closedDue->id, $openDue->id]])
            ->assertSessionHasErrors('due_ids');

        $this->assertSame('ausstehend', $closedDue->fresh()->status);
        $this->assertSame('ausstehend', $openDue->fresh()->status);
    }

    #[Test]
    public function paid_through_cannot_touch_a_closed_year(): void
    {
        $later = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        $closedDue = $this->due($this->closed);
        $member = Member::factory()->create();
        $earlyDue = $this->due($this->closed, [], $member);
        $this->closeYear($this->closed);
        $this->actingAs($this->admin());

        $this->patchJson("/members/dues/{$closedDue->id}/paid-through", ['fiscal_year_id' => $later->id])->assertStatus(422);
        $this->assertSame('ausstehend', $closedDue->fresh()->status);

        // From an open year through a closed later year: reopen order swapped for this case.
        $this->closed->forceFill(['abgeschlossen_am' => null])->save();
        $this->closeYear($this->open);
        $this->patchJson("/members/dues/{$earlyDue->id}/paid-through", ['fiscal_year_id' => $later->id])->assertStatus(422);
        $this->post('/members/dues/bulk-mark-paid', ['due_ids' => [$earlyDue->id], 'bezahlt_bis_fiscal_year_id' => $later->id])
            ->assertSessionHasErrors('bezahlt_bis_fiscal_year_id');
        $this->assertSame('ausstehend', $earlyDue->fresh()->status);
        $this->assertSame(0, MemberDue::where('member_id', $member->id)->where('fiscal_year_id', $this->open->id)->count());
    }

    #[Test]
    public function payments_cannot_include_a_closed_years_due_and_the_form_does_not_offer_it(): void
    {
        $member = Member::factory()->create();
        $closedDue = $this->due($this->closed, [], $member);
        $openDue = $this->due($this->open, [], $member);
        $this->closeYear($this->closed);
        $this->actingAs($this->admin());

        $this->get("/members/{$member->id}/payments/create")
            ->assertOk()
            ->assertSee('value="'.$openDue->id.'"', false)
            ->assertDontSee('name="member_due_ids[]" value="'.$closedDue->id.'"', false);

        $this->post("/members/{$member->id}/payments", [
            'amount' => 200, 'paid_at' => '2026-10-01', 'member_due_ids' => [$closedDue->id, $openDue->id],
        ])->assertSessionHasErrors('member_due_ids.0');

        $this->assertSame(0, Payment::count());
        $this->assertSame('ausstehend', $openDue->fresh()->status);
    }

    // ---- read-only views ----------------------------------------------------------------

    #[Test]
    public function the_dues_list_marks_a_closed_year_and_offers_no_actions(): void
    {
        $this->due($this->closed);
        $this->closeYear($this->closed);

        $response = $this->actingAs($this->admin())
            ->get("/members/dues?status=alle&fiscal_year_id={$this->closed->id}")
            ->assertOk()
            ->assertSee('<option value="'.$this->closed->id.'" selected>2025 (abgeschlossen)</option>', false)
            ->assertSee('data-testid="dues-year-closed"', false)
            ->assertDontSee('id="dues-bulk-mark-paid"', false)
            ->assertDontSee('id="due-compose"', false)
            ->assertDontSee('data-field="status"', false);

        // The open year still offers everything.
        $this->get("/members/dues?fiscal_year_id={$this->open->id}")
            ->assertOk()
            ->assertSee('id="dues-bulk-mark-paid"', false)
            ->assertDontSee('data-testid="dues-year-closed"', false);

        $response->assertDontSee('<option value="'.$this->closed->id.'">2025', false);
    }

    #[Test]
    public function the_member_card_shows_closed_years_rows_read_only(): void
    {
        $member = Member::factory()->create();
        $this->due($this->closed, [], $member);
        $this->due($this->open, [], $member);
        $this->closeYear($this->closed);

        $html = $this->actingAs($this->admin())->get("/members/{$member->id}/edit")->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-field="status"'));
        $this->assertStringContainsString('2025 (abgeschlossen)', $html);
    }

    #[Test]
    public function the_year_list_shows_the_closed_state(): void
    {
        $this->closeYear($this->closed);

        $this->actingAs($this->admin())->get('/members/fiscal-years')
            ->assertOk()
            ->assertSee('Abgeschlossen');
    }

    #[Test]
    public function export_stays_available_for_closed_years(): void
    {
        $this->due($this->closed);
        $this->closeYear($this->closed);

        $this->actingAs($this->admin())->get('/members/export')->assertOk();
    }

    #[Test]
    public function the_dues_list_does_not_query_per_row_for_the_lock(): void
    {
        $this->closeYear($this->closed);
        $admin = $this->admin();
        $this->due($this->closed);
        $count = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get("/members/dues?status=alle&fiscal_year_id={$this->closed->id}")->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $few = $count();
        for ($i = 0; $i < 6; $i++) {
            $this->due($this->closed);
        }

        $this->assertSame($few, $count());
    }

    #[Test]
    public function the_csv_import_skips_closed_years(): void
    {
        $this->closeYear($this->closed);
        $file = sys_get_temp_dir().'/members-import-closed-year.csv';
        file_put_contents($file, "Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem\n"
            ."Muster,Anna,Erwachsene,bezahlt 25,,,,,,,,weiblich,,,,Nein\n");

        $this->artisan('members:import-csv', ['file' => $file])->assertSuccessful();

        $this->assertSame(0, MemberDue::where('fiscal_year_id', $this->closed->id)->count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(1, MemberDue::where('fiscal_year_id', $this->open->id)->count());
    }
}
