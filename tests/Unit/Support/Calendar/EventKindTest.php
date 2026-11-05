<?php

namespace Tests\Unit\Support\Calendar;

use App\Models\Event;
use App\Models\EventMeta;
use App\Support\Calendar\EventKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventKindTest extends TestCase
{
    /** @param array<string, string> $meta */
    private function event(array $meta): Event
    {
        $event = new Event;
        $event->setRelation('meta', collect(array_map(
            fn (string $key, string $value) => new EventMeta(['key' => $key, 'value' => $value]),
            array_keys($meta),
            array_values($meta),
        )));

        return $event;
    }

    #[Test]
    public function stored_kind_wins_over_the_name(): void
    {
        $this->assertSame(EventKind::Event, EventKind::for($this->event(['name' => 'Platzsperre Nord', 'kind' => 'event'])));
        $this->assertSame(EventKind::Closure, EventKind::for($this->event(['name' => 'Club Day', 'kind' => 'closure'])));
    }

    /** @return array<string, array{string, EventKind}> */
    public static function names(): array
    {
        return [
            'closure word' => ['Platzsperre Court A', EventKind::Closure],
            'short word, any case' => ['sperre wegen Regen', EventKind::Closure],
            'plain event' => ['Example Training', EventKind::Event],
            'empty name' => ['', EventKind::Event],
        ];
    }

    #[Test]
    #[DataProvider('names')]
    public function name_is_the_fallback_without_a_stored_kind(string $name, EventKind $expected): void
    {
        $this->assertSame($expected, EventKind::for($this->event($name === '' ? [] : ['name' => $name])));
    }

    #[Test]
    public function an_unknown_stored_kind_falls_back_to_the_name(): void
    {
        $this->assertSame(EventKind::Closure, EventKind::for($this->event(['name' => 'Sperre', 'kind' => 'bogus'])));
        $this->assertSame(EventKind::Event, EventKind::for($this->event(['name' => 'Club Day', 'kind' => 'bogus'])));
    }

    #[Test]
    public function keywords_come_from_the_config(): void
    {
        config(['calendar.closure_keywords' => ['Maintenance']]);

        $this->assertSame(EventKind::Closure, EventKind::for($this->event(['name' => 'Court maintenance'])));
        $this->assertSame(EventKind::Event, EventKind::for($this->event(['name' => 'Platzsperre'])));
    }
}
