<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Services\RoundNotFutureException;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\FakeBeaconService;
use Tests\TestCase;

class FakeBeaconServiceTest extends TestCase
{
    #[Test]
    public function round_math(): void
    {
        $beacon = new FakeBeaconService(1000, 30);

        $this->assertInstanceOf(BeaconService::class, $beacon);
        $this->assertSame(['genesis_time' => 1000, 'period' => 30], $beacon->info());
        $this->assertSame(1, $beacon->roundAt(1000));
        $this->assertSame(2, $beacon->roundAt(1059));
        $this->assertSame(1060, $beacon->roundTime(3));
        $this->assertSame('drand:round:3', $beacon->sourceLabel(3));
    }

    #[Test]
    public function before_genesis_throws(): void
    {
        $this->expectExceptionObject(new RuntimeException('timestamp is before the chain genesis'));
        (new FakeBeaconService(1000, 30))->roundAt(10);
    }

    #[Test]
    public function publish(): void
    {
        $beacon = new FakeBeaconService(1000, 30, [4 => 'aa']);

        $this->assertSame(['round' => 4, 'randomness' => 'aa', 'signature' => ''], $beacon->round(4));
        $this->assertNull($beacon->round(5));

        $beacon->publish(5, 'bb');
        $this->assertSame('bb', $beacon->round(5)['randomness']);
    }

    #[Test]
    public function future_round_uses_the_test_clock(): void
    {
        Date::setTestNow(Date::createFromTimestamp(1059));
        $beacon = new FakeBeaconService(1000, 30);

        $this->assertTrue($beacon->assertFutureRound(3));
        $this->expectException(RoundNotFutureException::class);
        $beacon->assertFutureRound(2);
    }

    #[Test]
    public function an_unreachable_fake_throws_like_the_client(): void
    {
        $beacon = new FakeBeaconService;
        $beacon->unreachable = true;

        $this->expectException(RuntimeException::class);
        $beacon->roundAt(time());
    }
}
