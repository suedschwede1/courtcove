<?php

namespace Tests\Unit\Support\Ui;

use App\Support\Ui\BadgeTone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeToneTest extends TestCase
{
    /** @return array<string, array{string, BadgeTone}> */
    public static function statuses(): array
    {
        $map = [
            'good' => ['active', 'played', 'confirmed', 'done', 'running'],
            'neutral' => ['scheduled', 'date_agreed', 'paused', 'subscription'],
            'attention' => ['no_date', 'open', 'still_open', 'pending_approval'],
            'problem' => ['cancelled', 'blocked', 'rejected', 'lost'],
            'you' => ['you'],
        ];

        $cases = [];
        foreach ($map as $tone => $keys) {
            foreach ($keys as $key) {
                $cases[$key] = [$key, BadgeTone::from($tone)];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('statuses')]
    public function status_maps_to_tone(string $status, BadgeTone $expected): void
    {
        $this->assertSame($expected, BadgeTone::forStatus($status));
    }

    #[Test]
    public function all_eighteen_statuses_are_covered(): void
    {
        $this->assertCount(18, self::statuses());
    }

    #[Test]
    public function unknown_status_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No badge tone for status [nope]; pass tone explicitly.');

        BadgeTone::forStatus('nope');
    }

    #[Test]
    public function every_status_has_a_label_in_both_locales(): void
    {
        foreach (array_keys(self::statuses()) as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertNotSame('booking.ui.status.'.$key, trans('booking.ui.status.'.$key, [], $locale), "{$key} / {$locale}");
            }
        }
    }
}
