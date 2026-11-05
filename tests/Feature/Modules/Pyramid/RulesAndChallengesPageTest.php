<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Support\PyramidStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RulesAndChallengesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ladder(int $players = 10, array $attributes = []): PyramidLadder
    {
        return PyramidLadder::factory()->asDefault()->withRanking($players)->create($attributes);
    }

    private function playerAt(PyramidLadder $ladder, int $position): PyramidPlayer
    {
        return PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', $position)->firstOrFail();
    }

    private function challenge(PyramidLadder $ladder, int $challengerPos, int $opponentPos, array $attributes = []): PyramidChallenge
    {
        return PyramidChallenge::factory()->create($attributes + [
            'ladder_id' => $ladder->id,
            'challenger_id' => $this->playerAt($ladder, $challengerPos)->id,
            'opponent_id' => $this->playerAt($ladder, $opponentPos)->id,
            'challenger_pos' => $challengerPos,
            'opponent_pos' => $opponentPos,
        ]);
    }

    #[Test]
    public function rules_show_the_ladder_days(): void
    {
        $this->ladder(5, ['days_to_play' => 9, 'loser_cooldown_days' => 11, 'winner_protected_days' => 4]);

        $this->get('/pyramide/regeln')
            ->assertOk()
            ->assertSee('Regeln der Forderungspyramide')
            ->assertSee('nicht mehr als 9 Tage')
            ->assertSee('erst nach 11 Tagen wieder fordern')
            ->assertSee('4 Tage lang nicht gefordert werden')
            ->assertSee('Forderungsrecht')
            ->assertSee('Fair Play')
            ->assertSee('Zwei Gewinnsätze');
    }

    #[Test]
    public function rules_without_a_ladder_are_a_404(): void
    {
        $this->get('/pyramide/regeln')->assertNotFound();
    }

    #[Test]
    public function filter_by_name_and_status(): void
    {
        $ladder = $this->ladder();
        $this->challenge($ladder, 5, 2, ['status' => 'played', 'score' => '6:4 6:3']);
        $this->challenge($ladder, 5, 3, ['status' => 'open']);
        $this->challenge($ladder, 8, 7, ['status' => 'played', 'score' => '7:5 6:1']);

        $response = $this->get('/pyramide/forderungen?q=spieler 5&status=played')->assertOk();

        $response->assertSee('Spieler 5 fordert Spieler 2')
            ->assertSee('6:4 6:3')
            ->assertDontSee('Spieler 5 fordert Spieler 3')
            ->assertDontSee('Spieler 8 fordert Spieler 7')
            ->assertSee('value="spieler 5"', false);
    }

    #[Test]
    public function challenge_list_shows_dates_results_and_status_badges(): void
    {
        $ladder = $this->ladder();
        $this->challenge($ladder, 9, 6, ['status' => 'scheduled', 'scheduled_at' => '2027-03-07 10:00:00']);
        $this->challenge($ladder, 4, 3, ['status' => 'open', 'deadline' => '2027-03-18 23:59:59']);

        $this->get('/pyramide/forderungen')
            ->assertOk()
            ->assertSee('Platz 9 gegen 6')
            ->assertSee('07.03.2027 10:00')
            ->assertSee('Frist 18.03.2027')
            ->assertSee('Termin vereinbart')
            ->assertSee('Offen')
            ->assertSee('pyramide/forderung/', false)
            ->assertSee('Details');
    }

    #[Test]
    public function empty_list_says_so(): void
    {
        $this->ladder();

        $this->get('/pyramide/forderungen?status=expired')->assertOk()->assertSee('Keine Forderungen gefunden.');
    }

    #[Test]
    public function challenger_from_waitlist_shows_warteliste(): void
    {
        $ladder = $this->ladder();
        $waiting = PyramidPlayer::factory()->waiting()->create(['ladder_id' => $ladder->id, 'label' => 'Wartender Spieler']);
        $this->challenge($ladder, 5, 4, [
            'challenger_id' => $waiting->id,
            'challenger_pos' => null,
            'opponent_id' => $this->playerAt($ladder, 4)->id,
            'opponent_pos' => 4,
        ]);

        $this->get('/pyramide/forderungen')
            ->assertOk()
            ->assertSee('Wartender Spieler fordert Spieler 4')
            ->assertSee('Warteliste gegen 4');
    }

    #[Test]
    public function pagination_keeps_filters(): void
    {
        $ladder = $this->ladder();
        PyramidChallenge::factory()->count(101)->create([
            'ladder_id' => $ladder->id,
            'challenger_id' => $this->playerAt($ladder, 5)->id,
            'opponent_id' => $this->playerAt($ladder, 4)->id,
            'challenger_pos' => 5,
            'opponent_pos' => 4,
            'status' => 'open',
        ]);

        $page1 = $this->get('/pyramide/forderungen?status=open')->assertOk();
        $this->assertSame(100, substr_count($page1->getContent(), 'pyramide/forderung/'));
        $page1->assertSee('status=open', false)->assertSee('page=2', false);

        $page2 = $this->get('/pyramide/forderungen?status=open&page=2')->assertOk();
        $this->assertSame(1, substr_count($page2->getContent(), 'pyramide/forderung/'));
    }

    #[Test]
    public function search_text_is_echoed_without_4_byte_chars(): void
    {
        $this->ladder();

        $response = $this->get('/pyramide/forderungen?q=Ball%20%F0%9F%8E%BE')->assertOk();

        $this->assertStringNotContainsString("\u{1F3BE}", $response->getContent());
        // The controller trims, as ZF2 did, so the stripped value is "Ball".
        $response->assertSee('value="Ball"', false);
    }

    #[Test]
    public function status_helper_maps_labels_and_badge_groups(): void
    {
        $this->assertSame('Termin vereinbart', PyramidStatus::label('scheduled'));
        $this->assertSame('Nicht angetreten', PyramidStatus::label('forfeited'));
        $this->assertSame('mystery', PyramidStatus::label('mystery'));

        $groups = [
            'open' => 'attention', 'scheduled' => 'neutral', 'played' => 'good', 'declined' => 'problem',
            'withdrawn' => 'neutral', 'forfeited' => 'problem', 'expired' => 'neutral',
            'active' => 'good', 'paused' => 'neutral', 'waiting' => 'attention', 'entering' => 'attention', 'removed' => 'problem',
            'mystery' => 'neutral',
        ];

        foreach ($groups as $status => $group) {
            $this->assertSame($group, PyramidStatus::badgeGroup($status), $status);
        }
    }

    #[Test]
    public function list_is_ordered_newest_first(): void
    {
        $ladder = $this->ladder();
        $this->challenge($ladder, 9, 6, ['status' => 'played', 'score' => '6:1 6:1', 'created' => '2027-01-01 10:00:00']);
        $this->challenge($ladder, 8, 5, ['status' => 'played', 'score' => '6:2 6:2', 'created' => '2027-01-03 10:00:00']);
        $this->challenge($ladder, 7, 4, ['status' => 'played', 'score' => '6:3 6:3', 'created' => '2027-01-02 10:00:00']);

        $this->get('/pyramide/forderungen')
            ->assertOk()
            ->assertSeeInOrder(['6:2 6:2', '6:3 6:3', '6:1 6:1']);
    }

    #[Test]
    public function the_oldest_challenge_lands_on_page_two(): void
    {
        $ladder = $this->ladder();
        $this->challenge($ladder, 9, 6, ['status' => 'played', 'score' => '7:6 7:6', 'created' => '2020-01-01 10:00:00']);
        PyramidChallenge::factory()->count(100)->create([
            'ladder_id' => $ladder->id,
            'challenger_id' => $this->playerAt($ladder, 5)->id,
            'opponent_id' => $this->playerAt($ladder, 4)->id,
            'challenger_pos' => 5,
            'opponent_pos' => 4,
            'status' => 'open',
            'created' => '2027-01-01 10:00:00',
        ]);

        $this->get('/pyramide/forderungen')->assertOk()->assertDontSee('7:6 7:6');
        $this->get('/pyramide/forderungen?page=2')->assertOk()->assertSee('7:6 7:6');
    }

    #[Test]
    public function the_search_ignores_case_and_handles_umlauts(): void
    {
        $ladder = $this->ladder();
        $this->playerAt($ladder, 5)->update(['label' => 'Jürgen Müller']);
        $this->challenge($ladder, 5, 2, ['status' => 'played', 'score' => '6:4 6:3']);
        $this->challenge($ladder, 8, 7, ['status' => 'played', 'score' => '7:5 6:1']);

        foreach (['müller', 'MÜLLER', 'jÜrg'] as $term) {
            $this->get('/pyramide/forderungen?q='.rawurlencode($term))
                ->assertOk()
                ->assertSee('6:4 6:3')
                ->assertDontSee('7:5 6:1');
        }
    }

    #[Test]
    public function a_player_without_a_label_in_the_lists_is_shown_as_unknown(): void
    {
        $ladder = $this->ladder();
        $removed = PyramidPlayer::factory()->create(['ladder_id' => $ladder->id, 'status' => 'removed', 'position' => null, 'label' => 'Entfernter Spieler']);
        $this->challenge($ladder, 5, 4, ['challenger_id' => $removed->id, 'challenger_pos' => null, 'status' => 'expired']);

        $this->get('/pyramide/forderungen')
            ->assertOk()
            ->assertSee('Unbekannt')
            ->assertDontSee('Entfernter Spieler');
    }

    #[Test]
    public function the_status_badge_markup_is_rendered_per_row(): void
    {
        $ladder = $this->ladder();
        $this->challenge($ladder, 9, 6, ['status' => 'scheduled', 'scheduled_at' => '2027-03-07 10:00:00']);
        $this->challenge($ladder, 4, 3, ['status' => 'open']);

        $this->get('/pyramide/forderungen')
            ->assertOk()
            ->assertSee('ds-badge--neutral">Termin vereinbart</span>', false)
            ->assertSee('ds-badge--attention">Offen</span>', false);
    }
}
