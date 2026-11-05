<?php

namespace Tests\Feature\Calendar;

use App\Models\Event;
use App\Models\EventMeta;
use App\Models\Square;
use App\Models\SquareMeta;
use App\Models\User;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\QueriesHtml;
use Tests\TestCase;

final class CalendarGridTest extends TestCase
{
    use LazilyRefreshDatabase;
    use QueriesHtml;

    /** @var list<Square> */
    private array $squares = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 10:00'));

        $courtA = Square::factory()->create(['name' => '1', 'priority' => 1]);
        SquareMeta::create(['sid' => $courtA->sid, 'key' => 'alias', 'value' => 'Court A']);
        $courtB = Square::factory()->create(['name' => '2', 'priority' => 2]);
        SquareMeta::create(['sid' => $courtB->sid, 'key' => 'alias', 'value' => 'Court B']);
        $courtC = Square::factory()->create(['name' => 'Platz 3', 'priority' => 3]);

        $this->squares = [$courtA, $courtB, $courtC];
    }

    private function page(string $query = '?date=2026-11-04'): DOMXPath
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $response = $this->actingAs($member)->get('/calendar'.$query)->assertOk();

        return $this->xpath($response->getContent());
    }

    private function norm(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function element(DOMXPath $xp, string $query, ?DOMElement $context = null): DOMElement
    {
        $node = $xp->query($query, $context)->item(0);
        $this->assertInstanceOf(DOMElement::class, $node, $query);

        return $node;
    }

    /** @return list<DOMElement> */
    private function slots(DOMElement $row): array
    {
        $slots = [];
        foreach ($row->childNodes as $child) {
            if ($child instanceof DOMElement && ! str_contains($child->getAttribute('class'), 'cal-row__time')) {
                $slots[] = $child;
            }
        }

        return $slots;
    }

    private function row(DOMXPath $xp, string $day, int $hour): DOMElement
    {
        return $this->element($xp, '//section[@data-day="'.$day.'"]//div[contains(@class,"cal-row")][@data-hour="'.$hour.'"]');
    }

    private function event(?int $sid, string $start, string $end, string $name): void
    {
        $event = Event::factory()->create([
            'sid' => $sid,
            'status' => 'enabled',
            'datetime_start' => Carbon::parse($start),
            'datetime_end' => Carbon::parse($end),
        ]);
        EventMeta::create(['eid' => $event->eid, 'key' => 'name', 'value' => $name]);
    }

    #[Test]
    public function renders_three_day_cards_starting_with_the_selected_date(): void
    {
        $xp = $this->page('?date=2026-11-05');

        $cards = $xp->query('//section[contains(@class,"cal-day")]');
        $this->assertSame(3, $cards->length);

        $days = [];
        foreach ($cards as $index => $card) {
            $days[] = $card->getAttribute('data-day');
            $isExtra = str_contains(' '.$card->getAttribute('class').' ', ' cal-extra-day ');
            $this->assertSame($index > 0, $isExtra, 'card '.$index);
            $titleId = $card->getAttribute('aria-labelledby');
            $this->assertSame(1, $xp->query('.//h2[@id="'.$titleId.'"][contains(@class,"cal-day__title")]', $card)->length);
        }
        $this->assertSame(['1', '2', '3'], $days);

        $firstTitle = $this->element($xp, './/h2[contains(@class,"cal-day__title")]', $cards->item(0));
        $this->assertStringStartsWith('Donnerstag', $this->norm($firstTitle->textContent));
        $this->assertStringContainsString('5. November', $this->norm($firstTitle->textContent));

        $this->assertSame(1, $xp->query('//*[@id="calendar-grid"]')->length);
        $this->assertSame(0, $xp->query('//*[@id="calendar-grid"]//table')->length);
        $this->assertSame(3, $xp->query('//*[@id="calendar-grid"]/section[contains(@class,"cal-day")]')->length);
    }

    #[Test]
    public function today_pill_marks_the_real_today_only(): void
    {
        $xp = $this->page('?date=2026-11-04');
        $cards = $xp->query('//section[contains(@class,"cal-day")]');

        $pills = $xp->query('.//span[contains(@class,"cal-day__today")]', $cards->item(0));
        $this->assertSame(1, $pills->length);
        $this->assertSame('Heute', $this->norm($pills->item(0)->textContent));
        $this->assertSame(0, $xp->query('.//span[contains(@class,"cal-day__today")]', $cards->item(1))->length);
        $this->assertSame(0, $xp->query('.//span[contains(@class,"cal-day__today")]', $cards->item(2))->length);

        $xp = $this->page('?date=2026-11-05');
        $this->assertSame(0, $xp->query('//span[contains(@class,"cal-day__today")]')->length);
    }

    #[Test]
    public function court_header_shows_alias_and_position(): void
    {
        $xp = $this->page();
        $card = $this->element($xp, '//section[contains(@class,"cal-day")]');

        $courts = $xp->query('.//div[contains(@class,"cal-day__courts")]/div[contains(@class,"cal-court")]', $card);
        $this->assertSame(3, $courts->length);

        $this->assertSame('Court A', $this->norm($this->element($xp, './/span[contains(@class,"cal-court__alias")]', $courts->item(0))->textContent));
        $this->assertSame('Platz 1', $this->norm($this->element($xp, './/span[contains(@class,"cal-court__number")]', $courts->item(0))->textContent));

        $second = $this->norm($courts->item(1)->textContent);
        $this->assertStringContainsString('Court B', $second);
        $this->assertStringContainsString('Platz 2', $second);

        $this->assertSame('Platz 3', $this->norm($courts->item(2)->textContent));
        $this->assertSame(0, $xp->query('.//span[contains(@class,"cal-court__number")]', $courts->item(2))->length);

        $spacer = $this->element($xp, './/div[contains(@class,"cal-day__courts")]/span[contains(@class,"cal-day__courts-spacer")]', $card);
        $this->assertSame('true', $spacer->getAttribute('aria-hidden'));
    }

    #[Test]
    public function time_column_and_row_times(): void
    {
        $xp = $this->page();

        $times = $this->element($xp, '//*[@id="calendar-grid"]/div[contains(concat(" ", @class, " ")," cal-times ")]');
        $this->assertSame('true', $times->getAttribute('aria-hidden'));
        $this->assertSame(1, $xp->query('./div[contains(@class,"cal-times__spacer--head")]', $times)->length);
        $this->assertSame(1, $xp->query('./div[contains(@class,"cal-times__spacer--courts")]', $times)->length);

        $hours = $xp->query('//span[contains(@class,"cal-times__hour")]');
        $this->assertSame(12, $hours->length);
        $this->assertSame('08:00', $this->norm($hours->item(0)->textContent));
        $this->assertSame('19:00', $this->norm($hours->item(11)->textContent));
        $this->assertSame(12, $xp->query('./div[contains(@class,"cal-times__row")]', $times)->length);
        $this->assertSame('bis 09', $this->norm($this->element($xp, '//span[contains(@class,"cal-times__until")]')->textContent));

        $rows = $xp->query('//section[@data-day="1"]/div[contains(concat(" ", @class, " ")," cal-row ")]');
        $this->assertSame(12, $rows->length);
        foreach ($rows as $index => $row) {
            $this->assertSame((string) (8 + $index), $row->getAttribute('data-hour'));
            $first = $row->firstElementChild;
            $this->assertNotNull($first);
            $this->assertSame('span', $first->nodeName);
            $this->assertStringContainsString('cal-row__time', $first->getAttribute('class'));
            $this->assertSame(sprintf('%02d:00', 8 + $index), $this->norm($first->textContent));
        }
    }

    #[Test]
    public function every_row_has_one_slot_per_court(): void
    {
        $xp = $this->page();
        $rows = $xp->query('//section[@data-day="1"]/div[contains(concat(" ", @class, " ")," cal-row ")]');
        $this->assertSame(12, $rows->length);

        foreach ($rows as $row) {
            $slots = $this->slots($row);
            $this->assertCount(3, $slots, 'row '.$row->getAttribute('data-hour'));
            foreach ($slots as $slot) {
                $this->assertStringStartsWith('calendar-cell', $slot->getAttribute('class'));
            }
        }
    }

    #[Test]
    public function event_is_split_per_court_and_hour(): void
    {
        $this->event(null, '2026-11-04 14:00', '2026-11-04 16:00', 'Example Training');
        $this->event($this->squares[0]->sid, '2026-11-05 09:00', '2026-11-05 12:00', 'Example Camp');

        $xp = $this->page();

        $row14 = $this->row($xp, '1', 14);
        $row15 = $this->row($xp, '1', 15);
        $this->assertCount(3, $this->slots($row14));
        $this->assertCount(3, $this->slots($row15));

        $starts = $xp->query('./*[contains(@class,"event-cell--start")]', $row14);
        $this->assertSame(3, $starts->length);
        foreach ($starts as $start) {
            $this->assertStringContainsString('Example Training', $start->textContent);
        }
        $this->assertSame(3, $xp->query('./*[contains(@class,"event-cell--end")]', $row15)->length);

        foreach ([$row14, $row15] as $row) {
            foreach ($this->slots($row) as $slot) {
                $this->assertSame('event', $slot->getAttribute('data-state'));
                $this->assertStringContainsString('event-cell', $slot->getAttribute('class'));
            }
        }

        $parts = [];
        foreach ([9, 10, 11] as $hour) {
            $slot = $this->slots($this->row($xp, '2', $hour))[0];
            preg_match('/event-cell--(single|start|middle|end)\b/', $slot->getAttribute('class'), $match);
            $parts[] = $match[1] ?? null;
            $this->assertStringContainsString('Example Camp', $slot->textContent);
        }
        $this->assertSame(['start', 'middle', 'end'], $parts);

        $middleLabel = $this->element($xp, './/span[contains(@class,"event-label")]', $this->slots($this->row($xp, '2', 10))[0]);
        $this->assertSame('true', $middleLabel->getAttribute('aria-hidden'));
        $this->assertStringNotContainsString('event-cell', $this->slots($this->row($xp, '2', 9))[1]->getAttribute('class'));
    }

    #[Test]
    public function single_hour_event_is_a_single_part(): void
    {
        $this->event($this->squares[1]->sid, '2026-11-04 17:00', '2026-11-04 18:00', 'Example Hour');

        $xp = $this->page();
        $slot = $this->slots($this->row($xp, '1', 17))[1];

        $this->assertStringContainsString('event-cell--single', $slot->getAttribute('class'));
        $label = $this->element($xp, './/span[contains(@class,"event-label")]', $slot);
        $this->assertFalse($label->hasAttribute('aria-hidden'));
    }

    #[Test]
    public function old_day_window_script_is_gone(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);
        $this->actingAs($member)->get('/calendar?date=2026-11-04')
            ->assertOk()
            ->assertDontSee('--calendar-visible-width', false)
            ->assertDontSee('applyVisibleDays', false)
            ->assertDontSee('calendar-square-table', false)
            ->assertDontSee('has-calendar-layout', false);
    }
}
