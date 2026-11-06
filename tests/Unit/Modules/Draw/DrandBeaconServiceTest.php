<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Services\DrandBeaconService;
use App\Modules\Draw\Services\RoundNotFutureException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DrandBeaconServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['draw.beacon.endpoints' => ['https://beacon-one.test', 'https://beacon-two.test']]);
        Http::preventStrayRequests();
    }

    private function service(): DrandBeaconService
    {
        return new DrandBeaconService;
    }

    private function fakeInfo(int $genesis = 1000, int $period = 30): void
    {
        Http::fake(['beacon-one.test/info' => Http::response(['genesis_time' => $genesis, 'period' => $period])]);
    }

    #[Test]
    public function the_container_binds_the_drand_client_with_the_zf2_defaults(): void
    {
        $this->assertInstanceOf(DrandBeaconService::class, app(BeaconService::class));
        $this->assertSame(app(BeaconService::class), app(BeaconService::class));

        $defaults = require app_path('Modules/Draw/config/draw.php');
        $this->assertSame(['https://api.drand.sh', 'https://drand.cloudflare.com'], $defaults['beacon']['endpoints']);
        $this->assertSame(10, $defaults['beacon']['timeout']);
    }

    #[Test]
    public function round_math(): void
    {
        $this->fakeInfo();
        $beacon = $this->service();

        $this->assertSame(1, $beacon->roundAt(1000));
        $this->assertSame(2, $beacon->roundAt(1059));
        $this->assertSame(1060, $beacon->roundTime(3));
        $this->assertSame('drand:round:42', $beacon->sourceLabel(42));
    }

    #[Test]
    public function timestamp_before_genesis_throws(): void
    {
        $this->fakeInfo();

        $this->expectExceptionObject(new RuntimeException('timestamp is before the chain genesis'));
        $this->service()->roundAt(999);
    }

    #[Test]
    public function info_without_genesis_or_period_throws(): void
    {
        Http::fake(['beacon-one.test/info' => Http::response(['period' => 30])]);

        $this->expectExceptionObject(new RuntimeException('drand /info response is missing genesis_time or period'));
        $this->service()->info();
    }

    #[Test]
    public function falls_back_to_the_second_endpoint(): void
    {
        Http::fake([
            'beacon-one.test/*' => Http::response('down', 503),
            'beacon-two.test/info' => Http::response(['genesis_time' => 1000, 'period' => 30]),
            'beacon-two.test/public/5' => Http::response(['round' => 5, 'randomness' => str_repeat('ab', 32), 'signature' => 'sig']),
        ]);

        $beacon = $this->service();
        $this->assertSame(1, $beacon->roundAt(1000));
        $this->assertSame(['round' => 5, 'randomness' => str_repeat('ab', 32), 'signature' => 'sig'], $beacon->round(5));
    }

    #[Test]
    public function invalid_json_falls_through_to_the_next_endpoint(): void
    {
        Http::fake([
            'beacon-one.test/info' => Http::response('<html>', 200),
            'beacon-two.test/info' => Http::response(['genesis_time' => 1000, 'period' => 30]),
        ]);

        $this->assertSame(1060, $this->service()->roundTime(3));
    }

    #[Test]
    public function unpublished_round_returns_null(): void
    {
        Http::fake([
            'beacon-one.test/public/*' => Http::response('not found', 404),
            'beacon-two.test/public/*' => Http::response('not found', 404),
        ]);

        $this->assertNull($this->service()->round(99));
    }

    #[Test]
    public function round_without_randomness_returns_null_and_a_missing_signature_is_empty(): void
    {
        Http::fake([
            'beacon-one.test/public/7' => Http::response(['round' => 7]),
            'beacon-one.test/public/8' => Http::response(['round' => 8, 'randomness' => 'cd']),
        ]);

        $this->assertNull($this->service()->round(7));
        $this->assertSame(['round' => 8, 'randomness' => 'cd', 'signature' => ''], $this->service()->round(8));
    }

    #[Test]
    public function mismatched_round_throws(): void
    {
        Http::fake(['beacon-one.test/public/5' => Http::response(['round' => 6, 'randomness' => 'ab'])]);

        $this->expectExceptionObject(new RuntimeException('drand returned round 6 instead of the requested 5'));
        $this->service()->round(5);
    }

    #[Test]
    public function assert_future_round_rejects_the_current_round(): void
    {
        $this->fakeInfo();
        Date::setTestNow(Date::createFromTimestamp(1059)); // round 2

        $beacon = $this->service();
        $this->assertTrue($beacon->assertFutureRound(3));

        try {
            $beacon->assertFutureRound(2);
            $this->fail('current round accepted');
        } catch (RoundNotFutureException $e) {
            $this->assertSame('drand-Runde 2 ist nicht zukünftig (aktuelle Runde: 2).', $e->getMessage());
            $this->assertSame(2, $e->round);
            $this->assertSame(2, $e->current);
        }
    }

    #[Test]
    public function uses_a_ten_second_timeout_and_neutral_user_agent(): void
    {
        $options = null;
        Http::fake(function (Request $request, array $o) use (&$options) {
            $options = $o;

            return Http::response(['genesis_time' => 1000, 'period' => 30]);
        });

        $this->service()->info();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://beacon-one.test/info'
            && $request->header('User-Agent') === ['draw-beacon/1.0']
            && $request->header('Accept') === ['application/json']);
        $this->assertSame(10, $options['timeout']);
        $this->assertSame(10, $options['connect_timeout']);
    }

    #[Test]
    public function info_is_fetched_once_per_instance(): void
    {
        $this->fakeInfo();
        $beacon = $this->service();

        $beacon->roundAt(1000);
        $beacon->roundTime(5);
        $beacon->roundAt(2000);

        Http::assertSentCount(1);
    }

    #[Test]
    public function no_reachable_endpoint_throws(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(RuntimeException::class);
        $this->service()->info();
    }
}
