<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Events\FiscalYearsLocking;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Sanctum\Sanctum;
use Modules\Api\Mcp\Tools\CreateDueTool;
use Modules\Api\Mcp\Tools\UpdateDueTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The race between validating a change and writing it: a year that is closed after the request
 * was validated (still open then) must not get the write. Every write path re-checks the year
 * inside its transaction, with the year row locked first. The close is simulated exactly at that
 * point: when the write path is about to lock the year rows, a listener closes the year directly
 * in the database, as a concurrent "Geschäftsjahr abschließen" would.
 */
class FiscalYearCloseRaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LOCKED_2026 = 'Geschäftsjahr 2026 ist abgeschlossen. Änderungen sind nicht mehr möglich.';

    private const LOCKED_2027 = 'Geschäftsjahr 2027 ist abgeschlossen. Änderungen sind nicht mehr möglich.';

    private FiscalYear $year;

    private FiscalYear $nextYear;

    private FeeCategory $category;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $this->category = FeeCategory::create(['name' => 'Erwachsene']);
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'faellig_am' => '2026-03-31']);
        $this->nextYear = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        foreach ([$this->year, $this->nextYear] as $year) {
            FeeCategoryRate::create(['fee_category_id' => $this->category->id, 'fiscal_year_id' => $year->id, 'amount' => 100]);
        }
        $this->member = Member::factory()->create(['fee_category_id' => $this->category->id]);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function due(FiscalYear $year, array $attributes = []): MemberDue
    {
        return MemberDue::create([
            'member_id' => $this->member->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $this->category->id,
            'amount' => 100, 'status' => 'ausstehend', 'faellig_am' => $year->faellig_am ?? $year->starts_on, ...$attributes,
        ]);
    }

    /** Closes $year in the database the moment a write path is about to lock it (once). */
    private function closeWhenLocking(FiscalYear $year): void
    {
        $done = false;
        Event::listen(FiscalYearsLocking::class, function (FiscalYearsLocking $event) use ($year, &$done): void {
            if (! $done && in_array($year->id, $event->fiscalYearIds, true)) {
                $done = true;
                DB::table('fiscal_years')->where('id', $year->id)->update(['abgeschlossen_am' => now(), 'abgeschlossen_von' => 999]);
            }
        });
    }

    // ---- fiscal year --------------------------------------------------------------------

    #[Test]
    public function editing_the_year(): void
    {
        $this->closeWhenLocking($this->year);

        $this->actingAs($this->admin())->from("/members/fiscal-years/{$this->year->id}/edit")
            ->put("/members/fiscal-years/{$this->year->id}", ['name' => '2026 neu', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])
            ->assertRedirect("/members/fiscal-years/{$this->year->id}/edit")
            ->assertSessionHasErrors(['fiscal_year' => self::LOCKED_2026]);

        $this->assertSame('2026', $this->year->fresh()->name);
    }

    #[Test]
    public function closing_a_year_that_was_closed_meanwhile_keeps_the_first_close(): void
    {
        $this->closeWhenLocking($this->year);
        $admin = $this->admin();

        $this->actingAs($admin)->from("/members/fiscal-years/{$this->year->id}/edit")
            ->post("/members/fiscal-years/{$this->year->id}/close", ['confirm_open_dues' => '1'])
            ->assertSessionHasErrors(['fiscal_year' => 'Geschäftsjahr 2026 ist bereits abgeschlossen.']);

        // The simulated close ran inside the refused request's transaction and was rolled back
        // with it; what matters is that this request did not close the year (again).
        $this->assertNotSame($admin->uid, $this->year->fresh()->abgeschlossen_von);
    }

    #[Test]
    public function closing_counts_open_dues_under_the_lock(): void
    {
        // A due created between the edit page and the close (right before the lock) still needs the confirmation.
        Event::listen(FiscalYearsLocking::class, function (): void {
            if (MemberDue::query()->count() === 0) {
                $this->due($this->year);
            }
        });

        $this->actingAs($this->admin())->from("/members/fiscal-years/{$this->year->id}/edit")
            ->post("/members/fiscal-years/{$this->year->id}/close")
            ->assertSessionHasErrors('confirm_open_dues');

        $this->assertFalse($this->year->fresh()->isClosed());
    }

    // ---- fee rates ----------------------------------------------------------------------

    #[Test]
    public function the_rate_matrix(): void
    {
        $this->closeWhenLocking($this->year);

        $this->actingAs($this->admin())->from('/members/fee-categories')
            ->post('/members/fee-categories/rates', ['fiscal_year_id' => $this->year->id, 'rates' => [$this->category->id => '150']])
            ->assertSessionHasErrors(['fiscal_year_id' => self::LOCKED_2026]);

        $this->assertSame('100.00', (string) FeeCategoryRate::query()->where('fiscal_year_id', $this->year->id)->value('amount'));
    }

    #[Test]
    public function the_fee_category_edit_page(): void
    {
        $this->closeWhenLocking($this->year);

        $this->actingAs($this->admin())->from("/members/fee-categories/{$this->category->id}/edit")
            ->put("/members/fee-categories/{$this->category->id}", ['name' => 'Vollzahler', 'rates' => [$this->year->id => '150', $this->nextYear->id => '120']])
            ->assertSessionHasErrors(['rates.'.$this->year->id => self::LOCKED_2026]);

        $this->assertSame('Erwachsene', $this->category->fresh()->name);
        $this->assertSame('100.00', (string) FeeCategoryRate::query()->where('fiscal_year_id', $this->year->id)->value('amount'));
        $this->assertSame('100.00', (string) FeeCategoryRate::query()->where('fiscal_year_id', $this->nextYear->id)->value('amount'));
    }

    // ---- dues ---------------------------------------------------------------------------

    #[Test]
    public function creating_a_due_by_hand(): void
    {
        $this->closeWhenLocking($this->year);

        $this->actingAs($this->admin())->from('/members/dues')->post('/members/dues', [
            'member_id' => $this->member->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->category->id,
        ])->assertSessionHasErrors(['fiscal_year_id' => self::LOCKED_2026]);

        $this->assertSame(0, MemberDue::query()->count());
    }

    #[Test]
    public function generating_dues(): void
    {
        $this->due($this->year);
        $this->closeWhenLocking($this->nextYear);

        $this->actingAs($this->admin())->from('/members/dues')
            ->post('/members/dues/generate', ['fiscal_year_id' => $this->nextYear->id])
            ->assertSessionHasErrors(['fiscal_year_id' => self::LOCKED_2027]);

        $this->assertSame(0, MemberDue::query()->where('fiscal_year_id', $this->nextYear->id)->count());
    }

    #[Test]
    public function changing_a_due_inline(): void
    {
        $due = $this->due($this->year);
        $this->closeWhenLocking($this->year);
        $this->actingAs($this->admin());

        $this->patchJson("/members/dues/{$due->id}/field", ['field' => 'status', 'value' => 'bezahlt'])
            ->assertStatus(422)->assertJson(['message' => self::LOCKED_2026]);

        $fresh = $due->fresh();
        $this->assertSame('ausstehend', $fresh->status);
        $this->assertNull($fresh->bezahlt_am);
    }

    #[Test]
    public function marking_dues_paid_in_bulk(): void
    {
        $due = $this->due($this->year);
        $this->closeWhenLocking($this->year);

        $this->actingAs($this->admin())->from('/members/dues')
            ->post('/members/dues/bulk-mark-paid', ['due_ids' => [$due->id]])
            ->assertSessionHasErrors(['due_ids' => self::LOCKED_2026]);

        $this->assertSame('ausstehend', $due->fresh()->status);
    }

    #[Test]
    public function marking_dues_paid_in_bulk_through_a_year_closed_meanwhile(): void
    {
        $due = $this->due($this->year);
        $this->closeWhenLocking($this->nextYear);

        $this->actingAs($this->admin())->from('/members/dues')
            ->post('/members/dues/bulk-mark-paid', ['due_ids' => [$due->id], 'bezahlt_bis_fiscal_year_id' => $this->nextYear->id])
            ->assertSessionHasErrors(['bezahlt_bis_fiscal_year_id' => self::LOCKED_2027]);

        $this->assertSame('ausstehend', $due->fresh()->status);
        $this->assertSame(0, MemberDue::query()->where('fiscal_year_id', $this->nextYear->id)->count());
    }

    #[Test]
    public function marking_a_due_paid_through_a_later_year(): void
    {
        $due = $this->due($this->year);
        $this->closeWhenLocking($this->nextYear);
        $this->actingAs($this->admin());

        $this->patchJson("/members/dues/{$due->id}/paid-through", ['fiscal_year_id' => $this->nextYear->id])
            ->assertStatus(422)->assertJson(['message' => self::LOCKED_2027]);

        $this->assertSame('ausstehend', $due->fresh()->status);
        $this->assertSame(0, MemberDue::query()->where('fiscal_year_id', $this->nextYear->id)->count());
    }

    // ---- payments -----------------------------------------------------------------------

    #[Test]
    public function recording_a_payment(): void
    {
        $due = $this->due($this->year);
        $this->closeWhenLocking($this->year);

        $this->actingAs($this->admin())->from("/members/{$this->member->id}/payments/create")
            ->post("/members/{$this->member->id}/payments", ['amount' => 100, 'paid_at' => '2026-10-01', 'member_due_ids' => [$due->id]])
            ->assertSessionHasErrors(['member_due_ids' => 'Ein ausgewählter Beitrag gehört zu einem abgeschlossenen Geschäftsjahr und kann nicht mehr bezahlt werden.']);

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, DB::table('payment_dues')->count());
        $this->assertSame('ausstehend', $due->fresh()->status);
    }

    // ---- CSV import ---------------------------------------------------------------------

    #[Test]
    public function the_csv_import(): void
    {
        $this->closeWhenLocking($this->year);
        $file = sys_get_temp_dir().'/members-import-close-race.csv';
        file_put_contents($file, "Name,Vorname,Mitgliedsart,MB ,Bemerkung,Email,Strasse,PLZ,Ort,Privat,Mobil,Geschlecht,GebDatum,ID-Nr,Alter,Reservierungssystem\n"
            ."Muster,Anna,Erwachsene,bezahlt 26,,,,,,,,weiblich,,,,Nein\n");

        $this->artisan('members:import-csv', ['file' => $file])
            ->expectsOutputToContain('Vorschreibungen in abgeschlossenen Geschäftsjahren übersprungen: 1.')
            ->assertSuccessful();

        $this->assertSame(0, MemberDue::query()->where('fiscal_year_id', $this->year->id)->count());
        $this->assertSame(0, Payment::query()->count());
    }

    // ---- API and MCP tools --------------------------------------------------------------

    #[Test]
    public function the_api_refuses_dues_of_a_year_closed_before_or_meanwhile(): void
    {
        Sanctum::actingAs($this->admin());
        $due = $this->due($this->year);
        $other = Member::factory()->create();

        $this->closeWhenLocking($this->year);
        $this->postJson('/api/v1/dues', ['member_id' => $other->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->category->id])
            ->assertStatus(422)->assertJsonFragment(['message' => self::LOCKED_2026]);
        $this->putJson("/api/v1/dues/{$due->id}", ['status' => 'bezahlt'])
            ->assertStatus(422)->assertJsonFragment(['message' => self::LOCKED_2026]);

        $this->assertSame(1, MemberDue::query()->count());
        $this->assertSame('ausstehend', $due->fresh()->status);
    }

    #[Test]
    public function the_mcp_tools_refuse_dues_of_a_year_closed_meanwhile(): void
    {
        Sanctum::actingAs($this->admin());
        $due = $this->due($this->year);
        $other = Member::factory()->create();
        $this->closeWhenLocking($this->year);

        $created = app(CreateDueTool::class)->handle(new McpRequest(['member_id' => $other->id, 'fiscal_year_id' => $this->year->id, 'fee_category_id' => $this->category->id]));
        $this->assertTrue($created->isError());
        $updated = app(UpdateDueTool::class)->handle(new McpRequest(['due_id' => $due->id, 'status' => 'bezahlt']));
        $this->assertTrue($updated->isError());

        $this->assertSame(1, MemberDue::query()->count());
        $this->assertSame('ausstehend', $due->fresh()->status);
    }
}
