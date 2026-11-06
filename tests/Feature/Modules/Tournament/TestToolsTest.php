<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\DrawVerifier;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\Admin\ResetService;
use App\Modules\Tournament\Services\Admin\ResultSimulator;
use App\Modules\Tournament\Services\LotOrderService;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Support\TournamentAccess;
use App\Modules\TournamentBase\Services\ScoreService;
use App\Modules\TournamentBase\Support\ActionRefused;
use App\Modules\TournamentBase\Support\ModuleFlash;
use App\Modules\TournamentBase\Support\TestModeSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Concerns\SeedsRandomizer;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Task 35: the test tools of the championship admin (old AdminController simulate, simulateResults,
 * randomScore and reset). Every destructive test runs with a second tournament present, whose rows must
 * stay byte-identical.
 *
 * The reset flashes show affected-row counts, as in ZF2. SQLite reports MATCHED rows ("9 Partien ohne
 * Ergebnis" even for rows that were already empty); MySQL reports CHANGED rows, so the same action shows
 * smaller numbers there. The exact counts below are therefore pinned for SQLite only: setUp refuses any
 * other driver, so moving this class into the MySQL group fails loudly instead of asserting wrong numbers.
 */
class TestToolsTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;
    use SeedsRandomizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'the reset counts are SQLite matched-row counts');
    }

    private Tournament $other;

    /** @var array<string, list<array<string, mixed>>> */
    private array $otherBefore = [];

    /** The other tournament (with a played match and an executed draw), then the main one. */
    private function both(array $attributes = []): Tournament
    {
        $this->other = $this->championship([], ['title' => 'Andere Meisterschaft', 'season' => 2026]);
        $this->play('A-0');
        Draw::factory()->token(4, 'G1', ['A2', 'A3', 'B2', 'B3'])->executed()->create(['tid' => $this->other->tid]);
        $this->other->update(['active' => 0]);

        $main = $this->championship([], $attributes);
        $this->otherBefore = $this->otherRows();

        return $main;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function otherRows(): array
    {
        $tid = $this->other->tid;
        $dids = DB::table('bs_draws')->where('tid', $tid)->pluck('did')->all();

        return [
            'players' => DB::table('bs_tournament_players')->where('tid', $tid)->orderBy('tpid')->get()->map(fn ($r) => (array) $r)->all(),
            'matches' => DB::table('bs_tournament_matches')->where('tid', $tid)->orderBy('tmid')->get()->map(fn ($r) => (array) $r)->all(),
            'lines' => DB::table('bs_tournament_lines')->where('tid', $tid)->orderBy('tlid')->get()->map(fn ($r) => (array) $r)->all(),
            'draws' => DB::table('bs_draws')->where('tid', $tid)->orderBy('did')->get()->map(fn ($r) => (array) $r)->all(),
            'tokens' => DB::table('bs_draw_tokens')->whereIn('did', $dids)->orderBy('dtid')->get()->map(fn ($r) => (array) $r)->all(),
            'tournament' => [(array) DB::table('bs_tournaments')->where('tid', $tid)->first()],
        ];
    }

    private function assertOtherUntouched(): void
    {
        $this->assertSame($this->otherBefore, $this->otherRows(), 'the other tournament changed');
    }

    /** @return array<string, mixed> */
    private function select(Tournament $t): array
    {
        return [TestModeSession::KEY => ['tid' => (int) $t->tid, 'mode' => null, 'tpid' => null]];
    }

    private function selectedTestCopy(): Tournament
    {
        return $this->both(['title' => 'TEST Demo-Meisterschaft', 'is_test' => 1, 'active' => 0]);
    }

    /** @return array<string, mixed>|null */
    private function simulation(): ?array
    {
        $state = session(TestModeSession::KEY);

        return is_array($state) ? $state : null;
    }

    // ------------------------------------------------------------------ simulate

    #[Test]
    public function simulate_and_stop(): void
    {
        $this->both();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tournament.admin.simulate'), ['mode' => 'member'])
            ->assertRedirect(route('tournament.index'))
            ->assertSessionHas('success', 'Testmodus: Ansicht eines einfachen Mitglieds ohne Teilnahme.');
        $this->assertSame('member', $this->simulation()['mode']);
        $context = $this->app->make(TournamentAccess::class)->context($admin, $this->tournament);
        $this->assertSame(TournamentAccess::MEMBER, $context->level);
        $this->assertFalse($context->canWrite);

        $this->actingAs($admin)->post(route('tournament.admin.simulate'), ['mode' => 'player', 'tpid' => (string) $this->tpid[3]])
            ->assertRedirect(route('tournament.index'))
            ->assertSessionHas('success', 'Testmodus: Ansicht von Demo Spieler 03.');
        $this->assertSame(['mode' => 'player', 'tpid' => $this->tpid[3]], array_intersect_key($this->simulation(), ['mode' => 1, 'tpid' => 1]));
        $this->app->forgetScopedInstances();
        $context = $this->app->make(TournamentAccess::class)->context($admin, $this->tournament);
        $this->assertSame(TournamentAccess::PARTICIPANT, $context->level);
        $this->assertFalse($context->canWrite);

        // ZF2's stop value is "off"; "stop" ends it as well.
        $this->actingAs($admin)->post(route('tournament.admin.simulate'), ['mode' => 'off'])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('success', 'Testmodus beendet.');
        $this->assertNull($this->simulation()['mode']);
        $this->assertNull($this->simulation()['tpid']);

        $this->actingAs($admin)->post(route('tournament.admin.simulate'), ['mode' => 'member']);
        $this->actingAs($admin)->post(route('tournament.admin.simulate'), ['mode' => 'stop'])->assertSessionHas('success', 'Testmodus beendet.');
        $this->assertNull($this->simulation()['mode']);
        $this->assertOtherUntouched();
    }

    #[Test]
    public function simulate_refuses_invalid_requests_and_foreign_players(): void
    {
        $this->both();
        $admin = $this->admin();
        $foreign = (int) TournamentPlayer::query()->where('tid', $this->other->tid)->value('tpid');

        $cases = [
            ['mode' => 'player'],
            ['mode' => 'player', 'tpid' => '0'],
            ['mode' => 'player', 'tpid' => ['1']],
            ['mode' => ['member']],
            ['mode' => 'admin', 'tpid' => (string) $this->tpid[1]],
            ['tpid' => '99999999999'],
        ];
        foreach ($cases as $input) {
            $this->actingAs($admin)->post(route('tournament.admin.simulate'), $input)
                ->assertRedirect(route('tournament.admin'))
                ->assertSessionHas('error', 'Ungültige Testmodus-Anfrage.');
            $this->assertNull($this->simulation()['mode'] ?? null, json_encode($input));
        }

        // A participant of another tournament is not a player of the selected one.
        $this->actingAs($admin)->post(route('tournament.admin.simulate'), ['mode' => 'player', 'tpid' => (string) $foreign])
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'Dieser Teilnehmer gehört nicht zum gewählten Turnier.');
        $this->assertNull($this->simulation()['mode'] ?? null);
    }

    #[Test]
    public function simulation_never_raises_the_level(): void
    {
        $this->both();
        $member = $this->member();
        TournamentPlayer::query()->whereKey($this->tpid[2])->update(['uid' => $member->uid]);

        $this->actingAs($member)->post(route('tournament.admin.simulate'), ['mode' => 'player', 'tpid' => (string) $this->tpid[1]])->assertForbidden();
        $this->assertNull($this->simulation());

        // Even a session value planted for a member does nothing: only admins have a simulation.
        $this->actingAs($member)->withSession([TestModeSession::KEY => ['mode' => 'player', 'tpid' => $this->tpid[1], 'tid' => null]]);
        $context = $this->app->make(TournamentAccess::class)->context($member, $this->tournament);
        $this->assertSame(TournamentAccess::PARTICIPANT, $context->level);
        $this->assertSame($this->tpid[2], $context->tpid);
        $this->assertNull($context->simulation);
    }

    // ------------------------------------------------------------------ simulate-results

    #[Test]
    public function simulate_results_only_for_test_tournaments(): void
    {
        $real = $this->both();
        $admin = $this->admin();
        $matches = fn (Tournament $t) => DB::table('bs_tournament_matches')->where('tid', $t->tid)->orderBy('tmid')->get()->map(fn ($r) => (array) $r)->all();
        $before = $matches($real);

        $this->actingAs($admin)->post(route('tournament.admin.simulate-results'))
            ->assertRedirect(route('tournament.admin'))
            ->assertSessionHas('error', 'Zufallsergebnisse gibt es nur im Testturnier. Beim echten Turnier müssen die Spieler ihre Ergebnisse selbst eintragen - sonst stünde in entered_uid die Turnierleitung.');
        $this->assertSame($before, $matches($real));

        try {
            $this->app->make(ResultSimulator::class)->simulate($real, new Randomizer(new Mt19937(1)), $admin);
            $this->fail('refusal expected');
        } catch (ActionRefused $e) {
            $this->assertSame('tournament::admin.simulate_results.real', $e->key);
        }

        // The selected test copy: one result is already entered and stays.
        $copy = $this->championship([], ['title' => 'TEST Demo-Meisterschaft', 'is_test' => 1, 'active' => 0]);
        $kept = $this->play('B-1', 'b');
        $this->seedRandomizer(7);

        $this->actingAs($admin)->withSession($this->select($copy))->post(route('tournament.admin.simulate-results'))
            ->assertRedirect(route('tournament.admin'));

        $this->assertSame([[
            'type' => 'success',
            'text' => '5 Gruppenspiele mit Zufallsergebnissen gefüllt, 1 übersprungen (schon eingetragen oder unbesetzt). Jetzt "KO-Raster aktualisieren" aufrufen, um die Platzhalter aufzulösen.',
        ]], session(ModuleFlash::KEY));

        $scores = new ScoreService;
        foreach (TournamentMatch::query()->where('tid', $copy->tid)->where('phase', 'group')->get() as $m) {
            if ((int) $m->tmid === (int) $kept->tmid) {
                $this->assertSame('4:6 3:6', $m->score);
                $this->assertNull($m->entered_uid);

                continue;
            }
            $result = $scores->evaluate(['s1a' => $m->s1a, 's1b' => $m->s1b, 's2a' => $m->s2a, 's2b' => $m->s2b, 'mtba' => $m->mtba, 'mtbb' => $m->mtbb]);
            $this->assertSame($result['score'], $m->score);
            $this->assertSame([$result['sets_a'], $result['sets_b'], $result['games_a'], $result['games_b']], [(int) $m->sets_a, (int) $m->sets_b, (int) $m->games_a, (int) $m->games_b]);
            $this->assertSame($result['winner_side'] === 'a' ? (int) $m->tpid_a : (int) $m->tpid_b, (int) $m->winner);
            $this->assertSame((int) $admin->uid, (int) $m->entered_uid);
            $this->assertSame((int) $admin->uid, (int) $m->confirmed_uid);
            $this->assertNotNull($m->confirmed);
            $this->assertContains($m->court, ['1', '2', '3']);
            $this->assertMatchesRegularExpression('/^(09|1[0-9]|20):(00|30):00$/', $m->played_time);
            $days = (int) round((strtotime(date('Y-m-d')) - strtotime($m->played)) / 86400);
            $this->assertGreaterThanOrEqual(1, $days);
            $this->assertLessThanOrEqual(14, $days);
        }
        $this->assertSame(0, TournamentMatch::query()->where('tid', $copy->tid)->where('phase', '!=', 'group')->whereNotNull('winner')->count());
        $this->assertSame($before, $matches($real));
        $this->assertOtherUntouched();
    }

    #[Test]
    public function random_scores_are_always_valid_and_come_from_the_randomizer(): void
    {
        $this->both();
        $copy = $this->championship([], ['title' => 'TEST Demo-Meisterschaft', 'is_test' => 1, 'active' => 0]);
        $admin = $this->admin();
        $clear = fn () => TournamentMatch::query()->where('tid', $copy->tid)->update(['winner' => null, 'score' => null]);
        $mtb = 0;

        $runs = [];
        foreach (range(1, 40) as $seed) {
            $clear();
            $this->app->forgetScopedInstances();
            $result = $this->app->make(ResultSimulator::class)->simulate($copy, new Randomizer(new Mt19937($seed)), $admin);
            $this->assertSame([], $result['problems'], "seed $seed");
            $this->assertSame(6, $result['filled']);
            $mtb += TournamentMatch::query()->where('tid', $copy->tid)->whereNotNull('mtba')->count();
            $runs[$seed] = TournamentMatch::query()->where('tid', $copy->tid)->orderBy('tmid')->pluck('score')->all();
        }
        $this->assertGreaterThan(0, $mtb, 'about a third goes over the match tie-break');

        // The same seed gives the same results: all randomness is drawn from the injected Randomizer.
        $clear();
        $this->app->forgetScopedInstances();
        $this->app->make(ResultSimulator::class)->simulate($copy, new Randomizer(new Mt19937(5)), $admin);
        $this->assertSame($runs[5], TournamentMatch::query()->where('tid', $copy->tid)->orderBy('tmid')->pluck('score')->all());
        $this->assertNotSame($runs[5], $runs[6]);
    }

    // ------------------------------------------------------------------ reset

    #[Test]
    public function reset_requires_the_exact_title_for_real_tournaments(): void
    {
        $real = $this->both();
        $this->play('A-0');
        $admin = $this->admin();
        $refusal = 'Das ist KEIN Testturnier. Zum Zurücksetzen den Titel exakt eintippen: Demo-Meisterschaft';

        foreach ([[], ['confirm' => 'demo-meisterschaft'], ['confirm' => 'Demo-Meisterschaft 2'], ['confirm' => ['Demo-Meisterschaft']]] as $extra) {
            $this->actingAs($admin)->post(route('tournament.admin.reset'), ['scope' => 'all'] + $extra)
                ->assertRedirect(route('tournament.admin'))
                ->assertSessionHas('error', $refusal);
        }
        $this->assertSame(9, TournamentMatch::query()->where('tid', $real->tid)->count());
        $this->assertNotNull($this->matchRow('A-0')->winner);

        try {
            $this->app->make(ResetService::class)->reset($real, 'results', null, '', $admin);
            $this->fail('refusal expected');
        } catch (ActionRefused $e) {
            $this->assertSame('tournament::admin.reset.not_test', $e->key);
        }

        $this->actingAs($admin)->post(route('tournament.admin.reset'), ['scope' => 'results', 'confirm' => 'Demo-Meisterschaft'])
            ->assertRedirect(route('tournament.admin'));
        $this->assertSame([['type' => 'success', 'text' => '9 Partien ohne Ergebnis']], session(ModuleFlash::KEY));
        $this->assertNull($this->matchRow('A-0')->winner);
        $this->assertOtherUntouched();
    }

    #[Test]
    public function unknown_scope_is_refused(): void
    {
        $this->selectedTestCopy();
        $admin = $this->admin();

        foreach ([[], ['scope' => 'alles'], ['scope' => ['all']], ['scope' => 'ALL']] as $input) {
            $this->actingAs($admin)->withSession($this->select($this->tournament))->post(route('tournament.admin.reset'), $input)
                ->assertRedirect(route('tournament.admin'))
                ->assertSessionHas('error', 'Unbekannter Umfang.');
        }
        $this->assertSame(9, TournamentMatch::query()->where('tid', $this->tournament->tid)->count());
    }

    #[Test]
    public function reset_results_clears_only_result_fields(): void
    {
        $copy = $this->selectedTestCopy();
        $this->play('A-0', 'a', ['entered_uid' => 5, 'entered' => '2027-05-03 18:00:00', 'confirmed_uid' => 6, 'confirmed' => '2027-05-04 09:00:00', 'retired' => 'ret_b', 'mtba' => null]);
        $this->schedule('A-1', '2027-05-10', '18:00:00', '2');
        $admin = $this->admin();

        $this->actingAs($admin)->withSession($this->select($copy))->post(route('tournament.admin.reset'), ['scope' => 'results'])
            ->assertRedirect(route('tournament.admin'));

        $this->assertSame([['type' => 'success', 'text' => '9 Partien ohne Ergebnis']], session(ModuleFlash::KEY));
        $m = $this->matchRow('A-0');
        foreach (['s1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb', 'sets_a', 'sets_b', 'games_a', 'games_b', 'score', 'winner', 'entered_uid', 'entered', 'confirmed_uid', 'confirmed'] as $column) {
            $this->assertNull($m->{$column}, $column);
        }
        $this->assertSame('none', $m->retired);
        $this->assertSame('2027-05-03', $m->played);
        $this->assertSame(['2027-05-10', '18:00:00', '2'], [$this->matchRow('A-1')->played, $this->matchRow('A-1')->played_time, $this->matchRow('A-1')->court]);
        $this->assertSame(9, TournamentMatch::query()->where('tid', $copy->tid)->count());
        $this->assertSame('A', TournamentPlayer::query()->whereKey($this->tpid[4])->value('grp'));
        $this->assertOtherUntouched();
    }

    #[Test]
    public function reset_draw_discards_real_draws_and_keeps_them_verifiable(): void
    {
        $real = $this->both();
        $draw = Draw::factory()->token(4, 'G1', ['A2', 'A3', 'B2', 'B3'])->executed()->closed()->create(['tid' => $real->tid]);
        $old = Draw::factory()->executed()->discarded('Erster Versuch')->create(['tid' => $real->tid, 'discarded' => '2027-03-01 10:00:00']);
        TournamentLine::query()->where('tid', $real->tid)->update(['placeholder' => 'W3', 'tpid' => $this->tpid[1]]);
        TournamentMatch::query()->where('tid', $real->tid)->where('phase', 'KO')->update(['tpid_a' => $this->tpid[1], 'tpid_b' => $this->tpid[2]]);
        TournamentPlayer::query()->where('tid', $real->tid)->update(['points_adjust' => -1]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tournament.admin.reset'), ['scope' => 'draw', 'confirm' => 'Demo-Meisterschaft', 'reason' => '  '])
            ->assertRedirect(route('tournament.admin'));

        $this->assertSame([
            ['type' => 'success', 'text' => '9 Partien ohne Ergebnis'],
            ['type' => 'success', 'text' => '4 Spieler zurück in den Lostopf (2 Köpfe bleiben)'],
            ['type' => 'success', 'text' => '6 Gruppenpartien gelöscht'],
            ['type' => 'success', 'text' => '4 Rasterlinien geleert'],
            ['type' => 'success', 'text' => '2 Ziehungen als VERWORFEN gekennzeichnet - sie bleiben im Protokoll sichtbar'],
            ['type' => 'info', 'text' => 'Jetzt "Aus Format erzeugen" aufrufen, damit Rasterlinien und KO-Partien neu entstehen.'],
        ], session(ModuleFlash::KEY));

        $draw->refresh();
        $this->assertNotNull($draw->discarded);
        $this->assertSame('Reset durch die Turnierleitung', $draw->discarded_reason);
        $this->assertTrue($this->app->make(DrawVerifier::class)->verify($draw)->ok());
        $this->assertSame(4, DrawToken::query()->where('did', $draw->did)->count());
        $old->refresh();
        $this->assertSame(['2027-03-01 10:00:00', 'Erster Versuch'], [$old->discarded, $old->discarded_reason]);

        $players = TournamentPlayer::query()->where('tid', $real->tid)->get()->keyBy('seed');
        foreach ([1 => 'A', 2 => 'B'] as $seed => $grp) {
            $this->assertSame([$grp, 1, -1], [$players[$seed]->grp, (int) $players[$seed]->grp_pos, (int) $players[$seed]->points_adjust]);
        }
        foreach ([3, 4, 5, 6] as $seed) {
            $this->assertSame([null, null, 0], [$players[$seed]->grp, $players[$seed]->grp_pos, (int) $players[$seed]->points_adjust]);
        }
        $this->assertSame(0, TournamentMatch::query()->where('tid', $real->tid)->where('phase', 'group')->count());
        $this->assertSame(3, TournamentMatch::query()->where('tid', $real->tid)->whereNull('tpid_a')->whereNull('tpid_b')->count());
        $this->assertSame(4, TournamentLine::query()->where('tid', $real->tid)->whereNull('placeholder')->whereNull('tpid')->count());
        $this->assertOtherUntouched();
    }

    #[Test]
    public function reset_draw_cuts_the_reason_to_160_characters(): void
    {
        $real = $this->both();
        $draw = Draw::factory()->executed()->create(['tid' => $real->tid]);

        $this->actingAs($this->admin())->post(route('tournament.admin.reset'), ['scope' => 'draw', 'confirm' => 'Demo-Meisterschaft', 'reason' => ' '.str_repeat('ä', 170).' ']);
        $this->assertSame(str_repeat('ä', 160), $draw->fresh()->discarded_reason);

        $second = Draw::factory()->executed()->create(['tid' => $real->tid]);
        $this->actingAs($this->admin())->post(route('tournament.admin.reset'), ['scope' => 'draw', 'confirm' => 'Demo-Meisterschaft', 'reason' => ['x']]);
        $this->assertSame('Reset durch die Turnierleitung', $second->fresh()->discarded_reason);
        $this->assertOtherUntouched();
    }

    #[Test]
    public function reset_draw_deletes_test_draws(): void
    {
        $copy = $this->selectedTestCopy();
        $draw = Draw::factory()->token(4, 'G1', ['A2', 'A3', 'B2', 'B3'])->executed()->create(['tid' => $copy->tid]);
        DrawEligible::query()->insert([['did' => $draw->did, 'tpid' => $this->tpid[3]], ['did' => $draw->did, 'tpid' => $this->tpid[4]]]);
        $direct = Draw::factory()->executed()->create(['tid' => $copy->tid]);
        $otherEligible = DrawEligible::query()->insert(['did' => Draw::query()->where('tid', $this->other->tid)->value('did'), 'tpid' => 1]);
        $this->otherBefore = $this->otherRows();
        $eligibleBefore = DrawEligible::query()->whereNotIn('did', [$draw->did, $direct->did])->count();

        $this->actingAs($this->admin())->withSession($this->select($copy))->post(route('tournament.admin.reset'), ['scope' => 'draw'])
            ->assertRedirect(route('tournament.admin'));

        $texts = array_column(session(ModuleFlash::KEY), 'text');
        $this->assertContains('2 Ziehungen gelöscht (Testturnier)', $texts);
        $this->assertSame(0, Draw::query()->where('tid', $copy->tid)->count());
        $this->assertSame(0, DrawToken::query()->whereIn('did', [$draw->did, $direct->did])->count());
        $this->assertSame(0, DrawEligible::query()->whereIn('did', [$draw->did, $direct->did])->count());
        $this->assertSame($eligibleBefore, DrawEligible::query()->count());
        $this->assertTrue($otherEligible);
        $this->assertOtherUntouched();
    }

    #[Test]
    public function reset_all_clears_matches_and_lines(): void
    {
        $copy = $this->selectedTestCopy();

        $this->actingAs($this->admin())->withSession($this->select($copy))->post(route('tournament.admin.reset'), ['scope' => 'all'])
            ->assertRedirect(route('tournament.admin'));

        $this->assertSame([
            ['type' => 'success', 'text' => '9 Partien ohne Ergebnis'],
            ['type' => 'success', 'text' => '4 Spieler zurück in den Lostopf (2 Köpfe bleiben)'],
            ['type' => 'success', 'text' => '6 Gruppenpartien gelöscht'],
            ['type' => 'success', 'text' => '4 Rasterlinien geleert'],
            ['type' => 'success', 'text' => '3 weitere Partien gelöscht'],
            ['type' => 'success', 'text' => '4 Rasterlinien gelöscht'],
            ['type' => 'info', 'text' => 'Jetzt "Aus Format erzeugen" aufrufen, damit Rasterlinien und KO-Partien neu entstehen.'],
        ], session(ModuleFlash::KEY));
        $this->assertSame(0, TournamentMatch::query()->where('tid', $copy->tid)->count());
        $this->assertSame(0, TournamentLine::query()->where('tid', $copy->tid)->count());
        $this->assertSame(6, TournamentPlayer::query()->where('tid', $copy->tid)->count());
        $this->assertOtherUntouched();
    }

    #[Test]
    public function lot_seed_moves_to_the_next_run_after_full_reset(): void
    {
        $real = $this->both();
        $first = Draw::factory()->executed(str_repeat('11', 32))->create(['tid' => $real->tid]);
        $m = $this->app->make(TournamentManager::class);
        $rows = [['tpid' => 3, 'needs_lot' => true], ['tpid' => 1, 'needs_lot' => true], ['tpid' => 2, 'needs_lot' => true]];
        $scope = 'phase:G1:place:2';
        $order = fn (string $seed) => (new DrawService)->shuffle([1, 2, 3], hash_hmac('sha256', $scope, $seed));

        $this->assertSame($order((string) $first->fresh()->final_seed), $m->derivedLotOrder((int) $real->tid, $scope, $rows));

        $this->app->make(ResetService::class)->reset($real, 'all', 'Demo-Meisterschaft', '', $this->admin());
        $this->assertNotNull($first->fresh()->discarded);

        // The new run draws again; its first executed draw is the new lot seed (the manager cache was dropped).
        $second = Draw::factory()->executed(str_repeat('22', 32))->create(['tid' => $real->tid]);
        $newSeed = (string) $second->fresh()->final_seed;
        $this->assertNotSame($first->final_seed, $newSeed);
        $this->assertSame($newSeed, $this->app->make(LotOrderService::class)->lotSeed($real));
        $this->assertSame($order($newSeed), $m->derivedLotOrder((int) $real->tid, $scope, $rows));
        $this->assertOtherUntouched();
    }

    // ------------------------------------------------------------------ routes

    #[Test]
    public function routes_require_login_admin_and_csrf_and_redirect_get(): void
    {
        $copy = $this->selectedTestCopy();
        $member = $this->member();
        $actions = ['simulate' => ['mode' => 'member'], 'simulate-results' => [], 'reset' => ['scope' => 'all']];

        foreach ($actions as $action => $input) {
            $this->post('/tournament/admin/'.$action, $input)
                ->assertRedirect(route('login', ['redirect_to' => '/tournament/admin/'.$action]));
        }
        foreach ($actions as $action => $input) {
            $this->actingAs($member)->withSession($this->select($copy))->post('/tournament/admin/'.$action, $input)->assertForbidden();
            $this->actingAs($this->admin())->get('/tournament/admin/'.$action)->assertRedirect(route('tournament.admin'));
        }
        $this->assertSame(9, TournamentMatch::query()->where('tid', $copy->tid)->count());
        $this->assertSame(0, TournamentMatch::query()->where('tid', $copy->tid)->whereNotNull('winner')->count());

        $this->withMiddleware();
        $this->app['env'] = 'local';
        foreach ($actions as $action => $input) {
            $this->actingAs($this->admin())->withSession($this->select($copy))->from(route('tournament.admin'))->post('/tournament/admin/'.$action, $input)
                ->assertRedirect(route('tournament.admin'))
                ->assertSessionHas('error', 'Ungültiger oder abgelaufener Formularschlüssel. Bitte Seite neu laden.');
        }
        $this->assertSame(9, TournamentMatch::query()->where('tid', $copy->tid)->count());
        $this->assertOtherUntouched();
    }

    #[Test]
    public function a_real_login_session_resets_the_selected_test_copy(): void
    {
        $copy = $this->selectedTestCopy();
        User::factory()->create(['status' => 'admin', 'email' => 'leitung@example.test', 'pw' => bcrypt('geheim-123')]);

        $this->post('/login', ['email' => 'leitung@example.test', 'password' => 'geheim-123']);
        $this->post(route('tournament.admin.select'), ['tid' => (string) $copy->tid]);
        $this->post(route('tournament.admin.reset'), ['scope' => 'all'])->assertRedirect(route('tournament.admin'));

        $this->assertSame(0, TournamentMatch::query()->where('tid', $copy->tid)->count());
        $this->assertOtherUntouched();
    }

    #[Test]
    public function the_admin_page_shows_the_test_tools(): void
    {
        $copy = $this->selectedTestCopy();
        $admin = $this->admin();

        $html = $this->actingAs($admin)->withSession($this->select($copy))->get(route('tournament.admin'))->assertOk()->getContent();
        foreach (['simulate', 'simulate-results', 'reset'] as $name) {
            $this->assertStringContainsString('action="'.route('tournament.admin.'.$name).'"', $html);
        }

        // The real (other) tournament: no random results, and the reset asks for the title.
        $this->actingAs($admin)->withSession($this->select($this->other))->get(route('tournament.admin'))->assertOk()
            ->assertDontSee('action="'.route('tournament.admin.simulate-results').'"', false)
            ->assertSee('name="confirm"', false);
    }
}
