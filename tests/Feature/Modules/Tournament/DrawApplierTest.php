<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\Draws\DrawApplier;
use App\Modules\Tournament\Services\GenerationService;
use App\Modules\TournamentBase\Support\ActionRefused;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Draw\Concerns\BuildsTokenDraw;
use Tests\TestCase;

class DrawApplierTest extends TestCase
{
    use BuildsTokenDraw;
    use LazilyRefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['status' => 'admin']);
    }

    /** The token draw closed with every token claimed: seeds 3..6 in slot order A2, B2, A3, B3. */
    private function closedTokenDraw(): Draw
    {
        $this->tokenDraw();
        foreach (['A2' => 3, 'B2' => 4, 'A3' => 5, 'B3' => 6] as $slot => $seed) {
            $this->claimDirectly($this->tokenWithSlot($slot), $seed);
        }
        Draw::query()->whereKey($this->draw->did)->update(['status' => 'closed', 'closed' => '2027-03-01 20:00:00']);

        return $this->draw = $this->draw->fresh();
    }

    /** A direct bracket draw for the KO pool lines 2 and 3 (lines created by the generation). */
    private function directDraw(array $slots = ['KO:2', 'KO:3'], array $entries = ['W3', 'W4']): Draw
    {
        $this->tokenDraw();
        app(GenerationService::class)->generate((int) $this->tournament->tid);

        return Draw::factory()->withPayload(['entries' => $entries, 'slots' => $slots, 'rule' => 'Testregel'])
            ->executed()->create(['tid' => $this->tournament->tid]);
    }

    private function apply(Draw $draw, ?int $tid = null): int
    {
        return app(DrawApplier::class)->apply($draw->fresh(), $tid ?? (int) $this->tournament->tid, $this->admin);
    }

    private function refusal(callable $action): string
    {
        try {
            $action();
        } catch (ActionRefused $e) {
            return (string) __($e->key, $e->replace);
        }

        $this->fail('no refusal');
    }

    /** @return array<int, array<string, mixed>> */
    private function players(): array
    {
        return TournamentPlayer::query()->where('tid', $this->tournament->tid)->orderBy('tpid')->get()->map->getAttributes()->all();
    }

    #[Test]
    public function closed_token_draw_applies_groups(): void
    {
        $draw = $this->closedTokenDraw();

        $this->assertSame(4, $this->apply($draw));

        foreach (['A2' => 3, 'B2' => 4, 'A3' => 5, 'B3' => 6] as $slot => $seed) {
            $player = TournamentPlayer::query()->findOrFail($this->tpid[$seed]);
            $this->assertSame([$slot[0], (int) $slot[1]], [$player->grp, (int) $player->grp_pos]);
        }
        $fresh = $draw->fresh();
        $this->assertNotNull($fresh->applied_at);
        $this->assertSame((int) $this->admin->uid, (int) $fresh->applied_uid);
        $this->assertSame('4 Zuordnungen atomar angewendet.', __('tournament::admin.apply.success', ['count' => 4]));
    }

    #[Test]
    public function executed_direct_draw_fills_placeholders(): void
    {
        $draw = $this->directDraw();

        $this->assertSame(2, $this->apply($draw));

        $lines = TournamentLine::query()->where('tid', $this->tournament->tid)->where('phase', 'KO')->orderBy('line')->pluck('placeholder', 'line')->all();
        $result = $draw->fresh()->resultArray();
        $this->assertSame($result['KO:2'], $lines[2]);
        $this->assertSame($result['KO:3'], $lines[3]);
        $this->assertNull($lines[1]); // seeded lines stay untouched
        $this->assertNotNull($draw->fresh()->applied_at);
    }

    #[Test]
    public function direct_draw_refuses_bad_slots_and_missing_pool_lines(): void
    {
        $draw = $this->directDraw();

        Draw::query()->whereKey($draw->did)->update(['result' => '{"ko:2":"W3"}']);
        $this->assertSame('Zielslot "ko:2" ist keine Rasterlinie.', $this->refusal(fn () => $this->apply($draw)));

        Draw::query()->whereKey($draw->did)->update(['result' => '{"KO:1":"W3"}']); // a seeded line
        $this->assertSame('Rasterlinie KO:1 existiert nicht als Poollinie.', $this->refusal(fn () => $this->apply($draw)));

        Draw::query()->whereKey($draw->did)->update(['result' => 'kaputt']);
        $this->assertSame('Das Ziehungsergebnis ist kein gültiges JSON.', $this->refusal(fn () => $this->apply($draw)));

        $this->assertNull($draw->fresh()->applied_at);
        $this->assertSame([], TournamentLine::query()->where('tid', $this->tournament->tid)->whereNotNull('placeholder')->pluck('tlid')->all());
    }

    #[Test]
    public function apply_validates_everything_before_the_first_write(): void
    {
        $draw = $this->closedTokenDraw();
        $other = Tournament::factory()->create();
        $foreign = TournamentPlayer::factory()->create(['tid' => $other->tid]);
        // The last token (by dtid) claims a player of another tournament.
        $last = DrawToken::query()->where('did', $draw->did)->orderByDesc('dtid')->firstOrFail();
        $owner = (int) $last->claimed_tpid;
        DrawToken::query()->whereKey($last->dtid)->update(['claimed_tpid' => $foreign->tpid]);
        $before = $this->players();

        $this->assertSame('Token '.$last->token.' verweist auf einen fremden Teilnehmer.', $this->refusal(fn () => $this->apply($draw)));

        $this->assertSame($before, $this->players());
        $this->assertNull($draw->fresh()->applied_at);

        // An unclaimed or slot-less token is refused the same way.
        DrawToken::query()->whereKey($last->dtid)->update(['claimed_tpid' => null]);
        $this->assertSame('Token '.$last->token.' ist unvollständig oder hat einen ungültigen Slot.', $this->refusal(fn () => $this->apply($draw)));
        DrawToken::query()->whereKey($last->dtid)->update(['claimed_tpid' => $owner, 'slot' => 'B-3']);
        $this->assertSame('Token '.$last->token.' ist unvollständig oder hat einen ungültigen Slot.', $this->refusal(fn () => $this->apply($draw)));
        $this->assertSame($before, $this->players());
    }

    #[Test]
    public function second_apply_is_refused_and_writes_nothing(): void
    {
        $draw = $this->closedTokenDraw();
        $this->apply($draw);
        $applied = $draw->fresh()->applied_at;
        TournamentPlayer::query()->whereKey($this->tpid[3])->update(['grp' => 'B', 'grp_pos' => 3]); // moved by hand
        $before = $this->players();

        $this->assertSame('Diese Auslosung wurde bereits angewendet.', $this->refusal(fn () => $this->apply($draw)));

        $this->assertSame($before, $this->players());
        $this->assertSame($applied, $draw->fresh()->applied_at);
    }

    #[Test]
    public function conditional_mark_failure_rolls_back_row_updates(): void
    {
        $draw = $this->closedTokenDraw();
        $before = $this->players();
        $fired = false;

        // A parallel apply marks the draw right after our first row update (inside our transaction).
        DB::listen(function (QueryExecuted $query) use (&$fired, $draw): void {
            if ($fired || ! str_starts_with(strtolower($query->sql), 'update') || ! str_contains($query->sql, 'bs_tournament_players')) {
                return;
            }
            $fired = true;
            DB::table('bs_draws')->where('did', $draw->did)->update(['applied_at' => '2027-03-01 21:00:00', 'applied_uid' => 1]);
        });

        $this->assertSame('Die Auslosung wurde parallel bereits angewendet.', $this->refusal(fn () => $this->apply($draw)));

        $this->assertTrue($fired);
        $this->assertSame($before, $this->players());
    }

    #[Test]
    public function draw_of_another_tournament_is_refused(): void
    {
        $draw = $this->closedTokenDraw();
        $other = Tournament::factory()->create();
        $text = 'Auslosung fehlt, ist noch nicht abgeschlossen, wurde nicht gezogen, ist verworfen oder gehört zu einem anderen Turnier.';

        $this->assertSame($text, $this->refusal(fn () => $this->apply($draw, (int) $other->tid)));

        Draw::query()->whereKey($draw->did)->update(['tid' => null]);
        $this->assertSame($text, $this->refusal(fn () => $this->apply($draw)));

        $this->assertNull($draw->fresh()->applied_at);
    }

    #[Test]
    public function token_draw_must_be_closed_and_direct_executed(): void
    {
        $text = 'Auslosung fehlt, ist noch nicht abgeschlossen, wurde nicht gezogen, ist verworfen oder gehört zu einem anderen Turnier.';
        $draw = $this->closedTokenDraw();
        $before = $this->players();

        foreach ([
            ['status' => 'picking'],
            ['status' => 'executed'],
            ['final_seed' => null],
            ['discarded' => '2027-03-01 22:00:00'],
        ] as $change) {
            $original = Draw::query()->findOrFail($draw->did)->getAttributes();
            Draw::query()->whereKey($draw->did)->update($change);
            $this->assertSame($text, $this->refusal(fn () => $this->apply($draw)), json_encode($change));
            Draw::query()->whereKey($draw->did)->update(array_intersect_key($original, $change));
        }

        $direct = Draw::factory()->executed()->create(['tid' => $this->tournament->tid, 'status' => 'closed']);
        $this->assertSame($text, $this->refusal(fn () => $this->apply($direct)));

        $committed = Draw::factory()->create(['tid' => $this->tournament->tid, 'status' => 'executed']); // no seed
        $this->assertSame($text, $this->refusal(fn () => $this->apply($committed)));

        $this->assertSame($before, $this->players());
        $this->assertSame(4, $this->apply($draw)); // unchanged draw still applies
    }
}
