<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\DrawVerifier;
use App\Modules\Draw\Services\PayloadGuard;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\Draws\TokenDrawService;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\FakeBeaconService;
use Tests\TestCase;

class TokenDrawServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const GENESIS = 1700000000;

    /** The token rule exactly as the old AdminController::prepareDrawAction wrote it. */
    private const ZF2_RULE = 'Jeder Spieler im Lostopf waehlt einen Token. Die Slot-Liste wird mit dem Zufall der drand-Runde %u gemischt; Token an Position i erhaelt den Slot an Position i der gemischten Liste. Wer nicht waehlt, bekommt beim Schliessen einen freien Token: freie Tokens werden nach Position geordnet, Nichtwaehler nach Teilnehmernummer geordnet und anschliessend mit dem finalen Seed reproduzierbar gemischt.';

    private FakeBeaconService $beacon;

    private Tournament $tournament;

    /** @var array<int, int> seed => tpid */
    private array $tpid = [];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beacon = new FakeBeaconService(self::GENESIS, 30);
        $this->app->scoped(BeaconService::class, fn (): FakeBeaconService => $this->beacon); // survives forgetScopedInstances()
        Date::setTestNow(Date::createFromTimestamp(self::GENESIS + 999 * 30)); // current round 1000
        $this->admin = User::factory()->create(['status' => 'admin']);
    }

    /**
     * Format small-two-groups with the group phase key AC: heads seed 1 (A1) and seed 2 (B1), seeds 3 to 6 in
     * the pot for the slots A2, B2, A3, B3.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function potTournament(array $attributes = []): Tournament
    {
        $json = str_replace('"G1"', '"AC"', (string) file_get_contents(base_path('tests/Fixtures/tournament/formats/small-two-groups.json')));
        $this->tournament = Tournament::factory()->active()->withFormat(json_decode($json, true))->create($attributes);
        $this->tpid = [];

        for ($seed = 1; $seed <= 6; $seed++) {
            $this->tpid[$seed] = (int) TournamentPlayer::factory()->create([
                'tid' => $this->tournament->tid,
                'label' => sprintf('Demo Spieler %02d', $seed),
                'seed' => $seed,
                'grp' => $seed <= 2 ? ($seed === 1 ? 'A' : 'B') : null,
                'grp_pos' => $seed <= 2 ? 1 : null,
            ])->tpid;
        }

        return $this->tournament;
    }

    /** @return array{draw: Draw, flashes: list<array{type: string, text: string}>} */
    private function prepare(int $round = 0, string $deadline = '', int $lead = 2, int $in = 0): array
    {
        return $this->app->make(TokenDrawService::class)->prepare($this->tournament, 'AC', $round, $deadline, $lead, $in, $this->admin);
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
    public function token_rule_text_is_byte_identical_to_zf2(): void
    {
        $this->potTournament();

        $draw = $this->prepare(1200)['draw'];

        $payload = json_decode($draw->payload, true);
        $this->assertSame(sprintf(self::ZF2_RULE, 1200), $payload['rule']);
        $this->assertSame(sprintf(self::ZF2_RULE, 1200), sprintf(TokenDrawService::RULE, 1200));
        $this->assertSame(['A2', 'B2', 'A3', 'B3'], $payload['slots']);
        $this->assertSame('Demo-Meisterschaft - Auslosung Gruppenphase', $draw->title);
        $this->assertSame('drand:round:1200', $draw->beacon_source);
        $this->assertSame('committed', $draw->status);
        $this->assertSame('token', $draw->mode);
        $this->assertNull($draw->pick_deadline);
        $this->assertSame((int) $this->admin->uid, (int) $draw->created_uid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $draw->slug);
        $this->assertSame($draw->payload, (new DrawService)->canonical($payload));
    }

    #[Test]
    public function real_tournament_needs_a_future_round(): void
    {
        $this->potTournament();

        $e = $this->refusal(fn () => $this->prepare(900));

        $this->assertSame('draw::admin.round_not_future', $e->key);
        $this->assertSame(['round' => 900, 'current' => 1000], $e->replace);
        $this->assertSame(0, Draw::query()->count());

        // Without a round: the round of tomorrow.
        $draw = $this->prepare()['draw'];
        $this->assertSame('drand:round:'.(1000 + 2880), $draw->beacon_source);
    }

    #[Test]
    public function deadline_must_follow_the_draw_by_five_minutes(): void
    {
        $this->potTournament();
        $now = now()->getTimestamp();
        $round = $this->beacon->roundAt($now + 120);
        $drawAt = $this->beacon->roundTime($round);

        $e = $this->refusal(fn () => $this->prepare(0, date('Y-m-d\TH:i', $drawAt + 240)));

        $this->assertSame('tournament::admin.prepare.deadline_too_early', $e->key);
        $this->assertSame(
            'Die Wahlfrist muss nach dem Wahlbeginn liegen. Gezogen werden kann ab '.date('d.m.Y H:i', $drawAt).' (drand-Runde '.$round.', 2 Minuten Vorlauf). Setze die Frist mindestens auf '.date('d.m.Y H:i', $drawAt + 900).'.',
            __($e->key, $e->replace),
        );
        $this->assertSame(0, Draw::query()->count());

        // A valid deadline is normalised; the round is the one after the lead time.
        $draw = $this->prepare(0, date('Y-m-d\TH:i', $drawAt + 3600), 15)['draw'];
        $this->assertSame(date('Y-m-d H:i:00', $drawAt + 3600), $draw->pick_deadline);
        $this->assertSame('drand:round:'.$this->beacon->roundAt($now + 900), $draw->beacon_source);
    }

    #[Test]
    public function quick_deadlines_and_invalid_dates(): void
    {
        $this->potTournament();
        $now = now()->getTimestamp();

        $draw = $this->prepare(0, '', 2, 120)['draw'];
        $this->assertSame(date('Y-m-d H:i:s', $now + 7200), $draw->pick_deadline);

        $this->assertSame('tournament::admin.prepare.invalid_deadline', $this->refusal(fn () => $this->prepare(0, 'kein Datum'))->key);
        $this->assertSame('tournament::admin.prepare.invalid_deadline', $this->refusal(fn () => $this->prepare(1200, 'kein Datum'))->key);
        $this->assertSame(1, Draw::query()->count());
    }

    #[Test]
    public function beacon_failure_refuses_with_the_manual_round_hint(): void
    {
        $this->potTournament();
        $this->beacon->unreachable = true;

        $e = $this->refusal(fn () => $this->prepare(0, date('Y-m-d H:i', now()->getTimestamp() + 7200)));
        $this->assertSame('drand nicht erreichbar, Rundennummer bitte manuell angeben: no drand endpoint reachable', __($e->key, $e->replace));
        $this->assertSame(0, Draw::query()->count());
    }

    #[Test]
    public function pool_and_slots_must_match(): void
    {
        $this->potTournament();
        TournamentPlayer::query()->whereKey($this->tpid[3])->update(['grp' => 'A', 'grp_pos' => 2]);

        $e = $this->refusal(fn () => $this->prepare(1200));

        $this->assertSame('Lostopf (3 Spieler) und offene Gruppenplätze (4) passen nicht zusammen. Sind die Gruppenköpfe schon zugeteilt?', __($e->key, $e->replace));
        $this->assertSame(0, Draw::query()->count());

        $this->assertSame('phase "XX" is not defined', __(...$this->keyAndReplace($this->refusal(fn () => $this->app->make(TokenDrawService::class)->prepare($this->tournament, 'XX', 1200, '', 2, 0, $this->admin)))));
    }

    #[Test]
    public function test_tournament_executes_immediately_into_picking(): void
    {
        $this->potTournament(['is_test' => 1, 'active' => 0]);
        $round = $this->beacon->roundAt(now()->getTimestamp() - 120);
        $this->beacon->publish($round, str_repeat('cd', 32));

        $result = $this->prepare(1200, '', 2, 30);
        $draw = $result['draw'];

        $this->assertSame('picking', $draw->status);
        $this->assertNotNull($draw->final_seed);
        $this->assertSame('drand:round:'.$round, $draw->beacon_source);
        $this->assertSame(4, DrawToken::query()->where('did', $draw->did)->whereNotNull('slot')->count());
        $this->assertSame('success', $result['flashes'][0]['type']);
        $this->assertStringStartsWith('Auslosung angelegt: 4 Tokens, 4 Berechtigte.', $result['flashes'][0]['text']);
        $this->assertStringContainsString(route('draw.record', ['slug' => $draw->slug]), $result['flashes'][0]['text']);
        $this->assertSame('Testturnier: gezogen und Wahlphase offen. Jeder Spieler erfährt seine Gruppe beim Klick.', end($result['flashes'])['text']);

        // An unpublished round only flashes: the draw stays committed.
        $this->potTournament(['is_test' => 1, 'active' => 0]);
        Date::setTestNow(now()->addMinutes(10));
        $result = $this->prepare();
        $this->assertSame('committed', $result['draw']->status);
        $this->assertSame('error', end($result['flashes'])['type']);
        $this->assertStringStartsWith('Anlegen hat geklappt, das Ziehen nicht: ', end($result['flashes'])['text']);
    }

    #[Test]
    public function token_draw_insert_is_atomic(): void
    {
        $this->potTournament();
        DrawEligible::creating(function (): void {
            throw new RuntimeException('Berechtigung abgelehnt');
        });

        try {
            $this->prepare(1200);
            $this->fail('the eligible insert must throw');
        } catch (RuntimeException $e) {
            $this->assertSame('Berechtigung abgelehnt', $e->getMessage());
        }

        $this->assertSame(0, Draw::query()->count());
        $this->assertSame(0, DrawToken::query()->count());
        $this->assertSame(0, DrawEligible::query()->count());
    }

    #[Test]
    public function tokens_carry_the_phase_prefix(): void
    {
        $this->potTournament();

        $draw = $this->prepare(1200)['draw'];

        $this->assertSame(
            [[1, 'AC-01'], [2, 'AC-02'], [3, 'AC-03'], [4, 'AC-04']],
            DrawToken::query()->where('did', $draw->did)->orderBy('position')->get()->map(fn ($t) => [(int) $t->position, $t->token])->all(),
        );
        $this->assertSame(['AC-01', 'AC-02', 'AC-03', 'AC-04'], json_decode($draw->payload, true)['entries']);
        $this->assertEqualsCanonicalizing(
            [$this->tpid[3], $this->tpid[4], $this->tpid[5], $this->tpid[6]],
            DrawEligible::query()->where('did', $draw->did)->pluck('tpid')->map(fn ($t) => (int) $t)->all(),
        );
    }

    #[Test]
    public function every_prepared_draw_verifies(): void
    {
        $this->potTournament(['is_test' => 1, 'active' => 0]);
        $this->beacon->publish($this->beacon->roundAt(now()->getTimestamp() - 120), str_repeat('ef', 32));
        $tokenDraw = $this->prepare()['draw'];

        $this->potTournament();
        $committed = $this->prepare(1300)['draw'];

        foreach ([$tokenDraw, $committed] as $draw) {
            $fresh = Draw::query()->findOrFail($draw->did);
            $this->assertTrue($this->app->make(DrawVerifier::class)->verify($fresh)->ok(), (string) $fresh->title);
            $this->app->make(PayloadGuard::class)->assertVerifierSafe($fresh->payloadArray());
        }
        $this->assertTrue($this->app->make(DrawVerifier::class)->verify($tokenDraw->fresh())->executed());
    }

    #[Test]
    public function the_route_prepares_on_the_selected_tournament_and_goes_to_the_draws(): void
    {
        $this->potTournament();
        $deadline = date('Y-m-d\TH:i', now()->getTimestamp() + 86400);

        $this->actingAs($this->admin)->post(route('tournament.admin.prepare-draw'), ['phase' => 'AC', 'deadline' => $deadline, 'lead_minutes' => '30'])
            ->assertRedirect(route('draw.index'));
        $this->assertStringStartsWith('Auslosung angelegt: 4 Tokens, 4 Berechtigte.', ModuleFlash::all()[0]['text']);
        $this->assertSame(1, Draw::query()->where('tid', $this->tournament->tid)->count());

        // A refusal returns to the admin page; hostile shapes do not 500.
        $this->actingAs($this->admin)->post(route('tournament.admin.prepare-draw'), ['phase' => ['AC'], 'round' => ['1'], 'deadline' => ['x'], 'lead_minutes' => ['2'], 'in_minutes' => ['5']])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'phase "" is not defined');

        // A test copy selected by the admin gets the draw, not the real tournament.
        $real = $this->tournament;
        $this->potTournament(['is_test' => 1, 'active' => 0]);
        $this->beacon->publish($this->beacon->roundAt(now()->getTimestamp() - 120), str_repeat('aa', 32));
        $this->actingAs($this->admin)
            ->withSession([TestModeSession::KEY => ['tid' => (int) $this->tournament->tid, 'mode' => null, 'tpid' => null]])
            ->post(route('tournament.admin.prepare-draw'), ['phase' => 'AC', 'in_minutes' => '30'])
            ->assertRedirect(route('draw.index'));
        $this->assertSame(1, Draw::query()->where('tid', $this->tournament->tid)->where('status', 'picking')->count());
        $this->assertSame(1, Draw::query()->where('tid', $real->tid)->count());
    }

    #[Test]
    public function a_tournament_admin_without_the_draw_privilege_stays_on_the_admin_page(): void
    {
        $this->potTournament();
        $assist = User::factory()->status('assist')->create();
        $assist->setMeta('allow.admin.tournament', 'true');

        $this->actingAs($assist->fresh())->post(route('tournament.admin.prepare-draw'), ['phase' => 'AC', 'round' => '1200'])
            ->assertRedirect(route('tournament.admin'));
        $this->assertSame(1, Draw::query()->count());
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function keyAndReplace(ActionRefused $e): array
    {
        return [$e->key, $e->replace];
    }
}
