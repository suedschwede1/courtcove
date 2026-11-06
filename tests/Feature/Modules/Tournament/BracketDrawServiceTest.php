<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawVerifier;
use App\Modules\Draw\Services\PayloadGuard;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Services\Draws\BracketDrawService;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\Support\FakeBeaconService;
use Tests\TestCase;

class BracketDrawServiceTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    private const GENESIS = 1700000000;

    /** Rule and title formats exactly as the old AdminController::prepareBracketDrawAction wrote them. */
    private const ZF2_RULE = '%s-Pool %s: Die Kennungen werden den vorab festgelegten Rasterlinien zugelost.';

    private const ZF2_TITLE = '%s – %s, Pool %s';

    private FakeBeaconService $beacon;

    private User $boss;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beacon = new FakeBeaconService(self::GENESIS, 30);
        $this->app->scoped(BeaconService::class, fn (): FakeBeaconService => $this->beacon); // survives forgetScopedInstances()
        Date::setTestNow(Date::createFromTimestamp(self::GENESIS + 999 * 30)); // current round 1000
        $this->boss = User::factory()->create(['status' => 'admin']);
    }

    private function playGroupPhase(): void
    {
        foreach (TournamentMatch::query()->where('tid', $this->tournament->tid)->whereNotNull('grp')->pluck('mkey') as $mkey) {
            $this->play((string) $mkey);
        }
    }

    /** @return array{draw: Draw, flashes: list<array{type: string, text: string}>} */
    private function prepare(string $pool = 'w', int $round = 0, int $lead = 2): array
    {
        return $this->app->make(BracketDrawService::class)->prepare($this->tournament->fresh(), 'KO', $pool, $round, $lead, $this->boss);
    }

    private function refusal(callable $call): ActionRefused
    {
        try {
            $call();
        } catch (ActionRefused $e) {
            return $e;
        }

        $this->fail('expected a refusal');
    }

    #[Test]
    public function bracket_rule_and_titles_are_byte_identical_to_zf2(): void
    {
        $this->championship();
        $this->playGroupPhase();

        $result = $this->prepare('w', 0, 15);
        $draw = $result['draw'];
        $payload = json_decode($draw->payload, true);

        $this->assertSame(sprintf(self::ZF2_RULE, 'KO', 'w'), $payload['rule']);
        $this->assertSame(sprintf(self::ZF2_RULE, 'KO', 'w'), sprintf(BracketDrawService::RULE, 'KO', 'w'));
        $this->assertSame(sprintf(self::ZF2_TITLE, 'Demo-Meisterschaft', 'KO-Runde', 'W'), $draw->title);
        $this->assertSame('Demo-Meisterschaft – KO-Runde, Pool W', $draw->title);
        $this->assertSame(['W3', 'W4'], $payload['entries']);
        $this->assertSame(['KO:2', 'KO:3'], $payload['slots']);
        $this->assertSame('direct', $draw->mode);
        $this->assertSame('committed', $draw->status);
        $this->assertNull($draw->pick_deadline);
        $this->assertSame('drand:round:'.$this->beacon->roundAt(now()->getTimestamp() + 900), $draw->beacon_source);
        $this->assertSame('KO-Ziehung angelegt. Gezogen werden kann ab '.date('d.m.Y H:i', $this->beacon->roundTime($this->beacon->roundAt(now()->getTimestamp() + 900))).' (drand-Runde '.$this->beacon->roundAt(now()->getTimestamp() + 900).'). Bis dahin den Protokoll-Link ankündigen.', $result['flashes'][0]['text']);
    }

    #[Test]
    public function bracket_post_timing_waits_for_the_group_phase(): void
    {
        $this->championship();
        $this->play('A-0');

        $e = $this->refusal(fn () => $this->prepare());

        $this->assertSame('Diese Ziehung darf erst nach Abschluss der Gruppenphase angelegt werden.', __($e->key, $e->replace));
        $this->assertSame(0, Draw::query()->count());
    }

    #[Test]
    public function pool_lines_and_codes_must_be_complete(): void
    {
        $this->championship();
        $this->playGroupPhase();

        foreach (['x', '', 'W'] as $pool) {
            $this->assertSame('tournament::admin.prepare.bracket_incomplete', $this->refusal(fn () => $this->prepare($pool))->key);
        }

        $e = $this->refusal(fn () => $this->app->make(BracketDrawService::class)->prepare($this->tournament, 'KO_X', 'w', 0, 2, $this->boss));
        $this->assertSame('phase "KO_X" is not defined', __($e->key, $e->replace));
        $this->assertSame(0, Draw::query()->count());
    }

    #[Test]
    public function real_tournament_needs_a_future_round(): void
    {
        $this->championship();
        $this->playGroupPhase();

        $e = $this->refusal(fn () => $this->prepare('w', 990));

        $this->assertSame('draw::admin.round_not_future', $e->key);
        $this->assertSame(0, Draw::query()->count());
    }

    #[Test]
    public function test_bracket_draw_is_executed_and_applied(): void
    {
        $this->championship([], ['is_test' => 1, 'active' => 0]);
        $this->playGroupPhase();
        $round = $this->beacon->roundAt(now()->getTimestamp() - 120);
        $this->beacon->publish($round, str_repeat('12', 32));

        $result = $this->prepare();
        $draw = Draw::query()->findOrFail($result['draw']->did);

        $this->assertSame('executed', $draw->status);
        $this->assertNotNull($draw->final_seed);
        $this->assertNotNull($draw->applied_at);
        $this->assertSame('drand:round:'.$round, $draw->beacon_source);

        $placeholders = TournamentLine::query()->where('tid', $this->tournament->tid)->where('phase', 'KO')->where('kind', 'pool')
            ->orderBy('line')->pluck('placeholder', 'line')->all();
        $this->assertEqualsCanonicalizing(['W3', 'W4'], array_values($placeholders));
        $this->assertSame($draw->resultArray()['KO:2'], $placeholders[2]);
        $this->assertSame($draw->resultArray()['KO:3'], $placeholders[3]);

        $texts = array_column($result['flashes'], 'text');
        $this->assertContains('2 Zuordnungen atomar angewendet.', $texts);
        $this->assertSame('Testturnier: gezogen und angewendet, die Platzhalter stehen im Raster.', end($texts));

        $this->assertTrue($this->app->make(DrawVerifier::class)->verify($draw)->ok());
        $this->app->make(PayloadGuard::class)->assertVerifierSafe($draw->payloadArray());
    }

    #[Test]
    public function the_route_prepares_and_refuses_back_to_the_admin_page(): void
    {
        $this->championship();

        $this->actingAs($this->boss)->post(route('tournament.admin.prepare-bracket-draw'), ['phase' => 'KO', 'pool' => 'w'])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'Diese Ziehung darf erst nach Abschluss der Gruppenphase angelegt werden.');

        $this->actingAs($this->boss)->post(route('tournament.admin.prepare-bracket-draw'), ['phase' => ['KO'], 'pool' => ['w'], 'lead_minutes' => ['x']])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'phase "" is not defined');

        $this->playGroupPhase();
        $this->actingAs($this->boss)->post(route('tournament.admin.prepare-bracket-draw'), ['phase' => 'KO', 'pool' => 'w', 'lead_minutes' => '30'])
            ->assertRedirect(route('draw.index'));
        $this->assertStringStartsWith('KO-Ziehung angelegt.', ModuleFlash::all()[0]['text']);
        $this->assertSame('drand:round:'.$this->beacon->roundAt(now()->getTimestamp() + 1800), Draw::query()->value('beacon_source'));
    }

    #[Test]
    public function a_failed_test_bracket_execution_shows_no_raw_exception_text(): void
    {
        $this->championship([], ['is_test' => 1, 'active' => 0]);
        $this->playGroupPhase();
        $round = $this->beacon->roundAt(now()->getTimestamp() - 120);
        $this->beacon->publish($round, str_repeat('12', 32));
        Log::spy();
        DB::listen(function (QueryExecuted $q): void {
            if (str_starts_with($q->sql, 'update "bs_draws" set')) {
                throw new \RuntimeException('SQLSTATE[HY000]: General error: secret_table');
            }
        });

        $result = $this->prepare();

        $last = end($result['flashes']);
        $this->assertSame('error', $last['type']);
        $this->assertStringEndsWith('interner Fehler, Details stehen im Fehlerprotokoll', $last['text']);
        $this->assertStringNotContainsString('SQLSTATE', $last['text']);
        Log::shouldHaveReceived('error')->once();
    }
}
