<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Support\AccessContext;
use App\Modules\Tournament\Support\MatchPresenter;
use App\Modules\Tournament\Support\TournamentAccess;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchPresenterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Tournament $tournament;

    /** @var array<int, array<string, mixed>> */
    private array $players = [
        11 => ['tpid' => 11, 'label' => 'Demo Spieler 11'],
        12 => ['tpid' => 12, 'label' => 'Demo Spieler 12'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // The match page arrives with the match controller task; the presenter only needs its name.
        if (! Route::has('tournament.match')) {
            Route::get('tournament/match/{tmid}', fn () => 'match')->whereNumber('tmid')->name('tournament.match');
            Route::getRoutes()->refreshNameLookups();
        }

        $this->tournament = Tournament::factory()->create();
    }

    /** @param  array<string, mixed>  $overrides */
    private function match(array $overrides = []): array
    {
        return $overrides + [
            'tmid' => 7, 'tid' => (int) $this->tournament->tid, 'phase' => 'group', 'mkey' => 'B-3', 'grp' => 'B', 'rnd' => null, 'slot' => 4,
            'tpid_a' => 11, 'tpid_b' => 12, 'played' => null, 'played_time' => null, 'court' => null,
            's1a' => null, 's1b' => null, 's2a' => null, 's2b' => null, 'mtba' => null, 'mtbb' => null,
            'retired' => 'none', 'winner' => null, 'confirmed_uid' => null,
        ];
    }

    private function access(int $level, ?int $tpid, bool $canWrite = true, ?array $simulation = null): AccessContext
    {
        return new AccessContext($level, $tpid ? $this->players[$tpid] : null, $tpid, $simulation, $canWrite, null);
    }

    /** @param  array<string, mixed>  $match */
    private function present(array $match, ?AccessContext $access, ?Tournament $t = null): array
    {
        return $this->app->make(MatchPresenter::class)->present($match, $this->players, $access, $t ?? $this->tournament);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string, 2: array{0: string, 1: string}, 3: bool, 4: array<string, string>|null}> */
    public static function states(): array
    {
        $scheduled = ['played' => '2027-05-03', 'played_time' => '17:00:00', 'court' => '2'];
        $played = $scheduled + ['s1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6, 'mtba' => 10, 'mtbb' => 8, 'winner' => 11];

        return [
            'open, own match' => [[], 'open', ['attention', 'ohne Termin'], true, ['label' => 'Termin eintragen', 'variant' => 'primary']],
            'open, foreign match' => [[], 'open', ['attention', 'ohne Termin'], false, null],
            'scheduled, own match' => [$scheduled, 'scheduled', ['neutral', 'geplant'], true, ['label' => 'Ergebnis eintragen', 'variant' => 'secondary']],
            'scheduled, foreign match' => [$scheduled, 'scheduled', ['neutral', 'geplant'], false, null],
            'played, own match' => [$played, 'played', ['good', 'gespielt'], true, null],
            'played and confirmed' => [$played + ['confirmed_uid' => 5], 'played', ['good', 'gespielt · bestätigt'], true, null],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array{0: string, 1: string}  $badge
     * @param  array<string, string>|null  $action
     */
    #[Test]
    #[DataProvider('states')]
    public function states_and_actions(array $overrides, string $state, array $badge, bool $own, ?array $action): void
    {
        $props = $this->present($this->match($overrides), $this->access(TournamentAccess::PARTICIPANT, $own ? 11 : null));

        $this->assertSame(['group' => $badge[0], 'label' => $badge[1]], $props['badge'], $state);
        if ($action === null) {
            $this->assertNull($props['action']);
        } else {
            $this->assertSame($action + ['url' => route('tournament.match', ['tmid' => 7])], $props['action']);
        }
    }

    #[Test]
    public function group_context_players_and_score(): void
    {
        $props = $this->present($this->match(['played' => '2027-05-03', 's1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6, 'mtba' => 10, 'mtbb' => 8, 'winner' => 11]), null);

        $this->assertSame('Gruppe B · Spiel 4', $props['context']);
        $this->assertSame([['name' => 'Demo Spieler 11', 'winner' => true], ['name' => 'Demo Spieler 12', 'winner' => false]], $props['players']);
        $this->assertSame(['6 3 10', '4 6 8'], $props['score']);
        $this->assertNull($props['action'], 'no access context: read only');
    }

    #[Test]
    public function ko_context_uses_the_phase_label_and_round(): void
    {
        $props = $this->present($this->match(['phase' => 'KO_A', 'mkey' => 'KO_A-R2-1', 'grp' => null, 'rnd' => 2, 'slot' => 1, 'tpid_a' => null, 'tpid_b' => 12]), null);

        $this->assertSame('KO-Bewerb A · Runde 2', $props['context']);
        $this->assertSame('offen', $props['players'][0]['name']);
    }

    #[Test]
    public function walkover_shows_w_o_for_the_winner(): void
    {
        $props = $this->present($this->match(['retired' => 'wo_b', 'winner' => 11]), null);

        $this->assertSame(['w.o.', ''], $props['score']);
        $this->assertSame(['good', 'gespielt'], [$props['badge']['group'], $props['badge']['label']]);
    }

    #[Test]
    public function unplayed_match_has_no_meta_and_empty_scores(): void
    {
        $props = $this->present($this->match(), null);

        $this->assertNull($props['meta']);
        $this->assertSame(['', ''], $props['score']);
    }

    #[Test]
    public function court_alias_comes_from_court_names(): void
    {
        config(['tournament-base.courts' => [1 => 'Alpha']]);

        $props = $this->present($this->match(['played' => '2027-05-03', 'played_time' => '17:00:00', 'court' => '1']), null);
        $this->assertSame('Mo, 3. Mai · 17:00 · Alpha', $props['meta']);

        $props = $this->present($this->match(['played' => '2027-11-04', 'court' => '2']), null);
        $this->assertSame('Do, 4. Nov · Platz 2', $props['meta'], 'no alias configured: the plain court label');

        $props = $this->present($this->match(['played' => '2027-11-04']), null);
        $this->assertSame('Do, 4. Nov', $props['meta']);
    }

    #[Test]
    public function admins_get_actions_on_every_match(): void
    {
        $props = $this->present($this->match(), $this->access(TournamentAccess::ADMIN, null));

        $this->assertSame('Termin eintragen', $props['action']['label']);
    }

    #[Test]
    public function members_get_no_action(): void
    {
        $this->assertNull($this->present($this->match(), $this->access(TournamentAccess::MEMBER, null))['action']);
    }

    #[Test]
    public function simulated_player_writes_only_own_matches_and_only_in_test_tournaments(): void
    {
        $simulation = ['mode' => 'player', 'label' => 'Demo Spieler 11', 'tpid' => 11, 'realLevel' => TournamentAccess::ADMIN];
        $sim = $this->access(TournamentAccess::PARTICIPANT, 11, false, $simulation);
        $test = Tournament::factory()->test()->create();

        $this->assertNull($this->present($this->match(), $sim)['action'], 'real tournament: read only');
        $this->assertSame('Termin eintragen', $this->present($this->match(), $sim, $test)['action']['label']);
        $this->assertNull($this->present($this->match(['tpid_a' => 13]), $sim, $test)['action'], 'foreign match stays locked');

        $member = new AccessContext(TournamentAccess::MEMBER, null, null, ['mode' => 'member', 'label' => 'x', 'realLevel' => 3], false, null);
        $this->assertNull($this->present($this->match(), $member, $test)['action']);
    }

    #[Test]
    public function presented_props_render_in_the_component(): void
    {
        $props = $this->present($this->match(), $this->access(TournamentAccess::PARTICIPANT, 11));

        $html = Blade::render('<x-tournament-base::match-card :context="$context" :badge="$badge" :players="$players" :score="$score" :meta="$meta" :action="$action" />', $props);

        $this->assertStringContainsString('Gruppe B · Spiel 4', $html);
        $this->assertStringContainsString('noch kein Termin', $html);
        $this->assertStringContainsString('Termin eintragen', $html);
    }
}
