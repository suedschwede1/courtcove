<?php

namespace Tests\Feature\Calendar;

use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\QueriesHtml;
use Tests\TestCase;

/**
 * The calendar page fills exactly one screen: no footer row and no legend row
 * take height from the grid, their content lives in the Infos panel instead.
 */
final class CalendarFillLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;
    use QueriesHtml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 10:00'));
    }

    private function legalOptions(): void
    {
        Option::create(['key' => 'client.website.contact', 'value' => 'https://example.com/contact', 'locale' => null]);
        Option::create(['key' => 'client.website.imprint', 'value' => 'https://example.com/imprint', 'locale' => null]);
        Option::create(['key' => 'client.website.privacy', 'value' => 'https://example.com/privacy', 'locale' => null]);
    }

    #[Test]
    public function contact_imprint_and_privacy_links_sit_in_the_infos_panel(): void
    {
        $this->legalOptions();

        foreach ([null, User::factory()->create(['status' => 'enabled'])] as $viewer) {
            $request = $viewer ? $this->actingAs($viewer) : $this;
            $xp = $this->xpath($request->get('/calendar?date=2026-11-04')->assertOk()->getContent());

            $panel = $xp->query('//section[@id="system-info-panel"]');
            $this->assertSame(1, $panel->length);
            $this->assertSame(1, $xp->query('//*[@data-panel-toggle="system-info-panel"][@aria-controls="system-info-panel"]')->length);

            foreach (['contact', 'imprint', 'privacy'] as $page) {
                $links = $xp->query('.//a[@href="https://example.com/'.$page.'"]', $panel->item(0));
                $this->assertSame(1, $links->length, $page);
                $this->assertSame(__('booking.calendar.operator_'.$page.'_link'), trim($links->item(0)->textContent));
            }
        }
    }

    #[Test]
    public function calendar_page_has_no_footer(): void
    {
        $this->legalOptions();
        $xp = $this->xpath($this->get('/calendar?date=2026-11-04')->assertOk()->getContent());

        $this->assertSame(0, $xp->query('//footer[not(ancestor::*[contains(@class,"ds-card") or contains(@class,"ds-modal")])]')->length);
        $this->assertSame(0, $xp->query('//*[@role="contentinfo"]')->length);
        // The legal links appear once, inside the Infos panel only.
        $this->assertSame(1, $xp->query('//a[@href="https://example.com/imprint"]')->length);
        $this->assertSame(0, $xp->query('//a[@href="https://example.com/imprint"][not(ancestor::section[@id="system-info-panel"])]')->length);
    }

    #[Test]
    public function legend_moves_into_the_infos_panel(): void
    {
        $xp = $this->xpath($this->get('/calendar?date=2026-11-04')->assertOk()->getContent());

        $this->assertSame(1, $xp->query('//section[@id="system-info-panel"]//ul[contains(@class,"cal-legend")]')->length);
        $this->assertSame(0, $xp->query('//*[contains(concat(" ", @class, " ")," cal-page ")]//ul[contains(@class,"cal-legend")]')->length);
    }

    #[Test]
    public function only_the_real_today_card_carries_the_today_ring(): void
    {
        $xp = $this->xpath($this->get('/calendar?date=2026-11-04')->assertOk()->getContent());
        $cards = $xp->query('//section[contains(concat(" ", @class, " ")," cal-day ")]');
        $this->assertSame(3, $cards->length);

        $marked = [];
        foreach ($cards as $card) {
            $marked[] = str_contains(' '.$card->getAttribute('class').' ', ' cal-day--today ');
        }
        $this->assertSame([true, false, false], $marked);

        $xp = $this->xpath($this->get('/calendar?date=2026-11-05')->assertOk()->getContent());
        $this->assertSame(0, $xp->query('//section[contains(@class,"cal-day--today")]')->length);
    }
}
