<?php

namespace Tests\Feature\Admin;

use App\Models\LoginHistory;
use App\Models\User;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserListViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DOMXPath $xpath;

    private string $html;

    private User $pat;

    private User $sam;

    private User $max;

    private function render(): void
    {
        $this->pat = User::factory()->create(['alias' => 'Pat Example', 'status' => 'enabled']);
        $this->sam = User::factory()->create(['alias' => 'Sam Example', 'status' => 'blocked']);
        $this->max = User::factory()->create(['alias' => 'Max Example', 'status' => 'placeholder']);
        LoginHistory::record((int) $this->pat->uid, '1.1.1.1', 'RU', 'success', ['unusual_country', 'impossible_travel']);

        $admin = User::factory()->create(['status' => 'admin']);
        $response = $this->actingAs($admin)->get('/admin/users?q=Example');
        $response->assertOk();
        $this->html = $response->getContent();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$this->html);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);
    }

    private function nodes(string $query): DOMNodeList
    {
        $nodes = $this->xpath->query($query);
        $this->assertNotFalse($nodes);

        return $nodes;
    }

    private function row(string $name): string
    {
        return '//tbody/tr[.//td[contains(@class,"ds-cell--title")][contains(normalize-space(.), "'.$name.'")]]';
    }

    #[Test]
    public function filter_card_has_labelled_search_and_status(): void
    {
        $this->render();

        $this->assertSame(1, $this->nodes('//label[@for="users-q"]')->length);
        $this->assertSame(1, $this->nodes('//input[@id="users-q"][@name="q"]')->length);
        $this->assertSame(1, $this->nodes('//label[@for="users-status"]')->length);
        $this->assertSame(1, $this->nodes('//select[@id="users-status"][@name="status"]')->length);
        // "All" plus the five account states; the permission has its own filter.
        $this->assertSame(6, $this->nodes('//select[@id="users-status"]/option')->length);
        $this->assertSame(1, $this->nodes('//label[@for="users-role"]')->length);
        $this->assertSame(4, $this->nodes('//select[@id="users-role"][@name="role"]/option')->length);
        $this->assertSame(1, $this->nodes('//form[contains(@class,"ds-admin-filter")]//button[@type="submit"][contains(@class,"ds-btn--primary")]')->length);
    }

    #[Test]
    public function rows_show_initials_badges_and_edit_action(): void
    {
        $this->render();

        $pat = $this->row('Pat Example');
        $this->assertSame('PE', trim($this->nodes($pat.'//span[contains(@class,"ds-admin-initials")]')->item(0)?->textContent ?? ''));
        $this->assertSame(1, $this->nodes($pat.'//td[contains(@class,"ds-admin-users__status")]//span[contains(@class,"ds-badge--good")]')->length);
        $this->assertSame(1, $this->nodes($this->row('Sam Example').'//td[contains(@class,"ds-admin-users__status")]//span[contains(@class,"ds-badge--problem")]')->length);
        $this->assertSame(1, $this->nodes($this->row('Max Example').'//td[contains(@class,"ds-admin-users__status")]//span[contains(@class,"ds-badge--neutral")]')->length);

        foreach ([$this->pat, $this->sam, $this->max] as $user) {
            $href = route('admin.users.edit', $user);
            $this->assertSame(1, $this->nodes('//tbody//a[contains(@class,"ds-btn--secondary")][@href="'.$href.'"]')->length);
        }

        foreach ($this->nodes('//tbody//td') as $td) {
            $this->assertNotSame('', trim($td->getAttribute('data-label')), 'td without data-label');
        }
    }

    #[Test]
    public function bulk_buttons_keep_action_values_and_confirms(): void
    {
        $this->render();

        $block = $this->nodes('//button[@name="action"][@value="blocked"]');
        $this->assertSame(1, $block->length);
        $this->assertStringContainsString('confirm(', $block->item(0)->getAttribute('onclick'));
        $this->assertStringContainsString('ds-btn--danger', $block->item(0)->getAttribute('class'));

        $enable = $this->nodes('//button[@name="action"][@value="enabled"]');
        $this->assertSame(1, $enable->length);
        $this->assertStringContainsString('confirm(', $enable->item(0)->getAttribute('onclick'));

        $this->assertStringContainsString(':count ausgewählt', $this->html);
        $this->assertStringNotContainsString("' ausgewählt'", $this->html);

        $this->assertSame(1, $this->nodes('//thead//input[@type="checkbox"][@aria-label="Alle auswählen"]')->length);
        $this->assertSame(1, $this->nodes('//tbody//input[@type="checkbox"][@aria-label="Pat Example auswählen"]')->length);
    }

    #[Test]
    public function risk_score_is_a_single_line_badge(): void
    {
        $this->render();

        $this->assertStringContainsString('>2<', $this->html);
    }
}
