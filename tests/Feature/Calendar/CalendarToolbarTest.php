<?php

namespace Tests\Feature\Calendar;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\QueriesHtml;
use Tests\TestCase;

final class CalendarToolbarTest extends TestCase
{
    use LazilyRefreshDatabase;
    use QueriesHtml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 10:00'));
    }

    private function page(string $query = '?date=2026-11-04')
    {
        $member = User::factory()->create(['status' => 'enabled']);

        return $this->actingAs($member)->get('/calendar'.$query)->assertOk();
    }

    private function norm(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    #[Test]
    public function heading_is_the_long_selected_date(): void
    {
        $xp = $this->xpath($this->page()->getContent());

        $this->assertSame(1, $xp->query('//h1')->length);
        $this->assertStringStartsWith('Mittwoch, 4. November 2026', $this->norm($xp->query('//h1')->item(0)->textContent));
    }

    #[Test]
    public function date_nav_links_to_the_neighbour_days(): void
    {
        $response = $this->page();
        $xp = $this->xpath($response->getContent());

        $nav = $xp->query('//nav[@id="calendar-date-nav"]');
        $this->assertSame(1, $nav->length);
        $this->assertSame(__('booking.calendar.date_navigation'), $nav->item(0)->getAttribute('aria-label'));

        $prev = $xp->query('//nav[@id="calendar-date-nav"]//a[@data-cal-nav="prev"]');
        $this->assertSame(1, $prev->length);
        $this->assertSame(route('calendar.index', ['date' => '2026-11-03']), $prev->item(0)->getAttribute('href'));
        $this->assertSame(__('booking.nav.previous_day'), $prev->item(0)->getAttribute('aria-label'));
        $this->assertStringContainsString('ds-icon-btn', $prev->item(0)->getAttribute('class'));

        $next = $xp->query('//nav[@id="calendar-date-nav"]//a[@data-cal-nav="next"]');
        $this->assertSame(1, $next->length);
        $this->assertSame(route('calendar.index', ['date' => '2026-11-05']), $next->item(0)->getAttribute('href'));
        $this->assertSame(__('booking.nav.next_day'), $next->item(0)->getAttribute('aria-label'));

        $children = $xp->query('//nav[@id="calendar-date-nav"]/*');
        $this->assertSame(4, $children->length);
        $this->assertSame('prev', $children->item(0)->getAttribute('data-cal-nav'));
        $this->assertSame('a', $children->item(1)->nodeName);
        $this->assertSame(route('calendar.index'), $children->item(1)->getAttribute('href'));
        $this->assertSame('form', $children->item(2)->nodeName);
        $this->assertSame('next', $children->item(3)->getAttribute('data-cal-nav'));

        $response->assertDontSee('‹')->assertDontSee('&#8249;', false);
    }

    #[Test]
    public function today_link_and_date_field(): void
    {
        $xp = $this->xpath($this->page()->getContent());
        $url = route('calendar.index');

        $today = $xp->query('//nav[@id="calendar-date-nav"]//a[@href="'.$url.'"]');
        $this->assertSame(1, $today->length);
        $this->assertSame('Heute', $this->norm($today->item(0)->textContent));

        $label = $xp->query('//nav[@id="calendar-date-nav"]//form[@method="GET"][@action="'.$url.'"]//label[contains(@class,"cal-toolbar__date-field")]');
        $this->assertSame(1, $label->length);
        $this->assertSame(1, $xp->query('.//*[local-name()="svg"][contains(@class,"ds-icon")]', $label->item(0))->length);
        $this->assertSame(1, $xp->query('.//input[@id="c-date"][@type="date"][@name="date"][@value="2026-11-04"]', $label->item(0))->length);
        $sr = $xp->query('.//*[contains(@class,"sr-only")]', $label->item(0));
        $this->assertSame(1, $sr->length);
        $this->assertSame(__('booking.nav.choose_date'), $this->norm($sr->item(0)->textContent));
    }

    #[Test]
    public function today_hint_only_when_selected_date_is_today(): void
    {
        $xp = $this->xpath($this->page('?date=2026-11-04')->getContent());
        $this->assertSame(1, $xp->query('//*[contains(@class,"cal-toolbar__today-hint")]')->length);

        $xp = $this->xpath($this->page('?date=2026-11-06')->getContent());
        $this->assertSame(0, $xp->query('//*[contains(@class,"cal-toolbar__today-hint")]')->length);
    }

    #[Test]
    public function invalid_date_falls_back_to_today(): void
    {
        $xp = $this->xpath($this->page('?date=garbage')->getContent());

        $this->assertStringStartsWith('Mittwoch, 4. November 2026', $this->norm($xp->query('//h1')->item(0)->textContent));
    }

    #[Test]
    public function panel_toggles_follow_a_divider_and_the_header_has_no_sub_bar(): void
    {
        $response = $this->page();
        $xp = $this->xpath($response->getContent());

        $panels = $xp->query('//div[contains(@class,"cal-toolbar__panels")]');
        $this->assertSame(1, $panels->length);
        $first = $xp->query('./*', $panels->item(0))->item(0);
        $this->assertSame('span', $first->nodeName);
        $this->assertStringContainsString('cal-toolbar__divider', $first->getAttribute('class'));
        $this->assertSame('true', $first->getAttribute('aria-hidden'));

        foreach (['system-info-panel', 'help-panel'] as $id) {
            $toggle = $xp->query('//*[@data-panel-toggle="'.$id.'"]');
            $this->assertSame(1, $toggle->length);
            $this->assertSame($id, $toggle->item(0)->getAttribute('aria-controls'));
        }

        $response->assertDontSee('ds-subbar', false)->assertDontSee('calendar-header-nav', false);
    }
}
