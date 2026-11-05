<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Pyramid;

use App\Modules\Pyramid\Services\ScheduleParser;
use App\Modules\Pyramid\Support\Verdict;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ScheduleParserTest extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        date_default_timezone_set('Europe/Vienna');
        $this->now = CarbonImmutable::parse('2026-11-04 10:00:00', 'Europe/Vienna');
    }

    private function parser(): ScheduleParser
    {
        return new ScheduleParser;
    }

    #[Test]
    public function accepts_german_and_iso_input(): void
    {
        $expected = strtotime('2026-08-14 17:00');
        $now = CarbonImmutable::parse('2026-08-01 10:00:00', 'Europe/Vienna');

        $this->assertSame($expected, $this->parser()->forSchedule('14.08.2026 17:00', $now));
        $this->assertSame($expected, $this->parser()->forSchedule(' 2026-08-14T17:00 ', $now));
        $this->assertSame(strtotime('2026-08-14'), $this->parser()->forSchedule('2026-08-14', $now));
    }

    #[Test]
    public function rejects_past_and_far_dates(): void
    {
        $past = $this->parser()->forSchedule('03.11.2026 10:00', $this->now);
        $far = $this->parser()->forSchedule('05.11.2027 10:00', $this->now);

        $this->assertInstanceOf(Verdict::class, $past);
        $this->assertSame('pyramid::check.schedule_past', $past->key);
        $this->assertInstanceOf(Verdict::class, $far);
        $this->assertSame('pyramid::check.schedule_far', $far->key);
        $this->assertIsInt($this->parser()->forSchedule('04.11.2027 10:00', $this->now));
    }

    #[Test]
    public function deadline_has_no_past_check(): void
    {
        $this->assertSame(strtotime('2026-10-01 23:59'), $this->parser()->forDeadline('2026-10-01', $this->now));
        $this->assertSame(strtotime('2026-11-20 23:59'), $this->parser()->forDeadline('20.11.2026', $this->now));

        $far = $this->parser()->forDeadline('2027-12-01', $this->now);
        $this->assertSame('pyramid::check.deadline_far', $far->key);
    }

    #[Test]
    public function garbage_is_invalid(): void
    {
        $this->assertSame('pyramid::check.schedule_invalid', $this->parser()->forSchedule('bald', $this->now)->key);
        $this->assertSame('pyramid::check.schedule_invalid', $this->parser()->forSchedule('  ', $this->now)->key);
        $this->assertSame('pyramid::check.deadline_invalid', $this->parser()->forDeadline('nonsense', $this->now)->key);
    }
}
