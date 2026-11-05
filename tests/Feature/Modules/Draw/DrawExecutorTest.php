<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawExecutor;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\DrawVerifier;
use App\Modules\TournamentBase\Support\ActionRefused;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\FakeBeaconService;
use Tests\TestCase;

class DrawExecutorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const GENESIS = 1700000000;

    private FakeBeaconService $beacon;

    private string $randomness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beacon = new FakeBeaconService(self::GENESIS, 30);
        $this->app->instance(BeaconService::class, $this->beacon);
        $this->randomness = str_repeat('5e', 32);
        $this->beacon->publish(1000, $this->randomness); // factory draws announce drand:round:1000
        Date::setTestNow(Date::createFromTimestamp($this->beacon->roundTime(1000) + 60));
    }

    private function executor(): DrawExecutor
    {
        return app(DrawExecutor::class);
    }

    private function refusal(Draw $draw): ActionRefused
    {
        try {
            $this->executor()->execute($draw);
        } catch (ActionRefused $e) {
            return $e;
        }

        $this->fail('no refusal');
    }

    /** @return array<string, mixed> */
    private function snapshot(Draw $draw): array
    {
        return [
            Draw::query()->findOrFail($draw->did)->getAttributes(),
            DrawToken::query()->where('did', $draw->did)->orderBy('dtid')->get()->map->getAttributes()->all(),
        ];
    }

    #[Test]
    public function direct_draw_executes_and_verifies(): void
    {
        $draw = Draw::factory()->create();

        $flash = $this->executor()->execute($draw);

        $fresh = Draw::query()->findOrFail($draw->did);
        $this->assertSame('executed', $fresh->status);
        $this->assertSame($this->randomness, $fresh->beacon_value);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $fresh->executed);
        $expected = app(DrawService::class)->execute($fresh->payloadArray(), (string) $fresh->server_seed, $this->randomness);
        $this->assertSame($expected['final_seed'], $fresh->final_seed);
        $this->assertSame(json_encode($expected['assignment'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $fresh->result);
        $this->assertTrue(app(DrawVerifier::class)->verify($fresh)->ok());
        $this->assertTrue($fresh->isRevealed());
        $this->assertSame('Ziehung ausgeführt. Protokoll unter '.route('draw.record', ['slug' => $fresh->slug]), $flash);
    }

    #[Test]
    public function token_draw_goes_to_picking_with_slots_on_tokens(): void
    {
        $draw = Draw::factory()->token()->create();

        $flash = $this->executor()->execute($draw);

        $fresh = Draw::query()->findOrFail($draw->did);
        $this->assertSame('picking', $fresh->status);
        $this->assertFalse($fresh->isRevealed());
        $this->assertTrue(app(DrawVerifier::class)->verify($fresh)->ok());

        $byToken = array_flip($fresh->resultArray());
        $tokens = DrawToken::query()->where('did', $draw->did)->get();
        $this->assertCount(6, $tokens);
        foreach ($tokens as $token) {
            $this->assertSame((string) $byToken[$token->token], $token->slot, $token->token);
            $this->assertNull($token->claimed_tpid);
        }
        $this->assertStringStartsWith('Gezogen und Wahlphase offen. Jeder Spieler erfährt seine Gruppe beim Klick;', $flash);
    }

    #[Test]
    public function second_execute_is_refused(): void
    {
        $draw = Draw::factory()->token()->create();
        $this->executor()->execute($draw);
        $before = $this->snapshot($draw);

        $e = $this->refusal(Draw::query()->findOrFail($draw->did));

        $this->assertSame('draw::admin.execute.already', $e->key);
        $this->assertSame('Diese Ziehung wurde bereits ausgeführt und kann nicht wiederholt werden.', __($e->key, $e->replace));
        $this->assertSame($before, $this->snapshot($draw));
    }

    #[Test]
    public function discarded_draw_is_refused(): void
    {
        $e = $this->refusal(Draw::factory()->discarded()->create());

        $this->assertSame('Ziehung nicht gefunden oder verworfen.', __($e->key, $e->replace));
    }

    #[Test]
    public function token_draw_outside_committed_is_refused_with_the_order_text(): void
    {
        $draw = Draw::factory()->token()->create(['status' => 'picking']);

        $e = $this->refusal($draw);

        $this->assertSame('Ausführen geht nur direkt nach dem Festschreiben (Zustand jetzt: picking). Reihenfolge: festschreiben, ausführen, Wahlphase öffnen, schließen.', __($e->key, $e->replace));
    }

    #[Test]
    public function unpublished_round_refuses_with_wait_hint(): void
    {
        $draw = Draw::factory()->create(['beacon_source' => 'drand:round:2000']);
        $fallsAt = $this->beacon->roundTime(2000);
        $time = date('H:i:s', $fallsAt);

        Date::setTestNow(Date::createFromTimestamp($fallsAt - 45));
        $e = $this->refusal($draw);
        $this->assertSame('drand-Runde 2000 ist noch nicht veröffentlicht. Die Ziehung ist ab '.$time.' möglich, noch 45 Sekunden. Auf eine andere Quelle auszuweichen würde das Protokoll brechen.', __($e->key, $e->replace));

        Date::setTestNow(Date::createFromTimestamp($fallsAt - 610));
        $e = $this->refusal($draw);
        $this->assertSame('drand-Runde 2000 ist noch nicht veröffentlicht. Die Ziehung ist ab '.$time.' möglich, noch etwa 10 Minuten. Auf eine andere Quelle auszuweichen würde das Protokoll brechen.', __($e->key, $e->replace));

        Date::setTestNow(Date::createFromTimestamp($fallsAt + 5));
        $e = $this->refusal($draw);
        $this->assertSame('drand-Runde 2000 ist noch nicht veröffentlicht. Die Runde ist seit '.$time.' fällig - bitte gleich nochmals versuchen. Auf eine andere Quelle auszuweichen würde das Protokoll brechen.', __($e->key, $e->replace));

        $this->beacon->unreachable = true;
        $e = $this->refusal($draw);
        $this->assertSame('drand-Runde 2000 ist noch nicht veröffentlicht. Auf eine andere Quelle auszuweichen würde das Protokoll brechen.', __($e->key, $e->replace));

        $this->assertNull(Draw::query()->findOrFail($draw->did)->final_seed);
    }

    #[Test]
    public function unknown_beacon_source_refuses(): void
    {
        $e = $this->refusal(Draw::factory()->create(['beacon_source' => 'lotto:2026-11-08']));

        $this->assertSame('Unbekannte Beacon-Quelle "lotto:2026-11-08" - hier ist eine manuelle Eingabe nötig.', __($e->key, $e->replace));
    }

    #[Test]
    public function tampered_payload_throws_and_writes_nothing(): void
    {
        $draw = Draw::factory()->token()->create();
        Draw::query()->whereKey($draw->did)->update(['payload' => str_replace('Testregel', 'Andere Regel', $draw->payload)]);
        $before = $this->snapshot($draw);

        try {
            $this->executor()->execute(Draw::query()->findOrFail($draw->did));
            $this->fail('no exception');
        } catch (RuntimeException $e) {
            $this->assertNotInstanceOf(ActionRefused::class, $e);
            $this->assertSame('commitment mismatch - payload or seed was altered after the commit', $e->getMessage());
        }
        $this->assertSame($before, $this->snapshot($draw));

        Draw::query()->whereKey($draw->did)->update(['payload' => 'not json']);
        $this->expectExceptionObject(new RuntimeException('stored payload is not valid JSON'));
        $this->executor()->execute(Draw::query()->findOrFail($draw->did));
    }

    #[Test]
    public function token_count_mismatch_rolls_back(): void
    {
        $draw = Draw::factory()->token()->create();
        DrawToken::factory()->create(['did' => $draw->did, 'position' => 7, 'token' => 'XX-99']);
        $before = $this->snapshot($draw);

        try {
            $this->executor()->execute($draw);
            $this->fail('no exception');
        } catch (RuntimeException $e) {
            $this->assertSame('1 Token(s) haben keinen Slot bekommen - Tokenliste und Payload passen nicht zusammen.', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot($draw));
    }
}
