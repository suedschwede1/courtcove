<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\Admin\BracketRefreshService;
use App\Modules\Tournament\Services\GenerationService;
use App\Modules\TournamentBase\Support\ModuleFlash;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 34: "Aus Format erzeugen" and "KO-Raster aktualisieren" (old AdminController generateAction and
 * refreshBracketAction). The bracket here: groups A and B of three, everybody advances (rule "all") into a
 * bracket of 8 with the seeds on lines 1 and 8, byes on lines 2 and 7 and the pool "ac" (AC3..AC6) on lines
 * 3 to 6. A second tournament with the same format is always present and must never change.
 */
class BracketRefreshTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Tournament $t;

    private Tournament $other;

    /** @var array<int, int> seed => tpid */
    private array $tpid = [];

    /** @param  list<array<string, int>>  $swapRules */
    private function format(array $swapRules = []): array
    {
        $ko = [
            'key' => 'KO', 'type' => 'bracket', 'label' => 'KO-Runde', 'size' => 8,
            'qualify' => [['from' => 'G', 'rule' => 'all', 'pool' => 'ac']],
            'seeding' => [['from' => 'G', 'rule' => 'group_winners', 'count' => 2, 'order' => 'record']],
            'lines' => [
                'seeded' => ['1' => 1, '2' => 8],
                'bye' => [2, 7],
                'pools' => ['ac' => [3, 4, 5, 6]],
                'pool_codes' => ['ac' => ['prefix' => 'AC', 'start' => 3]],
            ],
            'draw' => ['mode' => 'direct', 'timing' => 'pre'],
        ];
        if ($swapRules !== []) {
            $ko['swap_rules'] = $swapRules;
        }

        return [
            'version' => 1,
            'match_format' => 'best_of_3_mtb',
            'ranking' => ['points_per_win' => 2, 'tiebreak_scope' => 'tied', 'criteria' => ['points', 'head_to_head', 'set_diff', 'game_diff', 'lot']],
            'runner_up' => ['mode' => 'points'],
            'phases' => [
                [
                    'key' => 'G', 'type' => 'groups', 'label' => 'Vorrunde', 'seed_range' => [1, 6], 'heads' => 1,
                    'groups' => [['key' => 'A', 'size' => 3], ['key' => 'B', 'size' => 3]],
                    'draw' => ['mode' => 'token', 'slot_order' => 'by_position'],
                ],
                $ko,
            ],
        ];
    }

    /**
     * Builds and generates a tournament (group A: seeds 1, 4, 5; group B: seeds 2, 3, 6) and sets the drawn
     * placeholders of the pool lines.
     *
     * @param  array<int, string>  $placeholders  line => code
     * @return array<int, int> seed => tpid
     */
    private function build(array $format, array $placeholders = [3 => 'AC5', 4 => 'AC3', 5 => 'AC4', 6 => 'AC6'], array $attributes = []): array
    {
        $t = Tournament::factory()->active()->withFormat($format)->create($attributes);
        $tpid = [];
        $layout = [1 => ['A', 1], 4 => ['A', 2], 5 => ['A', 3], 2 => ['B', 1], 3 => ['B', 2], 6 => ['B', 3]];
        foreach ($layout as $seed => [$grp, $pos]) {
            $tpid[$seed] = (int) TournamentPlayer::factory()->create([
                'tid' => $t->tid, 'uid' => null, 'label' => sprintf('Demo Spieler %02d', $seed), 'seed' => $seed, 'grp' => $grp, 'grp_pos' => $pos,
            ])->tpid;
        }
        $this->app->make(GenerationService::class)->generate((int) $t->tid);
        foreach ($placeholders as $line => $code) {
            TournamentLine::query()->where('tid', $t->tid)->where('phase', 'KO')->where('line', $line)->update(['placeholder' => $code]);
        }
        $this->app->forgetScopedInstances();

        return [$t, $tpid];
    }

    /** @param  list<array<string, int>>  $swapRules */
    private function setUpBoth(array $swapRules = [], array $placeholders = [3 => 'AC5', 4 => 'AC3', 5 => 'AC4', 6 => 'AC6']): void
    {
        [$this->other] = $this->build($this->format($swapRules), $placeholders, ['title' => 'Andere Meisterschaft', 'active' => 0]);
        $this->playAll($this->other, $this->otherTpids());
        [$this->t, $this->tpid] = $this->build($this->format($swapRules), $placeholders);
    }

    /** @return array<int, int> */
    private function otherTpids(): array
    {
        return TournamentPlayer::query()->where('tid', $this->other->tid)->pluck('tpid', 'seed')->map(fn ($v) => (int) $v)->all();
    }

    /** Scores the group match between two players: the winner takes both sets 6:$loserGames. */
    private function score(Tournament $t, int $winner, int $loser, int $loserGames): void
    {
        $match = TournamentMatch::query()->where('tid', $t->tid)->where('phase', 'group')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('tpid_a', $winner)->where('tpid_b', $loser))
                ->orWhere(fn ($q) => $q->where('tpid_a', $loser)->where('tpid_b', $winner)))
            ->firstOrFail();
        $a = (int) $match->tpid_a === $winner;
        $match->update([
            's1a' => $a ? 6 : $loserGames, 's1b' => $a ? $loserGames : 6, 's2a' => $a ? 6 : $loserGames, 's2b' => $a ? $loserGames : 6,
            'sets_a' => $a ? 2 : 0, 'sets_b' => $a ? 0 : 2, 'games_a' => $a ? 12 : 2 * $loserGames, 'games_b' => $a ? 2 * $loserGames : 12,
            'score' => $a ? "6:$loserGames 6:$loserGames" : "$loserGames:6 $loserGames:6", 'winner' => $winner, 'played' => '2027-05-03',
        ]);
        $this->app->forgetScopedInstances();
    }

    /**
     * Plays every group match with distinct records: A 1 > 4 > 5, B 2 > 3 > 6, so the order is
     * S1 = seed 1, S2 = seed 2, AC3 = seed 3, AC4 = seed 4, AC5 = seed 6, AC6 = seed 5.
     *
     * @param  array<int, int>  $tpid
     */
    private function playAll(Tournament $t, array $tpid, bool $leaveOneOpen = false): void
    {
        $this->score($t, $tpid[1], $tpid[4], 0);
        $this->score($t, $tpid[1], $tpid[5], 1);
        $this->score($t, $tpid[4], $tpid[5], 2);
        $this->score($t, $tpid[2], $tpid[3], 4);
        $this->score($t, $tpid[2], $tpid[6], 3);
        if (! $leaveOneOpen) {
            $this->score($t, $tpid[3], $tpid[6], 4);
        }
    }

    /** @return array<int, int|null> line => tpid */
    private function lines(Tournament $t): array
    {
        return TournamentLine::query()->where('tid', $t->tid)->where('phase', 'KO')->orderBy('line')->get()
            ->mapWithKeys(fn (TournamentLine $l) => [(int) $l->line => $l->tpid === null ? null : (int) $l->tpid])->all();
    }

    private function ko(Tournament $t, int $round, int $slot): TournamentMatch
    {
        return TournamentMatch::query()->where('tid', $t->tid)->where('phase', 'KO')->where('rnd', $round)->where('slot', $slot)->firstOrFail();
    }

    /** @return array{resolved:int, filled:int, advanced:int, notes:list<string>} */
    private function refresh(?Tournament $t = null): array
    {
        $this->app->forgetScopedInstances();

        return $this->app->make(BracketRefreshService::class)->refresh($t ?? $this->t);
    }

    /** @return array<string, mixed> every row of the other tournament */
    private function snapshotOther(): array
    {
        return [
            'lines' => DB::table('bs_tournament_lines')->where('tid', $this->other->tid)->orderBy('tlid')->get()->map(fn ($r) => (array) $r)->all(),
            'matches' => DB::table('bs_tournament_matches')->where('tid', $this->other->tid)->orderBy('tmid')->get()->map(fn ($r) => (array) $r)->all(),
            'players' => DB::table('bs_tournament_players')->where('tid', $this->other->tid)->orderBy('tpid')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function incomplete_group_phase_resolves_nothing(): void
    {
        $this->setUpBoth();
        $this->playAll($this->t, $this->tpid, leaveOneOpen: true);

        $result = $this->refresh();

        $this->assertSame(0, $result['resolved']);
        $this->assertSame(0, $result['filled']);
        $this->assertSame(0, $result['advanced']);
        $this->assertSame(['KO: Phase G ist noch nicht fertig gespielt.'], $result['notes']);
        $this->assertSame(array_fill(1, 8, null), $this->lines($this->t));
        $this->assertNull($this->ko($this->t, 1, 1)->tpid_a);
    }

    #[Test]
    public function complete_phase_fills_round_one(): void
    {
        $this->setUpBoth();
        $before = $this->snapshotOther();
        $this->playAll($this->t, $this->tpid);

        $result = $this->refresh();

        $this->assertSame(['resolved' => 6, 'filled' => 4, 'advanced' => 2, 'notes' => []], $result);
        $s = $this->tpid;
        $this->assertSame([1 => $s[1], 2 => null, 3 => $s[6], 4 => $s[3], 5 => $s[4], 6 => $s[5], 7 => null, 8 => $s[2]], $this->lines($this->t));

        $pairs = [1 => [$s[1], null], 2 => [$s[6], $s[3]], 3 => [$s[4], $s[5]], 4 => [null, $s[2]]];
        foreach ($pairs as $slot => [$a, $b]) {
            $m = $this->ko($this->t, 1, $slot);
            $this->assertSame([$a, $b], [$m->tpid_a === null ? null : (int) $m->tpid_a, $m->tpid_b === null ? null : (int) $m->tpid_b], "R1/$slot");
        }
        $this->assertNull($this->ko($this->t, 1, 2)->winner);
        $this->assertSame($before, $this->snapshotOther());
    }

    #[Test]
    public function bye_advances_without_score(): void
    {
        $this->setUpBoth();
        $this->playAll($this->t, $this->tpid);

        $this->refresh();

        $bye = $this->ko($this->t, 1, 1);
        $this->assertSame($this->tpid[1], (int) $bye->winner);
        $this->assertSame('none', $bye->retired);
        foreach (['score', 's1a', 's1b', 's2a', 's2b', 'sets_a', 'sets_b', 'games_a', 'games_b', 'entered_uid', 'confirmed_uid', 'played'] as $column) {
            $this->assertNull($bye->{$column}, $column);
        }
        $lower = $this->ko($this->t, 1, 4);
        $this->assertSame($this->tpid[2], (int) $lower->winner);
        $this->assertNull($lower->score);

        // Both bye winners already advanced in the same run.
        $this->assertSame($this->tpid[1], (int) $this->ko($this->t, 2, 1)->tpid_a);
        $this->assertSame($this->tpid[2], (int) $this->ko($this->t, 2, 2)->tpid_b);
    }

    #[Test]
    public function winners_advance_to_ceil_half_side_a_on_odd(): void
    {
        $this->setUpBoth();
        $this->playAll($this->t, $this->tpid);
        $this->refresh();

        // R1/3 (odd) goes to R2/2 side a, R1/2 (even) to R2/1 side b.
        $this->ko($this->t, 1, 3)->update(['winner' => $this->tpid[5], 'score' => '4:6 4:6']);
        $this->ko($this->t, 1, 2)->update(['winner' => $this->tpid[3], 'score' => '6:1 6:1']);

        $result = $this->refresh();

        $this->assertSame(['resolved' => 0, 'filled' => 0, 'advanced' => 2, 'notes' => []], $result);
        $r21 = $this->ko($this->t, 2, 1);
        $r22 = $this->ko($this->t, 2, 2);
        $this->assertSame([$this->tpid[1], $this->tpid[3]], [(int) $r21->tpid_a, (int) $r21->tpid_b]);
        $this->assertSame([$this->tpid[5], $this->tpid[2]], [(int) $r22->tpid_a, (int) $r22->tpid_b]);

        // A semi-final winner goes on to the final (R3/1): slot 1 is odd, side a.
        $r21->update(['winner' => $this->tpid[3]]);
        $this->assertSame(1, $this->refresh()['advanced']);
        $this->assertSame($this->tpid[3], (int) $this->ko($this->t, 3, 1)->tpid_a);

        // A played target is never overwritten when a predecessor changes afterwards.
        $this->ko($this->t, 3, 1)->update(['tpid_b' => $this->tpid[2], 'winner' => $this->tpid[3]]);
        $r22->update(['winner' => $this->tpid[5]]);
        $result = $this->refresh();
        $this->assertSame(0, $result['advanced']);
        $this->assertSame(['KO R3/1 ist bereits gespielt; geänderter Vorgänger wurde nicht überschrieben.'], $result['notes']);
        $this->assertSame($this->tpid[2], (int) $this->ko($this->t, 3, 1)->tpid_b);
    }

    #[Test]
    public function lot_tie_uses_derived_order_from_the_first_draw(): void
    {
        $this->setUpBoth();
        $s = $this->tpid;
        // Identical records in both groups: winners, runners-up and thirds tie across the groups.
        foreach ([[1, 4], [1, 5], [4, 5], [2, 3], [2, 6], [3, 6]] as [$w, $l]) {
            $this->score($this->t, $s[$w], $s[$l], 3);
        }

        // Without an executed draw the tie blocks the bracket.
        $blocked = $this->refresh();
        $this->assertSame(0, $blocked['resolved']);
        $this->assertCount(1, $blocked['notes']);
        $this->assertStringStartsWith('KO: Losentscheidung erforderlich', $blocked['notes'][0]);

        // The lowest executed, not discarded draw gives the seed; a discarded earlier one and a later one do not.
        Draw::factory()->executed(str_repeat('11', 32))->discarded()->create(['tid' => $this->t->tid]);
        $first = Draw::factory()->executed(str_repeat('22', 32))->create(['tid' => $this->t->tid]);
        Draw::factory()->executed(str_repeat('33', 32))->create(['tid' => $this->t->tid]);
        $seed = (string) $first->fresh()->final_seed;

        $order = [];
        foreach ([1 => [$s[1], $s[2]], 2 => [$s[4], $s[3]], 3 => [$s[5], $s[6]]] as $place => $tied) {
            sort($tied);
            foreach ((new DrawService)->shuffle($tied, hash_hmac('sha256', 'phase:G:place:'.$place, $seed)) as $tpid) {
                $order[] = $tpid;
            }
        }
        // codes: 1 => S1, 2 => S2, 3..6 => AC3..AC6; lines 3..6 carry AC5, AC3, AC4, AC6
        $expected = [1 => $order[0], 2 => null, 3 => $order[4], 4 => $order[2], 5 => $order[3], 6 => $order[5], 7 => null, 8 => $order[1]];

        $result = $this->refresh();

        $this->assertSame(6, $result['resolved']);
        $this->assertSame($expected, $this->lines($this->t));

        // A manual lot order wins over the derived one.
        $manual = array_reverse((new DrawService)->shuffle([min($s[1], $s[2]), max($s[1], $s[2])], hash_hmac('sha256', 'phase:G:place:1', $seed)));
        $this->t->update(['lot_orders' => json_encode(['phase:G:place:1' => $manual])]);
        $this->refresh();
        $lines = $this->lines($this->t);
        $this->assertSame([$manual[0], $manual[1]], [$lines[1], $lines[8]]);
    }

    #[Test]
    public function swap_rules_are_applied(): void
    {
        // Line 3 carries AC6 = seed 5 of group A, the group of seed 1: seeds 1 and 2 change lines.
        $this->setUpBoth([['if_same_group_as_line' => 3, 'seed' => 1, 'swap_with_seed' => 2]], [3 => 'AC6', 4 => 'AC3', 5 => 'AC4', 6 => 'AC5']);
        $this->playAll($this->t, $this->tpid);

        $result = $this->refresh();

        $this->assertSame(['KO: Setznummern 1 und 2 gemäß Tauschregel versetzt.'], $result['notes']);
        $lines = $this->lines($this->t);
        $this->assertSame([$this->tpid[2], $this->tpid[5], $this->tpid[1]], [$lines[1], $lines[3], $lines[8]]);
    }

    #[Test]
    public function swap_rule_is_skipped_for_different_groups(): void
    {
        // Line 3 carries AC5 = seed 6 of group B: no swap.
        $this->setUpBoth([['if_same_group_as_line' => 3, 'seed' => 1, 'swap_with_seed' => 2]]);
        $this->playAll($this->t, $this->tpid);

        $this->assertSame([], $this->refresh()['notes']);
        $lines = $this->lines($this->t);
        $this->assertSame([$this->tpid[1], $this->tpid[2]], [$lines[1], $lines[8]]);
    }

    #[Test]
    public function refresh_is_idempotent(): void
    {
        $this->setUpBoth();
        $this->playAll($this->t, $this->tpid);
        $this->refresh();
        $rows = fn () => [
            DB::table('bs_tournament_lines')->orderBy('tlid')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('bs_tournament_matches')->orderBy('tmid')->get()->map(fn ($r) => (array) $r)->all(),
        ];
        $before = $rows();

        $this->assertSame(['resolved' => 0, 'filled' => 0, 'advanced' => 0, 'notes' => []], $this->refresh());
        $this->assertSame($before, $rows());
    }

    #[Test]
    public function failure_rolls_back(): void
    {
        $this->setUpBoth();
        $this->playAll($this->t, $this->tpid);

        // The first round fill fails after the line updates were written.
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'update "bs_tournament_matches"')) {
                throw new RuntimeException('simulierter Fehler');
            }
        });

        try {
            $this->refresh();
            $this->fail('exception expected');
        } catch (RuntimeException $e) {
            $this->assertSame('simulierter Fehler', $e->getMessage());
        }

        $this->assertSame(array_fill(1, 8, null), $this->lines($this->t));
        $this->assertNull($this->ko($this->t, 1, 1)->winner);
    }

    // ------------------------------------------------------------------ routes

    #[Test]
    public function generate_route_flashes_the_counts_and_skipped_groups(): void
    {
        $t = Tournament::factory()->active()->withFormat($this->format())->create();
        foreach ([1 => ['A', 1], 2 => ['B', 1], 4 => ['A', 2], 5 => ['A', 3], 3 => [null, null], 6 => [null, null]] as $seed => [$grp, $pos]) {
            TournamentPlayer::factory()->create(['tid' => $t->tid, 'uid' => null, 'label' => sprintf('Demo Spieler %02d', $seed), 'seed' => $seed, 'grp' => $grp, 'grp_pos' => $pos]);
        }
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tournament.admin.generate'))->assertRedirect(route('tournament.admin'));

        $this->assertSame([
            ['type' => 'success', 'text' => 'Erzeugt: 0 Gruppenköpfe, 3 Gruppenpartien, 8 Rasterlinien, 7 KO-Partien.'],
            ['type' => 'info', 'text' => 'Gruppe B: 1 von 3 Plätzen besetzt, 3 Partie(n) noch offen - Partien folgen nach der Auslosung'],
        ], session(ModuleFlash::KEY));
        $this->assertSame(3, TournamentMatch::query()->where('tid', $t->tid)->where('phase', 'group')->count());

        // Idempotent: a second run creates nothing.
        $this->actingAs($admin)->post(route('tournament.admin.generate'));
        $this->assertSame('Erzeugt: 0 Gruppenköpfe, 0 Gruppenpartien, 0 Rasterlinien, 0 KO-Partien.', session(ModuleFlash::KEY)[0]['text']);
    }

    #[Test]
    public function generate_works_on_the_selected_tournament_and_reports_failures(): void
    {
        $this->setUpBoth();
        $broken = Tournament::factory()->test()->create(['title' => 'TEST kaputt', 'format' => '{"phases": []}']);
        $admin = $this->admin();
        $before = TournamentMatch::query()->count();

        $this->actingAs($admin)->withSession([TestModeSession::KEY => ['tid' => (int) $broken->tid, 'mode' => null, 'tpid' => null]])
            ->post(route('tournament.admin.generate'))->assertRedirect(route('tournament.admin'));

        $this->assertSame([['type' => 'error', 'text' => 'Erzeugung fehlgeschlagen, es wurde nichts gespeichert: interner Fehler, Details stehen im Fehlerprotokoll']], session(ModuleFlash::KEY));
        $this->assertSame($before, TournamentMatch::query()->count());
    }

    #[Test]
    public function refresh_route_flashes_notes_then_counts_on_the_selected_tournament(): void
    {
        $this->setUpBoth();
        $this->playAll($this->t, $this->tpid, leaveOneOpen: true);
        $before = $this->snapshotOther();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tournament.admin.refresh-bracket'))->assertRedirect(route('tournament.admin'));
        $this->assertSame([
            ['type' => 'info', 'text' => 'KO: Phase G ist noch nicht fertig gespielt.'],
            ['type' => 'success', 'text' => '0 Rasterlinien aufgelöst, 0 Erstrundenpartien gefüllt, 0 Aufstiege übertragen.'],
        ], session(ModuleFlash::KEY));

        $this->score($this->t, $this->tpid[3], $this->tpid[6], 4);
        $this->actingAs($admin)->post(route('tournament.admin.refresh-bracket'));
        $this->assertSame([['type' => 'success', 'text' => '6 Rasterlinien aufgelöst, 4 Erstrundenpartien gefüllt, 2 Aufstiege übertragen.']], session(ModuleFlash::KEY));

        // Selecting the other tournament refreshes only that one.
        $this->actingAs($admin)->withSession([TestModeSession::KEY => ['tid' => (int) $this->other->tid, 'mode' => null, 'tpid' => null]])
            ->post(route('tournament.admin.refresh-bracket'));
        $this->assertNotSame($before, $this->snapshotOther());
        $this->assertSame($this->otherTpids()[1], (int) TournamentLine::query()->where('tid', $this->other->tid)->where('line', 1)->value('tpid'));
    }

    #[Test]
    public function routes_require_the_admin_privilege_and_csrf(): void
    {
        $this->setUpBoth();
        $this->playAll($this->t, $this->tpid);
        $member = User::factory()->create(['status' => 'enabled']);

        foreach (['generate', 'refresh-bracket'] as $action) {
            $this->post('/tournament/admin/'.$action)
                ->assertRedirect(route('login', ['redirect_to' => '/tournament/admin/'.$action]));
        }
        foreach (['generate', 'refresh-bracket'] as $action) {
            $this->actingAs($member)->post('/tournament/admin/'.$action)->assertForbidden();
            $this->actingAs($this->admin())->get('/tournament/admin/'.$action)->assertRedirect(route('tournament.admin'));
        }
        $this->assertSame(array_fill(1, 8, null), $this->lines($this->t));

        $this->withMiddleware();
        $this->app['env'] = 'local';
        $this->actingAs($this->admin())->from(route('tournament.admin'))->post(route('tournament.admin.refresh-bracket'))
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'Ungültiger oder abgelaufener Formularschlüssel. Bitte Seite neu laden.');
        $this->assertSame(array_fill(1, 8, null), $this->lines($this->t));
    }

    #[Test]
    public function a_real_login_session_refreshes(): void
    {
        $this->setUpBoth();
        $this->playAll($this->t, $this->tpid);
        $admin = User::factory()->create(['status' => 'admin', 'email' => 'leitung@example.test', 'pw' => bcrypt('geheim-123')]);

        $this->post('/login', ['email' => 'leitung@example.test', 'password' => 'geheim-123']);
        $this->assertAuthenticatedAs($admin);

        $this->post(route('tournament.admin.refresh-bracket'))->assertRedirect(route('tournament.admin'));
        $this->assertSame($this->tpid[1], $this->lines($this->t)[1]);
    }

    #[Test]
    public function the_admin_page_shows_generate_and_refresh(): void
    {
        $this->setUpBoth();

        $html = $this->actingAs($this->admin())->get(route('tournament.admin'))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('tournament.admin.generate').'"', $html);
        $this->assertStringContainsString('action="'.route('tournament.admin.refresh-bracket').'"', $html);
    }
}
