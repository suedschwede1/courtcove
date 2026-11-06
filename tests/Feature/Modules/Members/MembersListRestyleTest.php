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
 * Markup of the members list after the redesign (mockups Mitglieder-Liste and Mitglieder-Liste-Mobil): seven compact
 * columns, search field plus quick-filter chips, a footer with hint and range, and compact phone cards.
 */
class MembersListRestyleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function render(string $url, string $locale = 'de'): string
    {
        $admin = User::factory()->create(['status' => 'admin']);

        return (string) $this->actingAs($admin)->withUnencryptedCookie('locale', $locale)->get($url)->assertOk()->getContent();
    }

    private function memberWithDue(string $status = 'ausstehend'): Member
    {
        $member = Member::factory()->create(['member_number' => 12, 'firstname' => 'Anna', 'lastname' => 'Muster', 'status' => 'aktiv']);
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $member->update(['fee_category_id' => $category->id]);
        $fiscalYear = FiscalYear::create(['name' => '2026/27', 'starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->addMonths(11)->toDateString()]);
        MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
            'status' => $status,
        ]);

        return $member;
    }

    private function between(string $html, string $open, string $close): string
    {
        $start = strpos($html, $open);
        $this->assertNotFalse($start, $open.' missing');
        $end = strpos($html, $close, (int) $start);
        $this->assertNotFalse($end, $close.' missing');

        return substr($html, (int) $start, (int) $end - (int) $start);
    }

    /** @return list<string> */
    private function headers(string $html): array
    {
        $head = $this->between($html, '<thead>', '</thead>');
        preg_match_all('#<th scope="col"[^>]*>(.*?)</th>#s', $head, $m);

        return array_map(fn (string $cell) => trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($cell)))), $m[1]);
    }

    #[Test]
    public function the_table_has_the_seven_mockup_columns_plus_the_open_link(): void
    {
        $this->memberWithDue();

        $this->assertSame(
            ['Nr.', 'Name', 'Kategorie', 'Status', 'Beitrag 2026/27', 'Telefon', 'Benutzerkonto (optional)', 'Aktionen'],
            $this->headers($this->render('/members')),
        );
        $this->assertSame(
            ['No.', 'Name', 'Category', 'Status', 'Dues 2026/27', 'Phone', 'User account (optional)', 'Actions'],
            $this->headers($this->render('/members', 'en')),
        );
    }

    #[Test]
    public function without_a_fiscal_year_the_due_column_is_just_called_dues(): void
    {
        Member::factory()->create();

        $this->assertContains('Beiträge', $this->headers($this->render('/members')));
    }

    #[Test]
    public function rows_show_name_last_name_first_and_link_number_and_chevron_to_the_detail_page(): void
    {
        $member = $this->memberWithDue();

        $html = $this->render('/members');
        $table = $this->between($html, '<tbody>', '</tbody>');

        $this->assertStringContainsString('Muster, Anna', $table);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('members.edit', $member), '#').'" class="mbr-row-link">12</a>#', $table);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('members.edit', $member), '#').'" class="mbr-open-link" aria-label="Anna Muster öffnen">#', $table);
        $this->assertStringNotContainsString('mbr-initials', $html);
        $this->assertStringNotContainsString('data-field="email"', $html);
    }

    #[Test]
    public function the_due_column_shows_the_due_of_the_current_fiscal_year(): void
    {
        $member = $this->memberWithDue('bezahlt');
        $old = FiscalYear::create(['name' => '2024/25', 'starts_on' => '2024-09-01', 'ends_on' => '2025-08-31']);
        MemberDue::create(['member_id' => $member->id, 'fiscal_year_id' => $old->id, 'fee_category_id' => $member->fee_category_id, 'amount' => 100, 'status' => 'ueberfaellig']);

        $html = $this->render('/members');
        $cell = $this->between($html, 'data-field="due-status"', '</td>');

        $this->assertStringContainsString('>Bezahlt</span>', $cell);
        $this->assertStringNotContainsString('>Überfällig</span>', $cell);
    }

    #[Test]
    public function list_uses_the_shared_table_without_stacking_and_the_footer_shows_hint_range_and_pager(): void
    {
        Member::factory()->count(26)->create(['status' => 'aktiv']);

        $html = $this->render('/members?filter=aktiv');

        $this->assertStringContainsString('class="ds-table ds-table--list mbr-list-table"', $html);
        $this->assertStringNotContainsString('ds-table--stack', $html);
        $this->assertStringContainsString('Zelle anklicken zum Bearbeiten · Änderungen werden protokolliert', $html);
        $this->assertStringContainsString('1–25 von 26', $html);
        $this->assertStringContainsString('class="ds-pagination"', $html);
        preg_match('/href="([^"]*page=2[^"]*)"/', $html, $match);
        $this->assertNotEmpty($match);
        $this->assertStringContainsString('filter=aktiv', html_entity_decode($match[1]));
    }

    #[Test]
    public function the_empty_list_says_so(): void
    {
        $html = $this->render('/members?search=Niemand');

        $this->assertStringContainsString('Keine Mitglieder gefunden.', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringContainsString('0–0 von 0', $html);
    }

    #[Test]
    public function status_badges_use_the_module_tone_map(): void
    {
        $this->memberWithDue();

        // @js() hex-escapes the quotes of the JSON it writes into the x-data attribute.
        $html = str_replace('\u0022', '"', html_entity_decode($this->render('/members')));

        $this->assertStringContainsString('"ruhend":"ds-badge--attention"', $html);
        $this->assertStringContainsString('"ueberfaellig":"ds-badge--problem"', $html);
    }

    #[Test]
    public function phones_get_compact_cards_that_open_the_member(): void
    {
        $member = $this->memberWithDue();
        $dormant = Member::factory()->create(['member_number' => 71, 'firstname' => 'Franz', 'lastname' => 'Moser', 'status' => 'ruhend']);

        $html = $this->render('/members');
        $cards = $this->between($html, '<ul class="mbr-member-cards mbr-phone-only"', '</ul>');

        $this->assertSame(2, substr_count($cards, 'class="mbr-member-card"'));
        $this->assertMatchesRegularExpression(
            '#<a href="'.preg_quote(route('members.edit', $member), '#').'" class="mbr-member-card">\s*<span class="mbr-member-card__text">\s*'
            .'<span class="mbr-member-card__name">Muster, Anna</span>\s*<span class="mbr-member-card__meta">Nr\. 12 · Vollzahler</span>#',
            $cards,
        );
        // Active with an open due: only "Beitrag offen"; dormant: the status.
        $anna = $this->between($cards, 'Muster, Anna', '</a>');
        $this->assertStringContainsString('<span class="ds-badge ds-badge--attention">Beitrag offen</span>', $anna);
        $this->assertStringNotContainsString('>Aktiv</span>', $anna);
        $franz = $this->between($cards, 'Moser, Franz', '</a>');
        $this->assertStringContainsString('<span class="ds-badge ds-badge--attention">Ruhend</span>', $franz);
        $this->assertStringContainsString('href="'.route('members.edit', $dormant).'"', $cards);
        // No inline editors inside the phone cards.
        $this->assertStringNotContainsString('x-data', $cards);
    }

    #[Test]
    public function a_paid_member_card_shows_the_status(): void
    {
        $this->memberWithDue('bezahlt');

        $cards = $this->between($this->render('/members'), '<ul class="mbr-member-cards', '</ul>');

        $this->assertStringContainsString('<span class="ds-badge ds-badge--good">Aktiv</span>', $cards);
        $this->assertStringNotContainsString('Beitrag offen', $cards);
    }

    #[Test]
    public function header_offers_export_and_new_member(): void
    {
        $html = $this->render('/members');

        // Header button (desktop) and the pinned phone action bar.
        $this->assertSame(2, substr_count($html, 'href="'.route('members.create').'"'));
        $this->assertStringContainsString('href="'.route('members.export').'"', $html);
        $this->assertStringContainsString('href="'.route('members.mail.index').'"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    #[Test]
    public function search_field_and_chips_follow_the_mockup(): void
    {
        $html = $this->render('/members');
        $form = $this->between($html, '<form method="GET" action="'.route('members.index').'" class="mbr-list-filters" role="search">', '</form>');

        $this->assertStringContainsString('<label for="mi-search" class="sr-only">Mitglieder suchen</label>', $form);
        $this->assertMatchesRegularExpression('#<input type="search" name="search" id="mi-search" value="" maxlength="100"\s+placeholder="Name, Nummer oder E-Mail"#', $form);
        $chips = $this->between($form, '<div class="mbr-chips" role="group" aria-label="Filter">', '</div>');
        preg_match_all('#<a href="[^"]*" class="ds-chip"[^>]*>(.*?)</a>#s', $chips, $m);
        $this->assertSame(['Alle (0)', 'Aktiv', 'Ruhend', 'Jugend', 'Beitrag offen'], array_map('trim', $m[1]));
        $this->assertSame(1, substr_count($chips, 'aria-current="true"'));
        $this->assertStringContainsString('title="Aktive und ruhende Mitglieder bis 18 Jahre"', $chips);
        // More filters stay reachable, collapsed while none is set.
        $this->assertStringContainsString('<details class="ds-card ds-disclosure mbr-more-filters">', $form);
        $this->assertStringContainsString('name="fee_category"', $form);
        $this->assertStringContainsString('name="zahlung"', $form);
        $this->assertStringContainsString('Ausgetretene Mitglieder anzeigen', $form);
    }

    #[Test]
    public function english_chips(): void
    {
        $chips = $this->between($this->render('/members', 'en'), '<div class="mbr-chips"', '</div>');

        preg_match_all('#class="ds-chip"[^>]*>(.*?)</a>#s', $chips, $m);
        $this->assertSame(['All (0)', 'Active', 'Dormant', 'Youth', 'Dues open'], array_map('trim', $m[1]));
    }

    #[Test]
    public function an_active_more_filter_opens_the_disclosure(): void
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);

        $html = $this->render('/members?fee_category='.$category->id);

        $this->assertStringContainsString('<details class="ds-card ds-disclosure mbr-more-filters" open>', $html);
    }

    #[Test]
    public function nothing_in_the_list_forces_sideways_scrolling(): void
    {
        $this->memberWithDue();

        $html = $this->render('/members');
        $list = $this->between($html, 'class="mbr-list-filters"', '</section>');

        $this->assertDoesNotMatchRegularExpression('/\bmin-w-(?!0\b)[\w\[\]-]+/', $list);
        $this->assertDoesNotMatchRegularExpression('/\bw-\[\d{3,}px\]/', $list);
        $this->assertStringNotContainsString('style="', $list);
        // The table sits in the shared scroll box and is hidden on phones as a whole (cards instead).
        $this->assertStringContainsString('<div class="mbr-phone-hidden">'."\n".'        <div class="ds-table-wrap">', $html);

        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $css = (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
        // The screen-reader label of the last column stays inside the scroll box.
        $this->assertMatchesRegularExpression('/\.mbr-list-table \.mbr-cell-open\{[^}]*position:relative/', $css);
        // Search field may shrink; on phones the chips scroll inside their own row.
        $this->assertMatchesRegularExpression('/\.mbr-search\{[^}]*min-width:0/', $css);
        $this->assertMatchesRegularExpression('/\.mbr-chips\{[^}]*flex-wrap:nowrap[^}]*overflow-x:auto/', $css);
        // Narrow widths drop the optional hint and then the phone/account columns.
        $this->assertMatchesRegularExpression('/\.mbr-list-table \.mbr-col-wide\{display:none/', $css);
        $this->assertStringContainsString('mbr-col-wide', $html);
    }

    #[Test]
    public function saved_state_uses_a_class_not_a_style(): void
    {
        $this->memberWithDue();

        $html = $this->render('/members');

        $this->assertStringNotContainsString(':style=', $html);
        $this->assertStringContainsString("'mbr-saved'", $html);
    }
}
