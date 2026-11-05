<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Models\User;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PrintablesTest extends TestCase
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

    /** @param  array{int, int, int, int}  $p */
    private function match(MixedRound $round, int $groupNo, int $matchNo, array $p, bool $extra = false, ?int $gamesA = null, ?int $gamesB = null): void
    {
        MixedMatch::factory()->create([
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

    /**
     * One rotation (3 matches) of a group of four.
     *
     * @param  list<int>  $p
     */
    private function group(MixedRound $round, int $groupNo, array $p, bool $extra = false, bool $scored = false): void
    {
        $this->match($round, $groupNo, 1, [$p[0], $p[1], $p[2], $p[3]], $extra, $scored ? 3 : null, $scored ? 1 : null);
        $this->match($round, $groupNo, 2, [$p[0], $p[2], $p[1], $p[3]], $extra, $scored ? 4 : null, $scored ? 0 : null);
        $this->match($round, $groupNo, 3, [$p[0], $p[3], $p[1], $p[2]], $extra, $scored ? 1 : null, $scored ? 3 : null);
    }

    /** Americano: 3 groups of four plus an extra group on 2 courts = waves 1, 1, 2, 3. */
    private function americanoRound(bool $scored = false): MixedRound
    {
        $event = MixedEvent::factory()->create(['court_count' => 2, 'title' => 'Sommer-Mixed']);
        $p = $this->participants($event, 13);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 2, 'status' => 'confirmed']);
        $this->group($round, 0, array_slice($p, 0, 4), false, $scored);
        $this->group($round, 1, array_slice($p, 4, 4), false, $scored);
        $this->group($round, 2, array_slice($p, 8, 4), false, $scored);
        $this->group($round, 3, [$p[12], $p[0], $p[4], $p[8]], true, $scored);

        return $round;
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<string> the class attribute of every card, in document order
     */
    private function cardClasses(TestResponse $response): array
    {
        preg_match_all('/<section class="(sc-card(?: [^"]*)?)"/', (string) $response->getContent(), $m);

        return $m[1];
    }

    #[Test]
    public function scorecard_only_for_confirmed_rounds(): void
    {
        $event = MixedEvent::factory()->create();
        $p = $this->participants($event, 4);

        foreach (['proposed', 'completed'] as $i => $status) {
            $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => $i + 1, 'status' => $status]);
            $this->group($round, 0, $p);

            $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id.'/scorecard')
                ->assertRedirect(route('mixed.round', ['round' => $round->id]))
                ->assertSessionHas('error', 'Die Scorecard gibt es nur für eine bestätigte, noch nicht abgeschlossene Runde.');
        }
    }

    #[Test]
    public function americano_scorecard_orders_by_wave_and_breaks_pages(): void
    {
        $round = $this->americanoRound();

        $response = $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id.'/scorecard')->assertOk();

        $classes = $this->cardClasses($response);
        $this->assertCount(4, $classes);
        $this->assertSame([false, true, true, false], array_map(fn (string $c): bool => str_contains($c, 'sc-wave-end'), $classes));
        $this->assertSame(2, substr_count((string) $response->getContent(), 'sc-wave-end'));

        $response->assertSeeInOrder(['Gruppe 1', 'Gruppe 2', 'Gruppe 3', 'Gruppe 4 (Zusatzgruppe)'])
            ->assertSee('Sommer-Mixed · Runde 2 · ', false)
            ->assertSee('Teilnehmer')
            ->assertSee('Paarungen')
            ->assertSee('1. Spieler A (D)')
            ->assertSee('2. Spieler B (H)')
            ->assertSee('Spieler A &amp; Spieler B', false)
            ->assertSee('Spieler C &amp; Spieler D', false);
    }

    #[Test]
    public function scorecard_lists_sitters_and_handles_empty_rounds(): void
    {
        $round = $this->americanoRound();
        $sitter = MixedParticipant::factory()->create(['event_id' => $round->event_id, 'label' => 'Spieler Pause', 'gender' => 'm']);
        MixedRoundPlayer::query()->create(['round_id' => $round->id, 'participant_id' => $sitter->id, 'group_no' => null]);

        $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id.'/scorecard')
            ->assertOk()->assertSee('Setzen diese Runde aus:')->assertSee('Spieler Pause');

        $empty = MixedRound::factory()->create(['event_id' => $round->event_id, 'round_no' => 3, 'status' => 'confirmed']);
        $this->actingAs($this->member())->get('/mixed-tournament/round/'.$empty->id.'/scorecard')
            ->assertOk()->assertSee('Für diese Runde gibt es keine Matches.')->assertDontSee('<section class="sc-card', false);
    }

    #[Test]
    public function scorecard_never_prints_waves_or_scores(): void
    {
        $round = $this->americanoRound(scored: true);

        $content = (string) $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id.'/scorecard')->assertOk()->getContent();
        $body = (string) preg_replace('/\s+/', ' ', strip_tags($content));

        $this->assertStringNotContainsString('Welle', $content);
        foreach (['3:1', '4:0', '1:3', '3 : 1', '4 : 0', '1 : 3'] as $score) {
            $this->assertStringNotContainsString($score, $body);
        }
    }

    #[Test]
    public function mexicano_scorecard_has_court_labels(): void
    {
        config(['tournament-base.courts' => [1 => 'Alpha', 2 => 'Beta']]);
        $event = MixedEvent::factory()->create(['mode' => 'mexicano', 'court_count' => 2, 'title' => 'Mexicano-Abend']);
        $p = $this->participants($event, 13);
        $round = MixedRound::factory()->create(['event_id' => $event->id, 'round_no' => 4, 'status' => 'confirmed']);
        $this->match($round, 0, 1, [$p[0], $p[1], $p[2], $p[3]]);
        $this->match($round, 1, 1, [$p[4], $p[5], $p[6], $p[7]]);
        $this->match($round, 2, 1, [$p[8], $p[9], $p[10], $p[11]]);
        MixedRoundPlayer::query()->create(['round_id' => $round->id, 'participant_id' => $p[12], 'group_no' => null]);

        $response = $this->actingAs($this->member())->get('/mixed-tournament/round/'.$round->id.'/scorecard')->assertOk();

        $response->assertSeeInOrder(['Gruppe 1 · Platz 1 – Alpha', 'Gruppe 2 · Platz 2 – Beta', 'Gruppe 3 · Platz 1 – Alpha'])
            ->assertSee('Runde 4 · ', false)
            ->assertSee('Spieler A &amp; Spieler B', false)
            ->assertSee('Setzen diese Runde aus:')
            ->assertSee('Spieler M')
            ->assertDontSee('Welle');
        $this->assertSame([false, true, false], array_map(fn (string $c): bool => str_contains($c, 'sc-wave-end'), $this->cardClasses($response)));
    }

    #[Test]
    public function blank_scorecard_clamps_count_and_size(): void
    {
        $event = MixedEvent::factory()->create();
        $url = '/mixed-tournament/event/'.$event->id.'/scorecard-blank';
        $user = $this->member();

        $this->assertCount(40, $this->cardClasses($this->actingAs($user)->get($url.'?count=99')->assertOk()));
        $this->assertCount(2, $this->cardClasses($this->actingAs($user)->get($url.'?count=1')->assertOk()));
        $this->assertCount(8, $this->cardClasses($this->actingAs($user)->get($url)->assertOk()));

        $four = (string) $this->actingAs($user)->get($url.'?size=7&count=2')->assertOk()->getContent();
        $this->assertSame(2 * 4, substr_count($four, 'class="sc-name-line"'));
        $this->assertSame(2 * 3, substr_count($four, 'class="sc-pair"'));
        $this->assertStringNotContainsString('Pause', strip_tags($four));
        $this->assertStringContainsString('4er-Gruppe', $four);

        $five = (string) $this->actingAs($user)->get($url.'?size=5&count=2')->assertOk()->getContent();
        $this->assertSame(2 * 5, substr_count($five, 'class="sc-name-line"'));
        $this->assertSame(2 * 5, substr_count($five, 'class="sc-pair"'));
        $this->assertSame(2 * 5, substr_count(strip_tags($five), 'Pause '));
        $this->assertStringContainsString('5er-Gruppe', $five);
    }

    #[Test]
    public function blank_scorecard_has_no_names_or_dates(): void
    {
        $event = MixedEvent::factory()->create(['title' => 'Herbst-Mixed', 'event_date' => '2027-04-10']);
        $this->participants($event, 8);

        $response = $this->actingAs($this->member())->get('/mixed-tournament/event/'.$event->id.'/scorecard-blank?count=2')->assertOk();
        $content = (string) $response->getContent();

        $this->assertStringNotContainsString('Spieler A', $content);
        $this->assertStringNotContainsString('2027', $content);
        $this->assertStringNotContainsString('April', $content);
        $this->assertStringNotContainsString('Welle', $content);
        $response->assertSee('Herbst-Mixed — Blanko-Scorecards')->assertSee('Gruppe')->assertSee('Runde')
            ->assertSee('Die Zahlen sind Positionen in der Gruppe:')
            ->assertSeeInOrder(['1+2', '3+4']);
    }

    #[Test]
    public function mexicano_blank_scorecard_always_has_four_players(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'mexicano']);

        $content = (string) $this->actingAs($this->member())->get('/mixed-tournament/event/'.$event->id.'/scorecard-blank?size=5&count=2')->assertOk()->getContent();

        $this->assertSame(2 * 4, substr_count($content, 'class="sc-name-line"'));
        $this->assertStringNotContainsString('Pause', strip_tags($content));
        $this->assertStringContainsString('Mexicano', $content);
    }

    #[Test]
    public function printables_are_standalone(): void
    {
        $round = $this->americanoRound();
        $user = $this->member();

        foreach ([
            '/mixed-tournament/round/'.$round->id.'/scorecard',
            '/mixed-tournament/event/'.$round->event_id.'/scorecard-blank',
        ] as $url) {
            $content = (string) $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('<html lang="de"', $content);
            $this->assertStringContainsString('mixed-print', $content);
            $this->assertStringNotContainsString('page-shell', $content);
            $this->assertStringNotContainsString('<header', $content);
            $this->assertStringNotContainsString('ds-admin', $content);
            $this->assertMatchesRegularExpression('/class="[^"]*\bno-print\b[^"]*"/', $content);
            $this->assertStringContainsString('window.print()', $content);
            $this->assertStringContainsString('Drucken', $content);
            $this->assertStringContainsString('Zurück', $content);
        }
    }

    #[Test]
    public function printables_need_a_login_and_unknown_ids_redirect(): void
    {
        $round = $this->americanoRound();

        $this->get('/mixed-tournament/round/'.$round->id.'/scorecard')->assertRedirect();
        $this->get('/mixed-tournament/event/'.$round->event_id.'/scorecard-blank')->assertRedirect();

        $this->actingAs($this->member())->get('/mixed-tournament/round/999999/scorecard')->assertRedirect(route('mixed.index'));
        $this->actingAs($this->member())->get('/mixed-tournament/event/999999/scorecard-blank')->assertRedirect(route('mixed.index'));
    }

    #[Test]
    public function print_stylesheet_is_built(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('resources/css/mixed-print.css', $manifest);
        $this->assertFileExists(public_path('build/'.$manifest['resources/css/mixed-print.css']['file']));
    }
}
