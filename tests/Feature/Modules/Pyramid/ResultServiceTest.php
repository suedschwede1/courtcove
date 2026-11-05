<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\ResultService;
use App\Modules\Pyramid\Support\PyramidRuleException;
use App\Modules\TournamentBase\Services\InvalidScoreException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPyramidLadders;
use Tests\TestCase;

class ResultServiceTest extends TestCase
{
    use BuildsPyramidLadders;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
    }

    private function service(): ResultService
    {
        return $this->app->make(ResultService::class);
    }

    private function open(PyramidLadder $ladder, PyramidPlayer $challenger, PyramidPlayer $opponent): PyramidChallenge
    {
        return $this->app->make(ChallengeService::class)->create($ladder, $challenger, $opponent, null);
    }

    /**
     * @return array<string, int>
     */
    private function win(): array
    {
        return ['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3];
    }

    // Ergebnis verbuchen

    #[Test]
    public function challenger_win_shifts_the_ladder(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3));

        $evaluated = $this->service()->record($ladder, $challenge, $this->win(), 42);

        $this->assertSame('6:4 6:3', $evaluated['score']);
        $this->assertSame(
            ['Spieler 1' => 1, 'Spieler 2' => 2, 'Spieler 5' => 3, 'Spieler 3' => 4, 'Spieler 4' => 5, 'Spieler 6' => 6],
            $this->positionsByLabel((int) $ladder->id)
        );

        $row = PyramidChallenge::query()->findOrFail($challenge->id);
        $this->assertSame('played', $row->status);
        $this->assertSame('6:4 6:3', $row->score);
        $this->assertSame((int) $challenge->challenger_id, (int) $row->winner_id);
        $this->assertSame(42, (int) $row->entered_uid);
        $this->assertNotNull($row->entered);
        $this->assertNotNull($row->resolved);

        $event = PyramidEvent::query()->where('challenge_id', $challenge->id)->where('type', 'result')->firstOrFail();
        $this->assertSame(
            json_encode(['winner' => (int) $challenge->challenger_id, 'loser' => (int) $challenge->opponent_id, 'score' => '6:4 6:3']),
            $event->payload
        );
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function challenger_loss_leaves_the_ladder_alone(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3));
        $before = $this->positionsByLabel((int) $ladder->id);

        $this->service()->record($ladder, $challenge, ['s1a' => 4, 's1b' => 6, 's2a' => 3, 's2b' => 6], null);

        $this->assertSame($before, $this->positionsByLabel((int) $ladder->id));
        $row = PyramidChallenge::query()->findOrFail($challenge->id);
        $this->assertSame((int) $challenge->opponent_id, (int) $row->winner_id);
        $this->assertNull($row->entered_uid);
    }

    // Regel 3.8

    #[Test]
    public function challenger_already_ahead_changes_nothing(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 4));

        // In the meantime 5 and 2 swapped places (NULL first because of the unique key).
        $five = $this->playerAt($ladder, 5);
        $two = $this->playerAt($ladder, 2);
        $five->update(['position' => null]);
        $two->update(['position' => 5]);
        $five->update(['position' => 2]);
        $before = $this->positionsByLabel((int) $ladder->id);

        $this->service()->record($ladder, $challenge, ['s1a' => 6, 's1b' => 0, 's2a' => 6, 's2b' => 0], null);

        $this->assertSame($before, $this->positionsByLabel((int) $ladder->id));
        $this->assertSame('played', PyramidChallenge::query()->findOrFail($challenge->id)->status);
    }

    // Regel 6.1

    #[Test]
    public function entry_win_inserts_at_opponent_position(): void
    {
        $ladder = $this->makeLadder(5);
        $newcomer = PyramidPlayer::factory()->create([
            'ladder_id' => $ladder->id,
            'label' => 'Neuling',
            'position' => null,
            'status' => 'entering',
            'uid' => null,
            'joined' => now()->format('Y-m-d H:i:s'),
        ]);
        $challenge = $this->open($ladder, $newcomer, $this->playerAt($ladder, 3));

        $this->service()->record($ladder, $challenge, $this->win(), null);

        $this->assertSame(
            ['Spieler 1' => 1, 'Spieler 2' => 2, 'Neuling' => 3, 'Spieler 3' => 4, 'Spieler 4' => 5, 'Spieler 5' => 6],
            $this->positionsByLabel((int) $ladder->id)
        );
        $this->assertSame('active', PyramidPlayer::query()->findOrFail($newcomer->id)->status);
        $this->assertRankingIntact((int) $ladder->id);
    }

    #[Test]
    public function entry_loss_appends_the_newcomer(): void
    {
        $ladder = $this->makeLadder(5);
        $newcomer = PyramidPlayer::factory()->create([
            'ladder_id' => $ladder->id,
            'label' => 'Neuling',
            'position' => null,
            'status' => 'entering',
            'uid' => null,
            'joined' => now()->format('Y-m-d H:i:s'),
        ]);
        $challenge = $this->open($ladder, $newcomer, $this->playerAt($ladder, 3));

        $this->service()->record($ladder, $challenge, ['s1a' => 4, 's1b' => 6, 's2a' => 3, 's2b' => 6], null);

        $this->assertSame(6, $this->positionsByLabel((int) $ladder->id)['Neuling']);
        $this->assertSame(3, $this->positionsByLabel((int) $ladder->id)['Spieler 3']);
        $this->assertSame('active', PyramidPlayer::query()->findOrFail($newcomer->id)->status);
        $this->assertRankingIntact((int) $ladder->id);
    }

    // Ungueltiger Score

    #[Test]
    public function invalid_score_changes_nothing(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3));
        $before = $this->positionsByLabel((int) $ladder->id);
        $events = PyramidEvent::query()->count();

        try {
            $this->service()->record($ladder, $challenge, ['s1a' => 6, 's1b' => 4], null);
            $this->fail('InvalidScoreException expected');
        } catch (InvalidScoreException $e) {
            $this->assertSame('tournament-base::score.sets_incomplete', $e->key);
        }

        $this->assertSame('open', PyramidChallenge::query()->findOrFail($challenge->id)->status);
        $this->assertSame($before, $this->positionsByLabel((int) $ladder->id));
        $this->assertSame($events, PyramidEvent::query()->count());
    }

    // nicht zweimal

    #[Test]
    public function a_result_cannot_be_recorded_twice(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3));
        $this->service()->record($ladder, $challenge, $this->win(), null);
        $after = $this->positionsByLabel((int) $ladder->id);

        try {
            $this->service()->record($ladder, $challenge, $this->win(), null);
            $this->fail('PyramidRuleException expected');
        } catch (PyramidRuleException $e) {
            $this->assertSame('pyramid::check.challenge_closed', $e->verdict->key);
        }

        $this->assertSame($after, $this->positionsByLabel((int) $ladder->id));
    }

    // veraltete Forderungszeile

    #[Test]
    public function stale_challenge_row_is_rechecked_after_locking(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3));
        $before = $this->positionsByLabel((int) $ladder->id);

        // The caller still holds the 'open' row, the database no longer does.
        PyramidChallenge::query()->whereKey($challenge->id)->update(['status' => 'withdrawn']);
        $this->assertSame('open', $challenge->status);

        try {
            $this->service()->record($ladder, $challenge, $this->win(), null);
            $this->fail('PyramidRuleException expected');
        } catch (PyramidRuleException $e) {
            $this->assertSame('pyramid::check.challenge_closed', $e->verdict->key);
        }

        $this->assertSame($before, $this->positionsByLabel((int) $ladder->id));
        $this->assertSame('withdrawn', PyramidChallenge::query()->findOrFail($challenge->id)->status);
    }

    #[Test]
    public function a_closed_ladder_cannot_be_played(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3));
        PyramidLadder::query()->whereKey($ladder->id)->update(['status' => 'closed']);

        $this->expectException(PyramidRuleException::class);
        $this->expectExceptionMessage('pyramid::check.ladder_closed');

        $this->service()->record($ladder, $challenge, $this->win(), null);
    }

    // Invariante

    #[Test]
    public function positions_are_cleared_before_they_are_set(): void
    {
        $ladder = $this->makeLadder(8);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 8), $this->playerAt($ladder, 4));

        DB::enableQueryLog();
        $this->service()->record($ladder, $challenge, $this->win(), null);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $updates = array_values(array_filter(
            $queries,
            fn (array $q): bool => str_contains($q['query'], 'update "bs_pyramid_players" set "position"')
        ));

        // Players on 4..8 change: five clears first, then five new values.
        $this->assertCount(10, $updates);

        foreach (array_slice($updates, 0, 5) as $update) {
            $this->assertNull($update['bindings'][0]);
        }

        foreach (array_slice($updates, 5) as $update) {
            $this->assertNotNull($update['bindings'][0]);
        }

        $this->assertSame(
            array_column(array_slice($updates, 0, 5), 'bindings'),
            array_map(fn (array $q): array => [null, $q['bindings'][1]], array_slice($updates, 5))
        );
        $this->assertRankingIntact((int) $ladder->id);
        $this->assertSame(4, $this->positionsByLabel((int) $ladder->id)['Spieler 8']);
    }

    // Kampflose Wertung und Verfall

    #[Test]
    public function forfeit_shifts_like_a_match_and_starts_the_cooldown(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3));

        $this->service()->recordForfeit($ladder, $challenge, true, 'Gegner nicht erschienen', 7);

        $this->assertSame(
            ['Spieler 1' => 1, 'Spieler 2' => 2, 'Spieler 5' => 3, 'Spieler 3' => 4, 'Spieler 4' => 5, 'Spieler 6' => 6],
            $this->positionsByLabel((int) $ladder->id)
        );

        $row = PyramidChallenge::query()->findOrFail($challenge->id);
        $this->assertSame('forfeited', $row->status);
        $this->assertNull($row->score);
        $this->assertSame((int) $challenge->challenger_id, (int) $row->winner_id);
        $this->assertSame('Gegner nicht erschienen', $row->admin_note);
        $this->assertNotNull($row->resolved);

        $event = PyramidEvent::query()->where('challenge_id', $challenge->id)->where('type', 'result')->firstOrFail();
        $this->assertSame(
            json_encode(['winner' => (int) $challenge->challenger_id, 'loser' => (int) $challenge->opponent_id, 'score' => 'nicht angetreten']),
            $event->payload
        );

        // The loser of a forfeit is blocked like the loser of a match (rule 4.3).
        $cooldown = $this->app->make(ChallengeService::class)->check(
            $ladder,
            PyramidPlayer::query()->findOrFail($challenge->opponent_id),
            $this->playerAt($ladder, 2)
        );
        $this->assertSame('pyramid::check.cooldown', $cooldown?->key);
    }

    #[Test]
    public function forfeit_win_of_the_opponent_leaves_the_ladder_alone(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, $this->playerAt($ladder, 5), $this->playerAt($ladder, 3));
        $before = $this->positionsByLabel((int) $ladder->id);

        $this->service()->recordForfeit($ladder, $challenge, false, 'Forderer nicht erschienen', null);

        $this->assertSame($before, $this->positionsByLabel((int) $ladder->id));
        $this->assertSame((int) $challenge->opponent_id, (int) PyramidChallenge::query()->findOrFail($challenge->id)->winner_id);
    }
}
