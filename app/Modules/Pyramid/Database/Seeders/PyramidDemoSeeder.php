<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Database\Seeders;

use App\Models\User;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Support\EventText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Fictional Pyramid data for staging and demos: a running men's ladder with every
 * challenge status, a ladder in setup and a closed previous season. Deterministic
 * (fixed users, labels, positions) and only touches the pyramid tables plus the
 * example.test demo users; it never reads other tables.
 */
class PyramidDemoSeeder extends Seeder
{
    private const FORMAT = 'Y-m-d H:i:s';

    /** @var array<int, User> */
    private array $users = [];

    public function run(): void
    {
        $now = CarbonImmutable::now();

        $admin = User::updateOrCreate(
            ['email' => 'pyramid-demo-admin@example.test'],
            ['alias' => 'Demo Admin', 'status' => 'enabled', 'created' => $now->format(self::FORMAT)]
        );

        // Users 01-21: 01-17 play the running men's ladder, 18-21 wait on the women's ladder.
        for ($n = 1; $n <= 21; $n++) {
            $user = User::updateOrCreate(
                ['email' => sprintf('pyramid-demo-%02d@example.test', $n)],
                ['alias' => sprintf('Demo Spieler %02d', $n), 'status' => 'enabled', 'created' => $now->format(self::FORMAT)]
            );
            $user->setMeta('phone', sprintf('+43 000 000 00%02d', $n));
            $this->users[$n] = $user;
        }

        $herren = $this->ladder('Demo Herren', 2027, 'running', true, $now->format(self::FORMAT));
        PyramidGrant::create([
            'ladder_id' => $herren->id, 'uid' => $admin->uid, 'role' => 'admin', 'created' => $now->format(self::FORMAT),
        ]);

        /** @var array<int, PyramidPlayer> $byPos players by ladder position 1-15 */
        $byPos = [];
        for ($n = 1; $n <= 15; $n++) {
            $byPos[$n] = $this->player($herren, $n, $n === 7 ? 'paused' : 'active', $n);
        }
        $waiting = $this->player($herren, 16, 'waiting', null);
        $entering = $this->player($herren, 17, 'entering', null);

        $this->event($herren, 'admin', ['message' => EventText::SEEDED], null, $now->subDays(14));
        $this->event($herren, 'join', ['player' => $waiting->id], null, $now->subDays(2));
        $this->event($herren, 'join', ['player' => $entering->id], null, $now->subDays(1));

        $this->seedChallenges($herren, $byPos, $now);

        $damen = $this->ladder('Demo Damen', 2027, 'setup', false, $now->format(self::FORMAT));
        for ($n = 18; $n <= 21; $n++) {
            $this->player($damen, $n, 'waiting', null, 'f');
        }

        $closed = $this->ladder('Demo Herren', 2026, 'closed', false, $now->subYear()->format(self::FORMAT));
        for ($n = 1; $n <= 4; $n++) {
            $this->player($closed, $n, 'active', $n);
        }
        $this->event($closed, 'admin', ['message' => EventText::CLOSED], null, $now->subMonths(2));
    }

    /**
     * @param  array<int, PyramidPlayer>  $p  players by position
     */
    private function seedChallenges(PyramidLadder $ladder, array $p, CarbonImmutable $now): void
    {
        // open, created today
        $open = $this->challenge($ladder, $p[9], $p[6], 'open', [
            'created' => $now->format(self::FORMAT), 'deadline' => $now->addDays(7)->toDateString(),
        ]);
        $this->event($ladder, 'challenge', $this->challengePayload($p[9], $p[6]), $open, $now);

        // scheduled in three days at 18:00
        $slot = $now->addDays(3)->setTime(18, 0)->format(self::FORMAT);
        $scheduled = $this->challenge($ladder, $p[10], $p[8], 'scheduled', [
            'created' => $now->subDays(2)->format(self::FORMAT), 'deadline' => $now->addDays(5)->toDateString(), 'scheduled_at' => $slot,
        ]);
        $this->event($ladder, 'challenge', $this->challengePayload($p[10], $p[8]), $scheduled, $now->subDays(2));
        $this->event($ladder, 'schedule', [
            'challenger' => $p[10]->id, 'opponent' => $p[8]->id, 'scheduled_at' => $slot,
        ], $scheduled, $now->subDays(1));

        // overdue: still open although the deadline passed
        $overdue = $this->challenge($ladder, $p[12], $p[11], 'open', [
            'created' => $now->subDays(10)->format(self::FORMAT), 'deadline' => $now->subDays(3)->toDateString(),
        ]);
        $this->event($ladder, 'challenge', $this->challengePayload($p[12], $p[11]), $overdue, $now->subDays(10));

        // played: challenger won 6:4 6:3 and took position 3 (was 4)
        $played = $this->challenge($ladder, $p[3], $p[4], 'played', [
            'challenger_pos' => 4, 'opponent_pos' => 3, 'winner_id' => $p[3]->id, 'score' => '6:4 6:3',
            'created' => $now->subDays(9)->format(self::FORMAT), 'deadline' => $now->subDays(2)->toDateString(),
            'resolved' => $now->subDays(5)->format(self::FORMAT),
        ]);
        $this->event($ladder, 'challenge', ['challenger' => $p[3]->id, 'opponent' => $p[4]->id, 'challenger_pos' => 4, 'opponent_pos' => 3], $played, $now->subDays(9));
        $this->event($ladder, 'result', ['winner' => $p[3]->id, 'loser' => $p[4]->id, 'score' => '6:4 6:3'], $played, $now->subDays(5));

        // forfeited: the opponent did not show up, the challenger moved up
        $forfeit = $this->challenge($ladder, $p[13], $p[14], 'forfeited', [
            'challenger_pos' => 14, 'opponent_pos' => 13, 'winner_id' => $p[13]->id, 'score' => EventText::FORFEIT_SCORE,
            'created' => $now->subDays(8)->format(self::FORMAT), 'deadline' => $now->subDays(1)->toDateString(),
            'resolved' => $now->subDays(4)->format(self::FORMAT),
        ]);
        $this->event($ladder, 'challenge', ['challenger' => $p[13]->id, 'opponent' => $p[14]->id, 'challenger_pos' => 14, 'opponent_pos' => 13], $forfeit, $now->subDays(8));
        $this->event($ladder, 'result', ['winner' => $p[13]->id, 'loser' => $p[14]->id, 'score' => EventText::FORFEIT_SCORE], $forfeit, $now->subDays(4));

        // declined with a reason
        $declined = $this->challenge($ladder, $p[5], $p[2], 'declined', [
            'decline_reason' => 'other_match', 'created' => $now->subDays(6)->format(self::FORMAT),
            'deadline' => $now->addDays(1)->toDateString(), 'resolved' => $now->subDays(6)->format(self::FORMAT),
        ]);
        $this->event($ladder, 'challenge', $this->challengePayload($p[5], $p[2]), $declined, $now->subDays(6));
        $this->event($ladder, 'admin', ['message' => EventText::DECLINED], $declined, $now->subDays(6));

        // withdrawn by the challenger
        $withdrawn = $this->challenge($ladder, $p[15], $p[13], 'withdrawn', [
            'created' => $now->subDays(7)->format(self::FORMAT), 'deadline' => $now->addDays(0)->toDateString(),
            'resolved' => $now->subDays(7)->format(self::FORMAT),
        ]);
        $this->event($ladder, 'challenge', $this->challengePayload($p[15], $p[13]), $withdrawn, $now->subDays(7));
        $this->event($ladder, 'admin', ['message' => EventText::WITHDRAWN], $withdrawn, $now->subDays(7));

        // expired without a match
        $expired = $this->challenge($ladder, $p[2], $p[1], 'expired', [
            'created' => $now->subDays(20)->format(self::FORMAT), 'deadline' => $now->subDays(13)->toDateString(),
            'resolved' => $now->subDays(12)->format(self::FORMAT),
            'admin_note' => 'Demo: kein Termin gefunden',
        ]);
        $this->event($ladder, 'challenge', $this->challengePayload($p[2], $p[1]), $expired, $now->subDays(20));
        $this->event($ladder, 'admin', [
            'message' => EventText::make(EventText::EXPIRED, [':note' => 'Demo: kein Termin gefunden']),
        ], $expired, $now->subDays(12));
    }

    private function ladder(string $title, int $season, string $status, bool $default, string $created): PyramidLadder
    {
        return PyramidLadder::create([
            'title' => $title, 'season' => $season, 'category' => null, 'status' => $status,
            'itn_default' => 10.0, 'days_to_play' => 7, 'winner_protected_days' => 3, 'loser_cooldown_days' => 7,
            'is_default' => $default ? 1 : 0, 'rules_version' => null, 'created' => $created,
        ]);
    }

    private function player(PyramidLadder $ladder, int $n, string $status, ?int $position, string $gender = 'm'): PyramidPlayer
    {
        $user = $this->users[$n];

        return PyramidPlayer::create([
            'ladder_id' => $ladder->id,
            'uid' => $user->uid,
            'label' => sprintf('Demo Spieler %02d', $n),
            'itn' => 10.0 + ($n % 5) / 2,
            'itn_rated' => 10.0 + ($n % 5) / 2,
            'position' => $position,
            'status' => $status,
            'gender' => $gender,
            'phone' => sprintf('+43 000 000 00%02d', $n),
            'email' => $user->email,
            'joined' => CarbonImmutable::now()->subDays(30)->format(self::FORMAT),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function challenge(PyramidLadder $ladder, PyramidPlayer $challenger, PyramidPlayer $opponent, string $status, array $attributes): PyramidChallenge
    {
        return PyramidChallenge::create($attributes + [
            'ladder_id' => $ladder->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'challenger_pos' => $challenger->position,
            'opponent_pos' => $opponent->position,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, int|null>
     */
    private function challengePayload(PyramidPlayer $challenger, PyramidPlayer $opponent): array
    {
        return [
            'challenger' => (int) $challenger->id,
            'opponent' => (int) $opponent->id,
            'challenger_pos' => $challenger->position,
            'opponent_pos' => $opponent->position,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(PyramidLadder $ladder, string $type, array $payload, ?PyramidChallenge $challenge, CarbonImmutable $at): void
    {
        PyramidEvent::create([
            'ladder_id' => $ladder->id,
            'challenge_id' => $challenge?->id,
            'type' => $type,
            'actor_uid' => null,
            'payload' => json_encode($payload),
            'created' => $at->format(self::FORMAT),
        ]);
    }
}
