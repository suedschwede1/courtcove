<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Services\ChallengeService;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Support\PyramidRuleException;
use App\Modules\Pyramid\Support\Verdict;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPyramidLadders;
use Tests\TestCase;

class ChallengeServiceTest extends TestCase
{
    use BuildsPyramidLadders;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set(config('app.timezone'));

        parent::tearDown();
    }

    private function service(): ChallengeService
    {
        return $this->app->make(ChallengeService::class);
    }

    private function key(PyramidLadder $ladder, PyramidPlayer $challenger, PyramidPlayer $opponent, ?CarbonImmutable $now = null): ?string
    {
        return $this->service()->check($ladder, $challenger, $opponent, $now)?->key;
    }

    private function at(PyramidLadder $ladder, int $position): PyramidPlayer
    {
        return $this->playerAt($ladder, $position);
    }

    private function resolve(PyramidChallenge $challenge, PyramidPlayer $winner, string $resolved, string $status = 'played'): void
    {
        $challenge->update([
            'status' => $status,
            'winner_id' => $winner->id,
            'score' => '6:4 6:4',
            'resolved' => $resolved,
        ]);
    }

    private function open(PyramidLadder $ladder, int $from, int $to): PyramidChallenge
    {
        return $this->service()->create($ladder, $this->at($ladder, $from), $this->at($ladder, $to), null);
    }

    // Zulaessigkeit

    #[Test]
    public function position_8_may_challenge_5_and_7_but_not_2(): void
    {
        $ladder = $this->makeLadder(10);

        $this->assertNull($this->key($ladder, $this->at($ladder, 8), $this->at($ladder, 5)));
        $this->assertNull($this->key($ladder, $this->at($ladder, 8), $this->at($ladder, 7)));

        $verdict = $this->service()->check($ladder, $this->at($ladder, 8), $this->at($ladder, 2));
        $this->assertSame('pyramid::check.position', $verdict?->key);
        $this->assertSame(['position' => 8, 'allowed' => '4, 5, 6, 7'], $verdict->replace);
    }

    #[Test]
    public function challenging_downwards_is_rejected(): void
    {
        $ladder = $this->makeLadder(10);

        $this->assertSame('pyramid::check.position', $this->key($ladder, $this->at($ladder, 5), $this->at($ladder, 6)));
    }

    #[Test]
    public function challenging_yourself_is_rejected(): void
    {
        $ladder = $this->makeLadder(10);

        $this->assertSame('pyramid::check.self', $this->key($ladder, $this->at($ladder, 5), $this->at($ladder, 5)));
    }

    #[Test]
    public function verdict_renders_the_german_and_english_sentence(): void
    {
        $ladder = $this->makeLadder(10);
        $verdict = $this->service()->check($ladder, $this->at($ladder, 8), $this->at($ladder, 2));

        $this->assertSame('Von Platz 8 darfst du nur die Plätze 4, 5, 6, 7 fordern.', $verdict->message('de'));
        $this->assertStringContainsString('8', $verdict->message('en'));
        $this->assertNotSame($verdict->message('de'), $verdict->message('en'));
    }

    #[Test]
    public function setup_ladder_allows_no_challenge(): void
    {
        $ladder = $this->makeLadder(10, ['status' => 'setup']);

        $this->assertSame('pyramid::check.not_running', $this->key($ladder, $this->at($ladder, 9), $this->at($ladder, 6)));
    }

    #[Test]
    public function open_challenge_blocks_both_players(): void
    {
        $ladder = $this->makeLadder(10);
        $this->open($ladder, 8, 5);

        $this->assertSame('pyramid::check.own_open', $this->key($ladder, $this->at($ladder, 8), $this->at($ladder, 6)));

        $verdict = $this->service()->check($ladder, $this->at($ladder, 9), $this->at($ladder, 5));
        $this->assertSame('pyramid::check.opponent_open', $verdict?->key);
        $this->assertSame(['name' => 'Spieler 5'], $verdict->replace);

        $this->assertNull($this->key($ladder, $this->at($ladder, 9), $this->at($ladder, 6)));
    }

    // Regel 4.4

    #[Test]
    public function winner_is_protected_for_three_days(): void
    {
        $ladder = $this->makeLadder(10);
        $challenge = $this->open($ladder, 8, 5);
        $winner = $this->at($ladder, 5);
        $this->resolve($challenge, $winner, now()->format('Y-m-d H:i:s'));

        $verdict = $this->service()->check($ladder, $this->at($ladder, 9), $winner);
        $this->assertSame('pyramid::check.protected', $verdict?->key);
        $this->assertSame('Spieler 5', $verdict->replace['name']);
        $this->assertSame(now()->addDays(3)->format('d.m.Y'), $verdict->replace['date']);

        $this->assertNull($this->key($ladder, $this->at($ladder, 9), $winner, CarbonImmutable::now()->addDays(4)));
    }

    #[Test]
    public function protection_ends_exactly_after_three_days(): void
    {
        $ladder = $this->makeLadder(8);
        $challenge = $this->open($ladder, 8, 5);
        $winner = $this->at($ladder, 5);
        $this->resolve($challenge, $winner, now()->subSeconds(259200)->format('Y-m-d H:i:s'));

        $this->assertNull($this->key($ladder, $this->at($ladder, 7), $winner));
        $this->assertSame(
            'pyramid::check.protected',
            $this->key($ladder, $this->at($ladder, 7), $winner, CarbonImmutable::now()->subSecond())
        );
    }

    #[Test]
    public function protection_window_counts_seconds_across_dst_end(): void
    {
        config(['app.timezone' => 'Europe/Vienna']);
        date_default_timezone_set('Europe/Vienna');

        $ladder = $this->makeLadder(10);
        $challenge = $this->open($ladder, 8, 5);
        $o = $this->at($ladder, 5);
        $c = $this->at($ladder, 7);
        $this->resolve($challenge, $o, '2026-10-24 12:00:00');
        $s = $this->service();

        // 3 * 86400 s after 12:00 CEST is 11:00 CET on the 27th, not 12:00.
        $this->assertSame('pyramid::check.protected', $s->check($ladder, $c, $o, CarbonImmutable::parse('2026-10-27 10:59:59'))?->key);
        $this->assertNull($s->check($ladder, $c, $o, CarbonImmutable::parse('2026-10-27 11:00:00')));
    }

    // Regel 4.3 / 4.4

    #[Test]
    public function loser_is_blocked_for_seven_days(): void
    {
        $ladder = $this->makeLadder(10);
        $challenge = $this->open($ladder, 8, 5);
        $loser = $this->at($ladder, 8);
        $this->resolve($challenge, $this->at($ladder, 5), now()->format('Y-m-d H:i:s'));

        $verdict = $this->service()->check($ladder, $loser, $this->at($ladder, 6));
        $this->assertSame('pyramid::check.cooldown', $verdict?->key);
        $this->assertSame(['date' => now()->addDays(7)->format('d.m.Y')], $verdict->replace);

        $this->assertNull($this->key($ladder, $loser, $this->at($ladder, 6), CarbonImmutable::now()->addDays(8)));
    }

    #[Test]
    public function cooldown_ends_exactly_after_seven_days(): void
    {
        $ladder = $this->makeLadder(8);
        $challenge = $this->open($ladder, 8, 5);
        $loser = $this->at($ladder, 8);
        $this->resolve($challenge, $this->at($ladder, 5), now()->subSeconds(604800)->format('Y-m-d H:i:s'));

        $this->assertNull($this->key($ladder, $loser, $this->at($ladder, 7)));
        $this->assertSame(
            'pyramid::check.cooldown',
            $this->key($ladder, $loser, $this->at($ladder, 7), CarbonImmutable::now()->subSecond())
        );
    }

    #[Test]
    public function forfeit_starts_the_loser_cooldown(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, 5, 3);
        $challenge->update(['status' => 'forfeited', 'winner_id' => $this->at($ladder, 5)->id, 'resolved' => now()->format('Y-m-d H:i:s')]);

        $this->assertSame('pyramid::check.cooldown', $this->key($ladder, $this->at($ladder, 3), $this->at($ladder, 2)));
    }

    #[Test]
    public function expired_challenge_starts_no_block(): void
    {
        $ladder = $this->makeLadder(6);
        $challenge = $this->open($ladder, 5, 3);
        $challenge->update(['status' => 'expired', 'resolved' => now()->format('Y-m-d H:i:s')]);

        $this->assertNull($this->key($ladder, $this->at($ladder, 5), $this->at($ladder, 4)));
    }

    #[Test]
    public function protection_boundary_is_exclusive(): void
    {
        $ladder = $this->makeLadder(8);
        $challenge = $this->open($ladder, 8, 5);
        $winner = $this->at($ladder, 5);
        $this->resolve($challenge, $winner, now()->subDays(3)->format('Y-m-d H:i:s'));

        $this->assertNull($this->key($ladder, $this->at($ladder, 7), $winner));
    }

    // Regel 6.1 and Spec 3.2.1

    #[Test]
    public function released_entrant_may_challenge_any_active_player(): void
    {
        $ladder = $this->makeLadder(10);
        $entrant = PyramidPlayer::factory()->entering()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler E']);

        $this->assertNull($this->key($ladder, $entrant, $this->at($ladder, 2)));
    }

    #[Test]
    public function merely_waiting_player_may_not_challenge(): void
    {
        $ladder = $this->makeLadder(10);
        $waiting = PyramidPlayer::factory()->waiting()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler W']);

        $this->assertSame('pyramid::check.not_released', $this->key($ladder, $waiting, $this->at($ladder, 2)));
    }

    #[Test]
    public function removed_challenger_does_not_participate(): void
    {
        $ladder = $this->makeLadder(10);
        $removed = PyramidPlayer::factory()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler R', 'status' => 'removed', 'position' => null]);

        $this->assertSame('pyramid::check.not_participating', $this->key($ladder, $removed, $this->at($ladder, 2)));
    }

    #[Test]
    public function inactive_opponent_is_not_challengeable(): void
    {
        $ladder = $this->makeLadder(10);
        $waiting = PyramidPlayer::factory()->waiting()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler W']);

        $verdict = $this->service()->check($ladder, $this->at($ladder, 9), $waiting);
        $this->assertSame('pyramid::check.opponent_inactive', $verdict?->key);
        $this->assertSame(['name' => 'Spieler W'], $verdict->replace);
    }

    #[Test]
    public function paused_opponent_is_not_challengeable_but_keeps_position(): void
    {
        $ladder = $this->makeLadder(8);
        $paused = $this->at($ladder, 5);
        $paused->update(['status' => 'paused']);

        $this->assertSame('pyramid::check.opponent_inactive', $this->key($ladder, $this->at($ladder, 8), $paused->fresh()));
        $this->assertNull($this->key($ladder, $this->at($ladder, 8), $this->at($ladder, 6)));
        $this->assertSame(5, (int) $paused->fresh()->position);
        $this->assertRankingIntact($ladder->id);
    }

    // create

    #[Test]
    public function create_snapshots_positions_and_deadline(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00:00'));
        $ladder = $this->makeLadder(10);
        $challenger = $this->at($ladder, 8);
        $opponent = $this->at($ladder, 5);

        $challenge = $this->service()->create($ladder, $challenger, $opponent, 42);

        $this->assertSame('open', $challenge->status);
        $this->assertSame('2026-11-04 10:00:00', $challenge->created);
        $this->assertSame('2026-11-11', $challenge->deadline);
        $this->assertSame(8, (int) $challenge->challenger_pos);
        $this->assertSame(5, (int) $challenge->opponent_pos);

        $event = PyramidEvent::query()->where('challenge_id', $challenge->id)->firstOrFail();
        $this->assertSame('challenge', $event->type);
        $this->assertSame(42, (int) $event->actor_uid);
        $this->assertSame(
            sprintf('{"challenger":%d,"opponent":%d,"challenger_pos":8,"opponent_pos":5}', $challenger->id, $opponent->id),
            $event->payload
        );
    }

    #[Test]
    public function entrant_challenge_stores_null_challenger_position(): void
    {
        $ladder = $this->makeLadder(10);
        $entrant = PyramidPlayer::factory()->entering()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler E']);

        $challenge = $this->service()->create($ladder, $entrant, $this->at($ladder, 3), null);

        $this->assertNull($challenge->fresh()->challenger_pos);
        $event = PyramidEvent::query()->where('challenge_id', $challenge->id)->firstOrFail();
        $this->assertStringContainsString('"challenger_pos":null', $event->payload);
    }

    #[Test]
    public function second_challenge_by_the_same_challenger_is_rejected(): void
    {
        $ladder = $this->makeLadder(8);
        $this->open($ladder, 8, 5);

        $this->expectException(PyramidRuleException::class);
        $this->expectExceptionMessage('pyramid::check.own_open');

        $this->open($ladder, 8, 6);
    }

    #[Test]
    public function crossed_challenge_is_rejected_by_create(): void
    {
        $ladder = $this->makeLadder(8);
        $this->open($ladder, 8, 5);

        try {
            $this->open($ladder, 5, 8);
            $this->fail('create must throw');
        } catch (PyramidRuleException $e) {
            $this->assertInstanceOf(Verdict::class, $e->verdict);
            // The position rule fires before rule 3.5 for the reversed pair: challenging downwards is never legal.
            $this->assertSame('pyramid::check.position', $e->verdict->key);
        }

        $this->assertSame(1, PyramidChallenge::query()->count());
    }

    #[Test]
    public function create_uses_fresh_state_not_the_stale_models(): void
    {
        $ladder = $this->makeLadder(8);
        $stale = $this->at($ladder, 5);
        $stale->newQuery()->whereKey($stale->id)->update(['status' => 'paused']);

        $this->expectException(PyramidRuleException::class);
        $this->expectExceptionMessage('pyramid::check.opponent_inactive');

        $this->service()->create($ladder, $this->at($ladder, 8), $stale, null);
    }

    // transition

    #[Test]
    public function transition_updates_logs_and_returns_the_row_before(): void
    {
        $ladder = $this->makeLadder(8);
        $challenge = $this->open($ladder, 8, 5);

        $before = $this->service()->transition($challenge->id, ['status' => 'declined', 'decline_reason' => 'injury'], 'admin', ['message' => 'x'], 7);

        $this->assertSame('open', $before->status);
        $this->assertSame('declined', $challenge->fresh()->status);
        $this->assertSame(1, PyramidEvent::query()->where('challenge_id', $challenge->id)->where('type', 'admin')->count());
    }

    #[Test]
    public function stale_transition_is_rejected(): void
    {
        $ladder = $this->makeLadder(8);
        $challenge = $this->open($ladder, 8, 5);
        $this->service()->transition($challenge->id, ['status' => 'declined'], 'admin', [], null);

        try {
            $this->service()->transition($challenge->id, ['status' => 'withdrawn'], 'admin', ['message' => 'x'], null);
            $this->fail('second transition must throw');
        } catch (PyramidRuleException $e) {
            $this->assertSame('pyramid::check.challenge_closed', $e->verdict->key);
        }

        $this->assertSame('declined', $challenge->fresh()->status);
    }

    #[Test]
    public function unknown_challenge_is_rejected(): void
    {
        $this->expectException(PyramidRuleException::class);
        $this->expectExceptionMessage('pyramid::check.challenge_missing');

        $this->service()->transition(999, ['status' => 'withdrawn'], 'admin', [], null);
    }

    #[Test]
    public function closed_ladder_rejects_transitions(): void
    {
        $ladder = $this->makeLadder(8);
        $challenge = $this->open($ladder, 8, 5);
        $ladder->update(['status' => 'closed']);

        try {
            $this->service()->transition($challenge->id, ['status' => 'withdrawn'], 'admin', [], null);
            $this->fail('transition must throw');
        } catch (PyramidRuleException $e) {
            $this->assertSame('pyramid::check.ladder_closed', $e->verdict->key);
        }

        $this->assertSame('open', $challenge->fresh()->status);
    }

    // annotate

    #[Test]
    public function annotate_without_viewer_asks_to_log_in(): void
    {
        $ladder = $this->makeLadder(4);
        $annotated = $this->service()->annotate($ladder, null, $this->app->make(PyramidRepository::class)->ranking($ladder->id));

        $this->assertCount(4, $annotated);
        foreach ($annotated as $verdict) {
            $this->assertSame('pyramid::check.login', $verdict?->key);
        }
    }

    #[Test]
    public function annotate_marks_challengeable_players_null(): void
    {
        $ladder = $this->makeLadder(6);
        $ranking = $this->app->make(PyramidRepository::class)->ranking($ladder->id);

        $annotated = $this->service()->annotate($ladder, $this->at($ladder, 5), $ranking);

        $this->assertNull($annotated[$this->at($ladder, 3)->id]);
        $this->assertNull($annotated[$this->at($ladder, 4)->id]);
        $this->assertSame('pyramid::check.position', $annotated[$this->at($ladder, 6)->id]?->key);
        $this->assertSame('pyramid::check.self', $annotated[$this->at($ladder, 5)->id]?->key);
    }
}
