<?php

namespace Tests\Unit\Support\Ui;

use App\Support\Ui\BadgeTone;
use App\Support\Ui\UserStatusTone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserStatusToneTest extends TestCase
{
    /**
     * @return array<string, array{string, BadgeTone}>
     */
    public static function statuses(): array
    {
        return [
            'admin' => ['admin', BadgeTone::Good],
            'assist' => ['assist', BadgeTone::Good],
            'enabled' => ['enabled', BadgeTone::Good],
            'blocked' => ['blocked', BadgeTone::Problem],
            'deleted' => ['deleted', BadgeTone::Problem],
            'disabled' => ['disabled', BadgeTone::Neutral],
            'placeholder' => ['placeholder', BadgeTone::Neutral],
            'unknown' => ['unknown', BadgeTone::Neutral],
        ];
    }

    #[Test]
    #[DataProvider('statuses')]
    public function status_maps_to_tone(string $status, BadgeTone $expected): void
    {
        $this->assertSame($expected, UserStatusTone::for($status));
    }
}
