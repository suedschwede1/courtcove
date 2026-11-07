<?php

namespace Tests\Feature\Calendar;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\QueriesHtml;
use Tests\TestCase;

final class CalendarLegendTest extends TestCase
{
    use LazilyRefreshDatabase;
    use QueriesHtml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 10:00'));
    }

    /** @return list<string> */
    private function states(string $html): array
    {
        $xp = $this->xpath($html);
        $list = $xp->query('//ul[contains(@class,"cal-legend")]');
        $this->assertSame(1, $list->length);
        $this->assertSame('Legende', $list->item(0)->getAttribute('aria-label'));

        $states = [];
        foreach ($xp->query('.//li', $list->item(0)) as $li) {
            $state = $li->getAttribute('data-state');
            $states[] = $state;
            $this->assertSame(1, $xp->query('.//span[@aria-hidden="true"][contains(@class,"cal-swatch--'.$state.'")]', $li)->length, $state);
        }

        return $states;
    }

    #[Test]
    public function member_sees_eight_legend_items(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $response = $this->actingAs($member)->get('/calendar?date=2026-11-04')->assertOk();

        $this->assertSame(['free', 'own', 'single_future', 'seeking', 'event', 'blocked', 'tournament', 'past'], $this->states($response->getContent()));
        $xp = $this->xpath($response->getContent());
        $this->assertStringContainsString('Frei – zum Buchen tippen', $xp->query('//ul[contains(@class,"cal-legend")]/li')->item(0)->textContent);
        $response->assertDontSee('Blaue Felder')->assertDontSee('Rosafarbene')->assertDontSee('Weiße Felder');
    }

    #[Test]
    public function guest_sees_seven_legend_items_without_own(): void
    {
        $response = $this->get('/calendar?date=2026-11-04')->assertOk();

        // Guests see search cells too (they lead to the login), so the legend explains them.
        $this->assertSame(['free', 'single_future', 'seeking', 'event', 'blocked', 'tournament', 'past'], $this->states($response->getContent()));
        $response->assertDontSee('Blaue Felder')->assertDontSee('Rosafarbene')->assertDontSee('Weiße Felder');
    }
}
