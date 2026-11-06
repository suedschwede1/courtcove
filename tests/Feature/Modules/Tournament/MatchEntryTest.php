<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\TournamentBase\Support\ModuleFlash;
use App\Modules\TournamentBase\Support\TestModeSession;
use App\Services\UiLinter;
use App\Services\UiRules;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Match entry (port of ZF2's MatchController edit/save/confirm/delete): who may write, the score rules of
 * ScoreService, walkovers, schedule-only saves, the opponent's confirmation and the lock on a confirmed
 * result.
 */
class MatchEntryTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    private User $p1;

    private User $p4;

    private User $p5;

    private TournamentMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->p1 = $this->member(['email' => 'spieler01@example.test']);
        $this->p4 = $this->member(['email' => 'spieler04@example.test']);
        $this->p5 = $this->member(['email' => 'spieler05@example.test']);
        $this->championship([1 => $this->p1, 4 => $this->p4, 5 => $this->p5]);
        $this->match = $this->pair(1, 4);
    }

    /** The group match between two seeds (whichever side each one plays). */
    private function pair(int $seedX, int $seedY, ?Tournament $t = null): TournamentMatch
    {
        $x = $this->tpid[$seedX];
        $y = $this->tpid[$seedY];

        return TournamentMatch::query()->where('tid', ($t ?? $this->tournament)->tid)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('tpid_a', $x)->where('tpid_b', $y))
                ->orWhere(fn ($q) => $q->where('tpid_a', $y)->where('tpid_b', $x)))
            ->firstOrFail();
    }

    private function url(string $action = '', ?TournamentMatch $m = null): string
    {
        $tmid = (int) ($m ?? $this->match)->tmid;

        return $action === '' ? route('tournament.match', ['tmid' => $tmid]) : route('tournament.match.'.$action, ['tmid' => $tmid]);
    }

    /** @param  array<string, mixed>  $data */
    private function save(User $user, array $data, ?TournamentMatch $m = null): TestResponse
    {
        $this->app->forgetScopedInstances();

        return $this->actingAs($user)->post($this->url('save', $m), $data);
    }

    private function fresh(?TournamentMatch $m = null): TournamentMatch
    {
        return TournamentMatch::query()->findOrFail(($m ?? $this->match)->tmid);
    }

    private function assertFlash(string $type, string $text): void
    {
        $this->assertContains(['type' => $type, 'text' => $text], session(ModuleFlash::KEY, []));
    }

    private function simulate(User $admin, string $mode, ?int $tpid = null): void
    {
        $this->actingAs($admin);
        $this->app->make(TestModeSession::class)->simulate($admin, $mode, $tpid);
    }

    /** The user who plays side a of the test match. */
    private function sideA(): User
    {
        return (int) $this->match->tpid_a === $this->tpid[1] ? $this->p1 : $this->p4;
    }

    private function sideB(): User
    {
        return (int) $this->match->tpid_a === $this->tpid[1] ? $this->p4 : $this->p1;
    }

    #[Test]
    public function own_player_saves_a_valid_result(): void
    {
        $this->save($this->sideA(), ['s1a' => '6', 's1b' => '4', 's2a' => '3', 's2b' => '6', 'mtba' => '10', 'mtbb' => '8'])
            ->assertRedirect($this->url());

        $m = $this->fresh();
        $this->assertSame((int) $m->tpid_a, (int) $m->winner);
        $this->assertSame([2, 1, 10, 10], [(int) $m->sets_a, (int) $m->sets_b, (int) $m->games_a, (int) $m->games_b]);
        $this->assertSame('6:4 3:6 10:8', $m->score);
        $this->assertSame('none', $m->retired);
        $this->assertSame((int) $this->sideA()->uid, (int) $m->entered_uid);
        $this->assertNotNull($m->entered);
        $this->assertNull($m->confirmed_uid);
        $this->assertNull($m->confirmed);
        $this->assertFlash('success', 'Ergebnis 6:4 3:6 10:8 gespeichert. Der Gegner muss es noch bestätigen.');
    }

    #[Test]
    public function invalid_score_is_rejected_with_the_score_service_text(): void
    {
        $before = $this->fresh()->getAttributes();

        $this->save($this->sideA(), ['s1a' => '6', 's1b' => '4', 's2a' => '3', 's2b' => '6', 'mtba' => '10', 'mtbb' => '9'])
            ->assertRedirect($this->url());

        $this->assertFlash('error', __('tournament-base::score.mtb_ten_nine', ['score' => '10:9']));
        $this->assertSame('Match-Tiebreak 10:9 - bei 10:9 muss weitergespielt werden.', __('tournament-base::score.mtb_ten_nine', ['score' => '10:9']));
        $this->assertSame($before, $this->fresh()->getAttributes());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function hostileInputs(): array
    {
        return [
            'array score' => [['s1a' => ['6'], 's1b' => '4', 's2a' => '6', 's2b' => '4']],
            'array date' => [['date' => ['2027-05-01'], 'time' => '18:00']],
            'array wo' => [['wo' => ['a']]],
            'array court' => [['court' => ['1'], 'date' => '2027-05-01', 'time' => '18:00']],
            'three digits' => [['s1a' => '100', 's1b' => '4', 's2a' => '6', 's2b' => '4']],
            'huge tie-break' => [['s1a' => '6', 's1b' => '4', 's2a' => '4', 's2b' => '6', 'mtba' => '999', 'mtbb' => '997']],
            'bad date' => [['date' => '2027-02-30', 'time' => '18:00']],
            'date text' => [['date' => 'morgen', 'time' => '18:00']],
            'bad time' => [['date' => '2027-05-01', 'time' => '25:00']],
        ];
    }

    /** @param  array<string, mixed>  $data */
    #[Test]
    #[DataProvider('hostileInputs')]
    public function hostile_input_never_breaks_the_page_and_writes_nothing_invalid(array $data): void
    {
        $response = $this->save($this->sideA(), $data);

        $response->assertRedirect($this->url());
        $m = $this->fresh();
        $this->assertNotSame('2027-02-30', $m->played);
        $this->assertNotSame('morgen', $m->played);
        $this->assertNotSame('25:00', $m->played_time);
        $this->assertNull($m->court);
        $this->assertTrue($m->winner === null || in_array((int) $m->winner, [(int) $m->tpid_a, (int) $m->tpid_b], true));
    }

    #[Test]
    public function invalid_date_and_time_get_their_own_message(): void
    {
        $this->save($this->sideA(), ['date' => '2027-02-30', 'time' => '18:00']);
        $this->assertFlash('error', 'Bitte ein gültiges Datum eintragen.');

        $this->save($this->sideA(), ['date' => '2027-05-01', 'time' => '7 Uhr']);
        $this->assertFlash('error', 'Bitte eine gültige Uhrzeit eintragen.');
        $this->assertNull($this->fresh()->played);
    }

    #[Test]
    public function walkover_stores_6_0_6_0_and_opponent_wins(): void
    {
        $this->save($this->sideA(), ['wo' => 'a', 's1a' => '6', 's1b' => '1'])->assertRedirect($this->url());

        $m = $this->fresh();
        $this->assertSame('wo_a', $m->retired);
        $this->assertSame((int) $m->tpid_b, (int) $m->winner);
        $this->assertSame('6:0 6:0', $m->score);
        $this->assertSame([0, 6, 0, 6], [(int) $m->s1a, (int) $m->s1b, (int) $m->s2a, (int) $m->s2b]);
        $this->assertSame([0, 2, 0, 12], [(int) $m->sets_a, (int) $m->sets_b, (int) $m->games_a, (int) $m->games_b]);
        $this->assertNull($m->mtba);
        $this->assertNull($m->confirmed_uid);
        $this->assertFlash('success', 'Als w.o. gewertet (6:0 6:0). Der Gegner muss es noch bestätigen.');

        $this->save($this->sideA(), ['wo' => 'b']);
        $m = $this->fresh();
        $this->assertSame(['wo_b', (int) $m->tpid_a, 2, 12], [$m->retired, (int) $m->winner, (int) $m->sets_a, (int) $m->games_a]);
    }

    #[Test]
    public function changing_a_result_resets_confirmation(): void
    {
        $this->save($this->sideA(), ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3']);
        $this->app->forgetScopedInstances();
        $this->actingAs($this->sideB())->post($this->url('confirm'))->assertRedirect($this->url());
        $this->assertNotNull($this->fresh()->confirmed_uid);

        // The admin corrects a confirmed result: the confirmation is reset on purpose.
        $this->save($this->admin(), ['s1a' => '4', 's1b' => '6', 's2a' => '3', 's2b' => '6']);

        $m = $this->fresh();
        $this->assertSame('4:6 3:6', $m->score);
        $this->assertSame((int) $m->tpid_b, (int) $m->winner);
        $this->assertNull($m->confirmed_uid);
        $this->assertNull($m->confirmed);
    }

    #[Test]
    public function enterer_cannot_confirm_but_opponent_and_admin_can(): void
    {
        $this->app->forgetScopedInstances();
        $this->actingAs($this->sideA())->post($this->url('confirm'));
        $this->assertFlash('error', 'Es gibt noch kein Ergebnis zu bestätigen.');

        $this->save($this->sideA(), ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3']);

        $this->app->forgetScopedInstances();
        $this->actingAs($this->sideA())->post($this->url('confirm'))->assertRedirect($this->url());
        $this->assertFlash('error', 'Das Ergebnis muss vom Gegner bestätigt werden, nicht von dir.');
        $this->assertNull($this->fresh()->confirmed_uid);

        $this->app->forgetScopedInstances();
        $this->actingAs($this->sideB())->post($this->url('confirm'));
        $this->assertFlash('success', 'Ergebnis bestätigt.');
        $this->assertSame((int) $this->sideB()->uid, (int) $this->fresh()->confirmed_uid);
        $this->assertNotNull($this->fresh()->confirmed);

        // An admin may confirm even a result he entered himself.
        $admin = $this->admin();
        $other = $this->pair(1, 5);
        $this->save($admin, ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3'], $other);
        $this->app->forgetScopedInstances();
        $this->actingAs($admin)->post($this->url('confirm', $other));
        $this->assertSame((int) $admin->uid, (int) $this->fresh($other)->confirmed_uid);
    }

    #[Test]
    public function confirmed_result_is_locked_for_participants(): void
    {
        $this->save($this->sideA(), ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3']);
        $this->app->forgetScopedInstances();
        $this->actingAs($this->sideB())->post($this->url('confirm'));
        $confirmed = $this->fresh()->getAttributes();

        foreach ([$this->sideA(), $this->sideB()] as $player) {
            $this->save($player, ['s1a' => '4', 's1b' => '6', 's2a' => '3', 's2b' => '6'])->assertRedirect($this->url());
            $this->assertFlash('error', 'Das Ergebnis ist bereits bestätigt. Änderungen kann nur die Turnierleitung vornehmen.');
            $this->save($player, ['wo' => 'b']);
            $this->save($player, ['date' => '2027-06-01', 'time' => '09:00']);
            $this->app->forgetScopedInstances();
            $this->actingAs($player)->post($this->url('delete'))->assertRedirect($this->url());
            $this->assertFlash('error', 'Das Ergebnis ist bereits bestätigt. Änderungen kann nur die Turnierleitung vornehmen.');
            $this->assertSame($confirmed, $this->fresh()->getAttributes());
        }

        // The page shows neither the save button nor the delete form to the participants.
        $this->app->forgetScopedInstances();
        $html = $this->actingAs($this->sideA())->get($this->url())->assertOk()->getContent();
        $this->assertStringNotContainsString('action="'.$this->url('delete').'"', $html);
        $this->assertStringNotContainsString('action="'.$this->url('confirm').'"', $html);
        $this->assertStringContainsString('vom Gegner bestätigt', $html);

        // The admin may change and delete.
        $admin = $this->admin();
        $this->save($admin, ['s1a' => '7', 's1b' => '5', 's2a' => '6', 's2b' => '3']);
        $this->assertSame('7:5 6:3', $this->fresh()->score);
        $this->app->forgetScopedInstances();
        $this->actingAs($admin)->post($this->url('confirm'));
        $this->app->forgetScopedInstances();
        $this->actingAs($admin)->post($this->url('delete'));
        $this->assertNull($this->fresh()->winner);
    }

    #[Test]
    public function stranger_cannot_write(): void
    {
        foreach (['save' => ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3'], 'confirm' => [], 'delete' => []] as $action => $data) {
            $this->app->forgetScopedInstances();
            $this->actingAs($this->p5)->post($this->url($action), $data)->assertStatus(302)->assertRedirect($this->url());
            $this->assertFlash('error', 'Du kannst nur Ergebnisse deiner eigenen Partien eintragen.');
        }

        $this->assertNull($this->fresh()->winner);

        // A plain member (no participant) neither.
        $this->save($this->member(), ['date' => '2027-05-01', 'time' => '18:00']);
        $this->assertFlash('error', 'Du kannst nur Ergebnisse deiner eigenen Partien eintragen.');
        $this->assertNull($this->fresh()->played);

        // The page itself is readable, with the read-only notice and no save button.
        $this->app->forgetScopedInstances();
        $html = $this->actingAs($this->p5)->get($this->url())->assertOk()->getContent();
        $this->assertStringContainsString('Nur die beiden Spieler dieser Partie oder die Turnierleitung können hier etwas eintragen.', $html);
        $this->assertStringNotContainsString('<span>Speichern</span>', $html);
    }

    #[Test]
    public function simulated_player_may_write_only_in_test_tournaments(): void
    {
        $admin = $this->admin();
        $tpidA = (int) $this->match->tpid_a;

        // Real tournament: the simulation is read-only, even for the simulated player's own match.
        $this->simulate($admin, 'player', $tpidA);
        $this->post($this->url('save'), ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3'])->assertRedirect($this->url());
        $this->assertFlash('error', 'Im Testmodus ist das Eintragen gesperrt. Beende ihn oben im Balken.');
        $this->assertNull($this->fresh()->winner);

        // Member simulation: read-only as well.
        $this->simulate($admin, 'member');
        $this->app->forgetScopedInstances();
        $this->post($this->url('save'), ['date' => '2027-05-01', 'time' => '18:00']);
        $this->assertFlash('error', 'Im Testmodus ist das Eintragen gesperrt. Beende ihn oben im Balken.');
        $this->assertNull($this->fresh()->played);

        // Test tournament: the simulated player writes his own match, entered by the admin's real uid.
        // (A test tournament is never the current one: the admin selects it first.)
        $this->tournament->update(['is_test' => 1]);
        $this->app->make(TestModeSession::class)->selectTid($admin, (int) $this->tournament->tid);
        $this->simulate($admin, 'player', $tpidA);
        $this->app->forgetScopedInstances();
        $this->post($this->url('save'), ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3'])->assertRedirect($this->url());
        $m = $this->fresh();
        $this->assertSame((int) $m->tpid_a, (int) $m->winner);
        $this->assertSame((int) $admin->uid, (int) $m->entered_uid);

        $this->app->forgetScopedInstances();
        $html = $this->get($this->url())->assertOk()->getContent();
        $this->assertStringContainsString('Du trägst als', $html);

        // ... but not a foreign match of the test tournament.
        $foreign = $this->pair(4, 5);
        $this->app->forgetScopedInstances();
        $this->post($this->url('save', $foreign), ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3']);
        $this->assertFlash('error', 'Im Testmodus ist das Eintragen gesperrt. Beende ihn oben im Balken.');
        $this->assertNull($this->fresh($foreign)->winner);
    }

    #[Test]
    public function schedule_without_result_needs_date_and_time(): void
    {
        $this->save($this->sideA(), ['date' => '2027-05-01', 'time' => '']);
        $this->assertFlash('error', 'Ohne Ergebnis müssen Datum und Uhrzeit gesetzt sein.');
        $this->assertNull($this->fresh()->played);

        $this->save($this->sideA(), ['date' => '2027-05-01', 'time' => '18:15', 'court' => '2', 'wo' => ''])->assertRedirect($this->url());
        $this->assertFlash('success', 'Termin gespeichert.');
        $m = $this->fresh();
        $this->assertSame('2027-05-01', $m->played);
        $this->assertSame('18:15:00', $m->played_time);
        $this->assertSame('2', (string) $m->court);
        $this->assertNull($m->winner);

        // An unknown court is stored as none (ZF2).
        $this->save($this->sideA(), ['date' => '2027-05-01', 'time' => '18:15', 'court' => '4']);
        $this->assertNull($this->fresh()->court);
    }

    #[Test]
    public function delete_keeps_the_date(): void
    {
        $this->save($this->sideA(), ['date' => '2027-05-01', 'time' => '18:00', 'court' => '1', 'wo' => 'b']);
        $this->app->forgetScopedInstances();
        $this->actingAs($this->sideB())->post($this->url('delete'))->assertRedirect($this->url());

        $this->assertFlash('success', 'Ergebnis gelöscht, der Termin bleibt bestehen.');
        $m = $this->fresh();
        foreach (['s1a', 's1b', 's2a', 's2b', 'mtba', 'mtbb', 'sets_a', 'sets_b', 'games_a', 'games_b', 'score', 'winner', 'entered_uid', 'entered', 'confirmed_uid', 'confirmed'] as $column) {
            $this->assertNull($m->{$column}, $column);
        }
        $this->assertSame('none', $m->retired);
        $this->assertSame(['2027-05-01', '18:00:00', '1'], [$m->played, $m->played_time, (string) $m->court]);
    }

    #[Test]
    public function result_needs_both_players(): void
    {
        $ko = TournamentMatch::query()->where('tid', $this->tournament->tid)->where('phase', '!=', 'group')->whereNull('tpid_a')->firstOrFail();

        $this->save($this->admin(), ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3'], $ko)->assertRedirect($this->url('', $ko));
        $this->assertFlash('error', 'Ein Ergebnis kann erst eingetragen werden, wenn beide Spieler feststehen.');
        $this->assertNull($this->fresh($ko)->winner);

        // A date can be set already (ZF2).
        $this->save($this->admin(), ['date' => '2027-06-01', 'time' => '10:00'], $ko);
        $this->assertSame('2027-06-01', $this->fresh($ko)->played);
    }

    #[Test]
    public function foreign_and_unknown_matches_404_on_every_route(): void
    {
        $other = Tournament::factory()->create(['season' => 2020]);
        $foreignPlayer = TournamentPlayer::factory()->create(['tid' => $other->tid, 'uid' => $this->p1->uid, 'label' => 'Demo Spieler 99', 'seed' => 1, 'itn_rated' => 5.0]);
        $foreign = TournamentMatch::factory()->group('A', 1, 1)->create(['tid' => $other->tid, 'tpid_a' => $foreignPlayer->tpid]);

        foreach ([$this->p1, $this->admin()] as $user) {
            $this->app->forgetScopedInstances();
            $this->actingAs($user)->get($this->url('', $foreign))->assertNotFound();
            foreach (['save', 'confirm', 'delete'] as $action) {
                $this->app->forgetScopedInstances();
                $this->actingAs($user)->post($this->url($action, $foreign), ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3'])->assertNotFound();
                $this->actingAs($user)->post(url('/tournament/match/999999/'.$action))->assertNotFound();
            }
        }

        $this->assertNull($foreign->fresh()->winner);
        $this->actingAs($this->p1)->get('/tournament/match/x')->assertNotFound();
    }

    #[Test]
    public function admin_selection_of_another_tournament_decides_the_match_scope(): void
    {
        $admin = $this->admin();
        $current = $this->match;
        $other = Tournament::factory()->create(['season' => 2020]);
        $otherMatch = TournamentMatch::factory()->group('A', 1, 1)->create(['tid' => $other->tid]);

        $this->actingAs($admin);
        $this->app->make(TestModeSession::class)->selectTid($admin, (int) $other->tid);

        $this->app->forgetScopedInstances();
        $this->get($this->url('', $otherMatch))->assertOk();
        $this->app->forgetScopedInstances();
        $this->get($this->url('', $current))->assertNotFound();
        $this->app->forgetScopedInstances();
        $this->post($this->url('save', $current), ['date' => '2027-05-01', 'time' => '18:00'])->assertNotFound();
        $this->assertNull($this->fresh($current)->played);
    }

    #[Test]
    public function get_on_post_paths_redirects_to_the_match(): void
    {
        foreach (['save', 'confirm', 'delete'] as $action) {
            $this->app->forgetScopedInstances();
            $this->actingAs($this->sideA())->get($this->url($action))->assertRedirect($this->url());
        }

        $this->get(url('/tournament/match/999999/save'))->assertNotFound();
    }

    #[Test]
    public function guests_go_to_login_and_a_real_login_gets_through(): void
    {
        $this->post($this->url('save'), ['date' => '2027-05-01', 'time' => '18:00'])->assertRedirect();
        $this->assertNull($this->fresh()->played);
        $this->get($this->url())->assertRedirect(route('login', ['redirect_to' => '/tournament/match/'.$this->match->tmid]));

        $user = $this->sideA();
        $user->forceFill(['pw' => bcrypt('geheim-123')])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'geheim-123']);
        $this->assertAuthenticatedAs($user);

        $this->get($this->url())->assertOk()->assertSee('Spiel bearbeiten');
        $this->post($this->url('save'), ['date' => '2027-05-01', 'time' => '18:00'])->assertRedirect($this->url());
        $this->assertSame('2027-05-01', $this->fresh()->played);
    }

    #[Test]
    public function every_post_form_carries_csrf(): void
    {
        $this->save($this->sideA(), ['s1a' => '6', 's1b' => '4', 's2a' => '6', 's2b' => '3']);

        foreach ([$this->sideB(), $this->admin(), $this->sideA(), $this->p5] as $user) {
            $this->app->forgetScopedInstances();
            $html = $this->actingAs($user)->get($this->url())->assertOk()->getContent();
            $this->assertSame(substr_count(strtolower($html), 'method="post"'), substr_count($html, 'name="_token"'));
        }

        $this->app->forgetScopedInstances();
        $html = $this->actingAs($this->sideB())->get($this->url())->getContent();
        $this->assertStringContainsString('action="'.$this->url('save').'"', $html);
        $this->assertStringContainsString('action="'.$this->url('confirm').'"', $html);
        $this->assertStringContainsString('action="'.$this->url('delete').'"', $html);
    }

    #[Test]
    public function the_page_shows_the_form_validator_texts_and_contacts_only_to_participants(): void
    {
        TournamentPlayer::query()->whereKey($this->tpid[4])->update(['phone' => '0664 1234567']);
        TournamentPlayer::query()->whereKey($this->tpid[1])->update(['phone' => '0664 7654321']);

        $this->app->forgetScopedInstances();
        $response = $this->actingAs($this->p1)->get($this->url())->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Spiel bearbeiten', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('data-scroll-save', $html);
        $this->assertStringContainsString('data-scroll-restore', $html);
        $this->assertSame(6, substr_count($html, 'ds-input--score'));
        // the live validator gets the server texts of ScoreService::describe() verbatim
        $messages = $response->viewData('validator')['messages'];
        foreach (['schedule_only', 'enter_sets', 'set1_invalid', 'set2_invalid', 'mtb_must_be_empty', 'mtb_needed', 'mtb_invalid'] as $key) {
            $this->assertSame(__('tournament-base::score.describe.'.$key), $messages[$key], $key);
        }
        $this->assertStringContainsString('tournamentScoreForm(', $html);
        $this->assertStringContainsString('Satzergebnisse eintragen – z. B. 6 : 4 und 6 : 3.', $html);
        $this->assertStringContainsString('https://wa.me/436641234567', $html);
        $this->assertStringNotContainsString('436647654321', $html);
        // no ITN, e-mail or raw phone number of anybody
        $this->assertStringNotContainsString('ITN', $html);
        $this->assertStringNotContainsString('@example.test', $html);
        $this->assertStringNotContainsString('0664 1234567', $html);

        // A plain member sees the match but no contact.
        $this->app->forgetScopedInstances();
        $html = $this->actingAs($this->member())->get($this->url())->assertOk()->getContent();
        $this->assertStringNotContainsString('wa.me', $html);
    }

    #[Test]
    public function played_match_shows_the_entered_result(): void
    {
        $this->save($this->sideA(), ['wo' => 'b']);
        $this->app->forgetScopedInstances();
        $html = $this->actingAs($this->sideB())->get($this->url())->assertOk()->getContent();

        $labelB = TournamentPlayer::query()->findOrFail($this->match->tpid_b)->label;
        $this->assertStringContainsString('Eingetragenes Ergebnis', $html);
        $this->assertStringContainsString('w.o. ('.$labelB.' nicht angetreten)', $html);
        $this->assertStringContainsString('noch nicht bestätigt', $html);
        $this->assertStringContainsString('Ergebnis ändern', $html);
    }

    #[Test]
    public function views_pass_the_ui_linter(): void
    {
        $this->assertSame([], (new UiLinter(app_path('Modules/Tournament/resources/views'), UiRules::all()))->run());
    }
}
