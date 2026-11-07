<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Support\MemberConnectionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Dues run and export: each member pays from its own category; connection problems are reported, not changed. */
class MemberConnectionReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FiscalYear $year;

    private FeeCategory $full;

    private FeeCategory $partner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-07 10:00:00');
        $this->year = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->full = FeeCategory::create(['name' => 'Vollzahler', 'connection_role' => 'vollzahler']);
        $this->partner = FeeCategory::create(['name' => 'Ehepartner/in, Lebensgefährte/in', 'connection_role' => 'anschluss']);
        FeeCategoryRate::create(['fee_category_id' => $this->full->id, 'fiscal_year_id' => $this->year->id, 'amount' => 180]);
        FeeCategoryRate::create(['fee_category_id' => $this->partner->id, 'fiscal_year_id' => $this->year->id, 'amount' => 90]);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function both_partners_get_the_due_of_their_own_category(): void
    {
        $heinz = Member::factory()->create(['firstname' => 'Heinz', 'lastname' => 'Muster', 'fee_category_id' => $this->full->id]);
        $helga = Member::factory()->create(['firstname' => 'Helga', 'lastname' => 'Beispiel', 'fee_category_id' => $this->partner->id]);
        app(MemberConnectionService::class)->connect($helga, $heinz);

        $this->actingAs($this->admin())->post('/members/dues/generate', ['fiscal_year_id' => $this->year->id])
            ->assertSessionHas('dues_report', fn (array $r) => $r['skipped']['connection_warning'] === []);

        $this->assertSame('180.00', MemberDue::where('member_id', $heinz->id)->sole()->amount);
        $this->assertSame('90.00', MemberDue::where('member_id', $helga->id)->sole()->amount);
    }

    #[Test]
    public function an_anschluss_member_without_connection_is_billed_from_its_category_and_reported(): void
    {
        $helga = Member::factory()->create(['firstname' => 'Helga', 'lastname' => 'Beispiel', 'fee_category_id' => $this->partner->id]);

        $response = $this->actingAs($this->admin())->post('/members/dues/generate', ['fiscal_year_id' => $this->year->id])
            ->assertSessionHas('dues_report', fn (array $r) => count($r['skipped']['connection_warning']) === 1
                && str_contains($r['skipped']['connection_warning'][0]['label'], 'Helga Beispiel'));

        $this->assertSame('90.00', MemberDue::where('member_id', $helga->id)->sole()->amount);
        $this->followRedirects($response)->assertSeeText(__('members::admin.dues.report.connection_warning', ['count' => 1]));
    }

    #[Test]
    public function the_export_names_the_connected_vollzahler(): void
    {
        $heinz = Member::factory()->create(['firstname' => 'Heinz', 'lastname' => 'Muster', 'fee_category_id' => $this->full->id]);
        $helga = Member::factory()->create(['firstname' => 'Helga', 'lastname' => 'Beispiel', 'fee_category_id' => $this->partner->id]);
        app(MemberConnectionService::class)->connect($helga, $heinz);

        $csv = $this->actingAs($this->admin())->get('/members/export')->streamedContent();
        $lines = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));

        $this->assertSame('Verbunden mit', $lines[0][7]);
        $byName = collect($lines)->keyBy(fn ($line) => $line[0]);
        $this->assertSame('Heinz Muster', $byName['Helga'][7]);
        $this->assertSame('', $byName['Heinz'][7]);
    }
}
