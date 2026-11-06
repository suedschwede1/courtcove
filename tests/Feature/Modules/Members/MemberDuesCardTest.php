<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The dues card on the member page is the compact list of the Mitglied-Detail mockup: one line per
 * year (Jahr, Kategorie · fällig, Betrag, Status, Bezahlt am) and the rarely used editors
 * (Bezahlt bis, Teilbetrag, Kommentar) in a details row behind a toggle, so the card never scrolls
 * sideways; "Zahlung erfassen" sits under the list.
 */
class MemberDuesCardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Member $member;

    private MemberDue $open;

    private MemberDue $withExtras;

    private MemberDue $locked;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-06 10:00:00');
        $category = FeeCategory::create(['name' => 'Jugend']);
        $closed = FiscalYear::create(['name' => '2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'faellig_am' => '2025-03-31']);
        $current = FiscalYear::create(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'faellig_am' => '2026-03-31']);
        $next = FiscalYear::create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'faellig_am' => '2027-03-31']);
        $this->member = Member::factory()->create(['fee_category_id' => $category->id]);
        $due = fn (FiscalYear $year, array $attributes = []) => MemberDue::create([
            'member_id' => $this->member->id, 'fiscal_year_id' => $year->id, 'fee_category_id' => $category->id,
            'amount' => 1200, 'status' => 'ausstehend', 'faellig_am' => $year->faellig_am, ...$attributes,
        ]);
        $this->open = $due($next);
        $this->withExtras = $due($current, ['teilbetrag' => 90, 'kommentar' => 'Zahlt in Raten']);
        $this->locked = $due($closed, ['status' => 'bezahlt', 'bezahlt_am' => '2025-03-12']);
        $closed->close(1);
    }

    private function page(string $locale = 'de'): string
    {
        $admin = User::factory()->create(['status' => 'admin']);

        return (string) $this->actingAs($admin)->withUnencryptedCookie('locale', $locale)
            ->get("/members/{$this->member->id}/edit")->assertOk()->getContent();
    }

    private function card(string $html): string
    {
        $start = (int) strpos($html, 'mbr-dues-card');

        return substr($html, $start, (int) strpos($html, 'mbr-detail__side', $start) - $start);
    }

    #[Test]
    public function the_table_has_the_compact_columns_of_the_mockup(): void
    {
        $card = $this->card($this->page());

        $this->assertStringContainsString('mbr-dues-table', $card);
        $this->assertStringNotContainsString('ds-table--stack', $card);
        preg_match_all('#<th scope="col"[^>]*>(.*?)</th>#s', $card, $headers);
        $labels = array_map(fn (string $label): string => trim(strip_tags($label)), $headers[1]);
        $this->assertSame(['Jahr', 'Kategorie', 'Betrag', 'Status', 'Bezahlt am', 'Details'], $labels);
    }

    #[Test]
    public function the_english_page_uses_the_english_labels(): void
    {
        $card = $this->card($this->page('en'));

        preg_match_all('#<th scope="col"[^>]*>(.*?)</th>#s', $card, $headers);
        $this->assertSame(['Year', 'Category', 'Amount', 'Status', 'Paid on', 'Details'], array_map(fn (string $label): string => trim(strip_tags($label)), $headers[1]));
        $this->assertStringContainsString('Jugend · due 03/31/2027', $card);
        $this->assertStringContainsString('Record payment', $card);
    }

    #[Test]
    public function each_open_due_shows_category_due_date_and_a_one_line_amount(): void
    {
        $card = $this->card($this->page());

        $this->assertStringContainsString('Jugend · fällig 31.03.2027', $card);
        $this->assertMatchesRegularExpression('#<td class="[^"]*mbr-due-amount[^"]*whitespace-nowrap[^"]*"[^>]*data-amount>1\.200,00 €</td>#', $card);
        // Paid dues do not repeat the due date; their date is in "Bezahlt am".
        $this->assertStringNotContainsString('fällig 31.03.2025', $card);
        $this->assertStringContainsString('12.03.2025', $card);
    }

    #[Test]
    public function the_rarely_used_editors_sit_in_a_details_row_behind_a_toggle(): void
    {
        $card = $this->card($this->page());

        foreach ([$this->open, $this->withExtras] as $due) {
            $this->assertStringContainsString('id="due-extra-'.$due->id.'"', $card);
            $this->assertStringContainsString('aria-controls="due-extra-'.$due->id.'"', $card);
            $this->assertStringContainsString('x-show="openDues['.$due->id.']"', $card);
        }
        $extra = substr($card, (int) strpos($card, 'id="due-extra-'.$this->open->id.'"'));
        $extra = substr($extra, 0, (int) strpos($extra, '</tr>'));
        foreach (['data-field="paid_through"', 'data-field="teilbetrag"', 'data-field="kommentar"', 'Bezahlt bis', 'Teilbetrag', 'Kommentar'] as $needle) {
            $this->assertStringContainsString($needle, $extra);
        }
    }

    #[Test]
    public function details_with_content_start_open_the_others_closed(): void
    {
        $card = $this->card($this->page());

        $this->assertStringContainsString('openDues: {"'.$this->open->id.'":false,"'.$this->withExtras->id.'":true}', html_entity_decode($card));
    }

    #[Test]
    public function a_locked_row_has_the_same_columns_and_shows_its_extras_read_only(): void
    {
        $this->locked->forceFill(['kommentar' => 'Altbestand'])->save();
        $card = $this->card($this->page());

        $row = substr($card, (int) strpos($card, 'data-locked="true"'));
        $row = substr($row, 0, (int) strpos($row, '</tr>'));
        $this->assertSame(6, substr_count($row, '<td'));
        $this->assertStringContainsString('2025 (abgeschlossen)', $row);
        $this->assertStringContainsString('Altbestand', $row);
        $this->assertStringNotContainsString('<select', $row);
    }

    #[Test]
    public function record_payment_sits_under_the_list(): void
    {
        $card = $this->card($this->page());

        $this->assertStringContainsString('href="'.route('members.payments.create', $this->member).'"', $card);
        $this->assertStringContainsString('Zahlung erfassen', $card);
    }

    #[Test]
    public function the_built_css_switches_the_card_to_stacked_lines_by_its_own_width(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $css = (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

        $this->assertMatchesRegularExpression('/\.mbr-dues-card\{[^}]*container-type:inline-size/', $css);
        $this->assertStringContainsString('@container', $css);
        $this->assertStringContainsString('.mbr-due-extra', $css);
    }
}
