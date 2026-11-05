<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerRoundPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(): User
    {
        return User::factory()->status('enabled')->create();
    }

    /** @return list<int> participant ids, labels "Spieler A", "Spieler B", ... */
    private function participants(MixedEvent $event, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = (int) MixedParticipant::factory()->create([
                'event_id' => $event->id,
                'label' => 'Spieler '.chr(65 + $i),
                'gender' => $i % 2 === 0 ? 'f' : 'm',
            ])->id;
        }

        return $ids;
    }

    /**
     * @param  array{int, int, int, int}  $p  team a (p[0], p[1]) against team b (p[2], p[3])
     */
    private function match(MixedRound $round, int $groupNo, int $matchNo, array $p, ?int $gamesA = null, ?int $gamesB = null, bool $extra = false): MixedMatch
    {
        return MixedMatch::factory()->create([
            'round_id' => $round->id,
            'group_no' => $groupNo,
            'match_no' => $matchNo,
            'team_a_p1' => $p[0],
            'team_a_p2' => $p[1],
            'team_b_p1' => $p[2],
            'team_b_p2' => $p[3],
            'games_a' => $gamesA,
            'games_b' => $gamesB,
            'is_extra' => $extra ? 1 : 0,
        ]);
    }

    /** One rotation (3 matches) per group of four. */
    private function group(MixedRound $round, int $groupNo, array $p, bool $scored = false): void
    {
        $this->match($round, $groupNo, 1, [$p[0], $p[1], $p[2], $p[3]], $scored ? 3 : null, $scored ? 1 : null);
        $this->match($round, $groupNo, 2, [$p[0], $p[2], $p[1], $p[3]], $scored ? 2 : null, $scored ? 2 : null);
        $this->match($round, $groupNo, 3, [$p[0], $p[3], $p[1], $p[2]]);
    }

    #[Test]
    public function americano_round_shows_groups_waves_and_top5(): void
    {
        config(['tournament-base.courts' => [1 => 'Alpha', 2 => 'Beta']]);
        $event = MixedEvent::factory()->create(['court_count' => 2]);
        $p = $this->participants($event, 12);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'confirmed', 'distribution' => 'itn_balanced']);
        $this->group($round, 0, array_slice($p, 0, 4), true);
        $this->group($round, 1, array_slice($p, 4, 4));
        $this->group($round, 2, array_slice($p, 8, 4));

        $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()
            ->assertSee('Verteilung: ITN-ausgeglichen')
            ->assertSee('Gruppe 1 · 2/3')
            ->assertSee('Gruppe 3 · 0/3')
            ->assertSee('Gruppe 1 · Welle 1')
            ->assertSee('Gruppe 3 · Welle 2')
            ->assertSee('2 Plätze, also 2 Wellen.')
            ->assertSee('Spieler A &amp; Spieler B', false)
            ->assertSee('3:1')
            ->assertSee('noch offen')
            ->assertSee('Live-Wertung · Top 5')
            ->assertSee('Vollständige Tabelle')
            ->assertSee(route('mixed.standings', ['event' => $event->id]))
            ->assertDontSee('Platz 1')
            ->assertDontSee('Alpha')
            ->assertDontSee('4:0');
    }

    #[Test]
    public function americano_round_marks_extra_group_and_double_players(): void
    {
        $event = MixedEvent::factory()->create(['court_count' => 4]);
        $p = $this->participants($event, 6);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'confirmed']);
        $this->group($round, 0, array_slice($p, 0, 4));
        $this->match($round, 1, 1, [$p[4], $p[5], $p[0], $p[1]], null, null, true);

        $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()
            ->assertSee('Gruppe 2 · Zusatzgruppe · Welle 2')
            ->assertSee('Spielen diese Runde 2×')
            ->assertSee('Spieler A, Spieler B');
    }

    #[Test]
    public function proposed_round_shows_groups_but_no_pairings(): void
    {
        $event = MixedEvent::factory()->create();
        $p = $this->participants($event, 4);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'proposed']);
        $this->group($round, 0, $p);

        $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()
            ->assertSee('Der Vorschlag ist noch nicht bestätigt')
            ->assertSee('Gruppe 1 · 4 Spieler')
            ->assertSee('Spieler A (D)')
            ->assertSee('Spieler B (H)')
            ->assertDontSee('Spieler A &amp; Spieler B', false);
    }

    #[Test]
    public function mexicano_round_shows_sitters_and_courts(): void
    {
        config(['tournament-base.courts' => [1 => 'Alpha', 2 => 'Beta']]);
        $event = MixedEvent::factory()->create(['mode' => 'mexicano', 'court_count' => 2]);
        $p = $this->participants($event, 9);
        $done = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 1, 'status' => 'completed']);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 2, 'status' => 'confirmed']);
        $this->match($round, 0, 1, [$p[0], $p[3], $p[1], $p[2]]);
        $this->match($round, 1, 1, [$p[4], $p[7], $p[5], $p[6]], 3, 1);
        MixedRoundPlayer::query()->create(['round_id' => $round->id, 'participant_id' => $p[8], 'group_no' => null]);

        $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()
            ->assertSee('Gruppe 1 · Platz 1 – Alpha')
            ->assertSee('Gruppe 2 · Platz 2 – Beta')
            ->assertDontSee('Welle')
            ->assertSee('Setzen diese Runde aus:')
            ->assertSee('Spieler I')
            ->assertSee('Runde 2 (offen)')
            ->assertSee(route('mixed.round', ['round' => $done->id]))
            ->assertSee('3:1');
    }

    #[Test]
    public function score_link_only_for_score_users(): void
    {
        $event = MixedEvent::factory()->create();
        $p = $this->participants($event, 4);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'confirmed']);
        $this->group($round, 0, $p);
        $reader = $this->member();
        $scorer = $this->member();
        MixedEventGrant::factory()->create(['event_id' => $event->id, 'uid' => $scorer->uid, 'role' => 'score']);
        $adminUrl = route('mixed.admin.round', ['round' => $round->id]);

        $this->actingAs($reader)->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()->assertDontSee('Ergebnisse eintragen')->assertDontSee($adminUrl);
        $this->actingAs($scorer)->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()->assertSee('Ergebnisse eintragen')->assertSee($adminUrl);
        $this->actingAs(User::factory()->status('admin')->create())->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()->assertSee('Zur Verwaltung dieser Runde');
    }

    #[Test]
    public function scorecard_link_only_for_confirmed_rounds(): void
    {
        $event = MixedEvent::factory()->create();
        $p = $this->participants($event, 4);
        $user = $this->member();

        foreach (['proposed' => false, 'confirmed' => true, 'completed' => false] as $status => $visible) {
            $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => MixedRound::query()->count() + 1, 'status' => $status]);
            $this->group($round, 0, $p);
            $response = $this->actingAs($user)->get('/mixed-tournament/round/'.$round->id)->assertOk();
            $url = route('mixed.scorecard', ['round' => $round->id]);
            $visible ? $response->assertSee($url)->assertSee('Scorecard drucken') : $response->assertDontSee($url);
        }
    }

    #[Test]
    public function empty_round_says_no_proposal(): void
    {
        $event = MixedEvent::factory()->create();
        $round = MixedRound::factory()->create(['event_id' => $event->id]);

        $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()
            ->assertSee('Für diese Runde wurde noch kein Vorschlag erzeugt.');
    }

    #[Test]
    public function standings_show_eligibility_and_order(): void
    {
        $event = MixedEvent::factory()->create();
        $p = $this->participants($event, 5);
        foreach ([1, 2] as $no) {
            $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => $no, 'status' => 'completed']);
            $this->match($round, 0, 1, [$p[0], $p[1], $p[2], $p[3]], 2, 2);
        }

        $html = $this->actingAs($this->member())->get('/mixed-tournament/event/'.$event->id.'/standings')
            ->assertOk()
            ->assertSee('Gewertet wird, wer höchstens eine Runde weniger gespielt hat als die meisten.')
            ->assertSee('Führende')
            ->assertSee('Spieler A · ⌀ 1,00')
            ->assertSeeInOrder(['Spieler A', 'Spieler B', 'Spieler C', 'Spieler D', 'Spieler E'])
            ->assertSee('nicht gewertet · noch 1 Runde')
            ->getContent();

        // Ties ordered by participant id; the not ranked row has no place number.
        $this->assertMatchesRegularExpression('#>\s*4\s*</td>\s*<td[^>]*>\s*<span class="flex flex-col">\s*<span[^>]*>Spieler D<#', $html);
        $this->assertMatchesRegularExpression('#>\s*–\s*</td>\s*<td[^>]*>\s*<span class="flex flex-col">\s*<span[^>]*>Spieler E<#', $html);
    }

    #[Test]
    public function durchmischung_matrix_counts_partners(): void
    {
        $event = MixedEvent::factory()->create();
        $p = $this->participants($event, 4);
        foreach ([1, 2] as $no) {
            $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => $no, 'status' => 'confirmed']);
            $this->match($round, 0, 1, [$p[0], $p[1], $p[2], $p[3]]);
        }
        // A proposal does not count.
        $proposed = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 3, 'status' => 'proposed']);
        $this->match($proposed, 0, 1, [$p[0], $p[2], $p[1], $p[3]]);

        $this->actingAs($this->member())->get('/mixed-tournament/event/'.$event->id.'/durchmischung')
            ->assertOk()
            ->assertSee('33 %')
            ->assertSee('von 6 möglichen')
            ->assertSee('Viele Wiederholungen')
            ->assertSee('title="Spieler A und Spieler B: 2"', false)
            ->assertSee('title="Spieler A und Spieler C: 0"', false)
            ->assertSee('Zahl = gemeinsame Partnerschaften. Dunkler = öfter.')
            ->assertSee('ring-court', false);
    }

    #[Test]
    public function mexicano_durchmischung_has_no_rating(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'mexicano']);
        $this->participants($event, 4);

        $this->actingAs($this->member())->get('/mixed-tournament/event/'.$event->id.'/durchmischung')
            ->assertOk()
            ->assertSee('bei Mexicano ohne Bewertung')
            ->assertDontSee('Noch keine Daten');
    }

    #[Test]
    public function numbers_use_german_decimals(): void
    {
        $event = MixedEvent::factory()->create();
        $p = $this->participants($event, 4);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'completed']);
        $this->match($round, 0, 1, [$p[0], $p[1], $p[2], $p[3]], 3, 1);
        $this->match($round, 0, 2, [$p[0], $p[2], $p[1], $p[3]], 2, 2);
        $user = $this->member();

        // Spieler A: win + draw = 3 points in 2 matches, 5 games.
        $this->actingAs($user)->get('/mixed-tournament/event/'.$event->id.'/standings')
            ->assertOk()->assertSee('1,50')->assertSee('2,50')->assertDontSee('1.50');
        $this->actingAs($user)->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()->assertSee('⌀ 1,50 Pkt');
        $this->actingAs($user)->withUnencryptedCookie('locale', 'en')->get('/mixed-tournament/event/'.$event->id.'/standings')
            ->assertOk()->assertSee('1.50')->assertDontSee('1,50');
    }

    #[Test]
    public function admin_tabs_only_for_event_admins_on_every_player_page(): void
    {
        $event = MixedEvent::factory()->create();
        $round = MixedRound::factory()->create(['event_id' => $event->id]);
        $urls = ['/round/'.$round->id, '/event/'.$event->id.'/standings', '/event/'.$event->id.'/durchmischung'];
        $admin = User::factory()->status('admin')->create();
        $reader = $this->member();

        foreach ($urls as $url) {
            $this->actingAs($admin)->get('/mixed-tournament'.$url)->assertOk()->assertSee('Konfiguration')->assertSee('+ Neue Runde');
            $this->actingAs($reader)->get('/mixed-tournament'.$url)->assertOk()->assertDontSee('Konfiguration')->assertDontSee('+ Neue Runde');
        }
    }

    #[Test]
    public function unknown_round_redirects_to_the_index(): void
    {
        $this->actingAs($this->member())->get('/mixed-tournament/round/999')
            ->assertRedirect(route('mixed.index'))
            ->assertSessionHas('error');
        $this->actingAs($this->member())->get('/mixed-tournament/event/999/standings')
            ->assertRedirect(route('mixed.index'))
            ->assertSessionHas('error');
    }

    #[Test]
    public function player_pages_never_show_itn_age_or_contact_data(): void
    {
        $event = MixedEvent::factory()->create();
        $linked = User::factory()->status('enabled')->create(['email' => 'kontakt-geheim@example.test']);
        $p = [];
        foreach (range(0, 3) as $i) {
            $p[] = (int) MixedParticipant::factory()->create([
                'event_id' => $event->id, 'label' => 'Spieler '.chr(65 + $i), 'gender' => 'f',
                'itn' => '7.35', 'age' => 117, 'uid' => $i === 0 ? $linked->uid : null,
            ])->id;
        }
        $confirmed = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 1, 'status' => 'confirmed']);
        $this->group($confirmed, 0, $p, true);
        $proposed = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 2, 'status' => 'proposed']);
        $this->group($proposed, 0, $p);
        $user = $this->member();

        foreach (['/event/'.$event->id, '/round/'.$confirmed->id, '/round/'.$proposed->id, '/event/'.$event->id.'/standings', '/event/'.$event->id.'/durchmischung'] as $url) {
            $response = $this->actingAs($user)->get('/mixed-tournament'.$url)->assertOk()->assertSee('Spieler A');
            foreach (['ITN', '7,4', '7,35', '7.35', '117', 'kontakt-geheim', 'Jahre'] as $secret) {
                $response->assertDontSee($secret);
            }
        }
    }

    #[Test]
    public function participants_of_another_event_never_show_their_data(): void
    {
        $event = MixedEvent::factory()->create();
        $other = MixedEvent::factory()->create();
        $p = $this->participants($event, 3);
        $foreign = (int) MixedParticipant::factory()->create(['event_id' => $other->id, 'label' => 'Fremde Person'])->id;
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'confirmed']);
        $this->match($round, 0, 1, [$p[0], $p[1], $p[2], $foreign], 3, 1);
        $otherRound = MixedRound::factory()->create(['event_id' => $other->id, 'round_no' => 7]);
        $user = $this->member();

        $this->actingAs($user)->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()
            ->assertSee('Spieler C &amp; ?', false)
            ->assertDontSee('Fremde Person')
            ->assertDontSee(route('mixed.round', ['round' => $otherRound->id]));
        foreach (['/standings', '/durchmischung'] as $page) {
            $this->actingAs($user)->get('/mixed-tournament/event/'.$event->id.$page)
                ->assertOk()->assertDontSee('Fremde Person');
        }
    }

    #[Test]
    public function round_standings_and_matrix_escape_labels(): void
    {
        $event = MixedEvent::factory()->create();
        $p = [];
        foreach (range(0, 3) as $i) {
            $p[] = (int) MixedParticipant::factory()->create(['event_id' => $event->id, 'label' => '<b>x'.$i.'</b>'])->id;
        }
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'confirmed']);
        $this->match($round, 0, 1, $p, 3, 1);
        $user = $this->member();

        foreach (['/round/'.$round->id, '/event/'.$event->id.'/standings', '/event/'.$event->id.'/durchmischung'] as $url) {
            $this->actingAs($user)->get('/mixed-tournament'.$url)
                ->assertOk()
                ->assertSee('&lt;b&gt;x0&lt;/b&gt;', false)
                ->assertDontSee('<b>x0</b>', false);
        }
    }

    #[Test]
    public function mexicano_round_shows_the_wave_hint_with_more_groups_than_courts(): void
    {
        config(['tournament-base.courts' => [1 => 'Alpha', 2 => 'Beta']]);
        $event = MixedEvent::factory()->create(['mode' => 'mexicano', 'court_count' => 2]);
        $p = $this->participants($event, 12);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'confirmed']);
        foreach ([0, 1, 2] as $g) {
            $this->match($round, $g, 1, array_slice($p, $g * 4, 4));
        }

        $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()
            ->assertSee('2 Plätze, 3 Gruppen, also 2 Wellen. Die Runde ist erst fertig, wenn alle Wellen gespielt sind.')
            ->assertSee('Gruppe 3 · Platz 1 – Alpha · Welle 2')
            ->assertSee('Gruppe 2 · Platz 2 – Beta · Welle 1');
    }

    #[Test]
    public function round_tab_is_marked_current_and_player_pages_have_no_scroll_restore(): void
    {
        $event = MixedEvent::factory()->create();
        $p = $this->participants($event, 4);
        MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 1, 'status' => 'completed']);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 2, 'status' => 'confirmed']);
        $this->group($round, 0, $p);

        $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id)
            ->assertOk()
            ->assertSee('aria-current="page">Runde 2</a>', false)
            ->assertDontSee('data-scroll-restore', false)
            ->assertDontSee('mixed-scroll:', false)
            ->assertDontSee('data-scroll-save', false);
    }

    #[Test]
    public function scroll_restore_is_one_shot_and_saves_only_on_marked_forms(): void
    {
        $html = view('mixed::partials.scroll-restore', ['scrollId' => 7])->render();

        $this->assertStringContainsString('data-scroll-restore="mixed-scroll:mixed:7"', $html);
        $this->assertStringContainsString('sessionStorage.removeItem(this.key)', $html);
        $this->assertStringContainsString("closest('[data-scroll-save]')", $html);
        $this->assertStringContainsString("addEventListener('submit'", $html);
        $this->assertStringNotContainsString('pagehide', $html);
        $this->assertStringNotContainsString('beforeunload', $html);

        $event = MixedEvent::factory()->create();
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'status' => 'confirmed']);
        $match = $this->match($round, 0, 1, $this->participants($event, 4), 3, 1);
        $card = view('mixed::partials.match-card', ['match' => $match, 'labels' => [], 'context' => 'Gruppe 1', 'withResults' => true])->render();

        $this->assertSame(5, substr_count($card, 'data-scroll-save'));
        $this->assertStringContainsString('aria-pressed="true"', $card);
    }
}
