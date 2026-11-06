<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Database\Seeders;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawEligible;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\TokenService;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\Draws\BracketDrawService;
use App\Modules\Tournament\Services\Draws\DrawApplier;
use App\Modules\Tournament\Services\Draws\TokenDrawService;
use App\Modules\Tournament\Services\GenerationService;
use App\Modules\Tournament\Services\MatchResultService;
use App\Modules\Tournament\Services\TournamentManager;
use Illuminate\Database\Seeder;

/**
 * Fictional championship data for staging and demos (users tn-demo-NN@example.test, players "Demo Spieler NN"):
 *
 *  - "Demo-Meisterschaft" (season 2027, active) in the real format: group heads set; the upper group draw (AC)
 *    executed and in the picking phase with two picks; the lower one (DG) closed and applied; groups D to G
 *    half played with a complete three-way tie (needs_lot) in group D; KO lines generated, the KO_A pool q
 *    drawn and applied (placeholders), the pool ac draw committed, a discarded KO_B draw.
 *  - its test copy (setup);
 *  - "Demo-Meisterschaft 2026" (finished), three groups and a KO with a bye line, all group matches played.
 *
 * Every draw is built through DrawFactory with fixed seeds (hash('sha256', 'demo-tn-...')), so commitments
 * are identical on every run and every executed draw verifies. Only the tournament and draw tables and the
 * 24 example users are touched. Not idempotent without a truncate: run it through `phase2:scrub-staging`.
 */
class TournamentDemoSeeder extends Seeder
{
    private const CREATED = '2027-01-10 10:00:00';

    /** The real championship format (structure only, as stored by the old application). */
    public const FORMAT = [
        'version' => 1,
        'match_format' => 'best_of_3_mtb',
        'ranking' => ['points_per_win' => 2, 'tiebreak_scope' => 'tied', 'criteria' => ['points', 'head_to_head', 'set_diff', 'game_diff', 'lot']],
        'runner_up' => ['mode' => 'drop_last'],
        'phases' => [
            [
                'key' => 'AC', 'type' => 'groups', 'label' => 'Oberer Bewerb', 'seed_range' => [1, 10], 'heads' => 1,
                'groups' => [['key' => 'A', 'size' => 3], ['key' => 'B', 'size' => 3], ['key' => 'C', 'size' => 4]],
                'draw' => ['mode' => 'token', 'slot_order' => 'by_position'],
            ],
            [
                'key' => 'DG', 'type' => 'groups', 'label' => 'Unterer Bewerb', 'seed_range' => [11, 24], 'heads' => 1,
                'groups' => [['key' => 'D', 'size' => 3], ['key' => 'E', 'size' => 3], ['key' => 'F', 'size' => 4], ['key' => 'G', 'size' => 4]],
                'draw' => ['mode' => 'token', 'slot_order' => 'by_position'],
            ],
            [
                'key' => 'KO_A', 'type' => 'bracket', 'label' => 'KO-Bewerb A', 'size' => 16,
                'qualify' => [
                    ['from' => 'AC', 'rule' => 'all', 'pool' => 'ac'],
                    ['from' => 'DG', 'rule' => 'group_winners', 'pool' => 'q'],
                    ['from' => 'DG', 'rule' => 'best_seconds', 'pool' => 'q', 'count' => 2],
                ],
                'seeding' => [
                    ['from' => 'AC', 'rule' => 'group_winners', 'count' => 3, 'order' => 'record'],
                    ['from' => 'AC', 'rule' => 'best_seconds', 'count' => 1, 'order' => 'record'],
                ],
                'lines' => [
                    'seeded' => ['1' => 1, '2' => 16, '3' => 9, '4' => 8],
                    'bye' => [],
                    'pools' => ['q' => [3, 5, 7, 10, 11, 13], 'ac' => [2, 4, 6, 12, 14, 15]],
                    'pool_codes' => ['q' => ['prefix' => 'Q', 'start' => 1], 'ac' => ['prefix' => 'AC', 'start' => 5]],
                ],
                'protect' => ['no_same_pool_in_round_1' => ['q']],
                'swap_rules' => [['if_same_group_as_line' => 15, 'seed' => 2, 'swap_with_seed' => 3]],
                'draw' => ['mode' => 'direct', 'timing' => 'pre'],
            ],
            [
                'key' => 'KO_B', 'type' => 'bracket', 'label' => 'KO-Bewerb B', 'size' => 8,
                'qualify' => [['from' => 'DG', 'rule' => 'remainder', 'pool' => 'rest']],
                'seeding' => [['from' => 'DG', 'rule' => 'remainder', 'count' => 2, 'order' => 'drop_last']],
                'lines' => [
                    'seeded' => ['1' => 1, '2' => 8],
                    'bye' => [],
                    'pools' => ['rest' => [2, 3, 4, 5, 6, 7]],
                    'pool_codes' => ['rest' => ['prefix' => 'REST', 'start' => 3]],
                ],
                'draw' => ['mode' => 'direct', 'timing' => 'pre'],
            ],
        ],
    ];

    /** A small format with a bye line for the finished championship. */
    public const BYE_FORMAT = [
        'version' => 1,
        'match_format' => 'best_of_3_mtb',
        'ranking' => ['points_per_win' => 2, 'tiebreak_scope' => 'tied', 'criteria' => ['points', 'head_to_head', 'set_diff', 'game_diff', 'lot']],
        'runner_up' => ['mode' => 'points'],
        'phases' => [
            [
                'key' => 'G', 'type' => 'groups', 'label' => 'Vorrunde', 'seed_range' => [1, 9], 'heads' => 1,
                'groups' => [['key' => 'A', 'size' => 3], ['key' => 'B', 'size' => 3], ['key' => 'C', 'size' => 3]],
                'draw' => ['mode' => 'token', 'slot_order' => 'by_position'],
            ],
            [
                'key' => 'KO', 'type' => 'bracket', 'label' => 'KO-Runde mit Freilos', 'size' => 8,
                'qualify' => [
                    ['from' => 'G', 'rule' => 'group_winners', 'pool' => 'w'],
                    ['from' => 'G', 'rule' => 'best_seconds', 'pool' => 'r', 'count' => 3],
                ],
                'seeding' => [['from' => 'G', 'rule' => 'group_winners', 'count' => 3, 'order' => 'record']],
                'lines' => [
                    'seeded' => ['1' => 1, '2' => 8, '3' => 5],
                    'bye' => [2],
                    'pools' => ['w' => [3, 6], 'r' => [4, 7]],
                    'pool_codes' => ['w' => ['prefix' => 'W', 'start' => 1], 'r' => ['prefix' => 'R', 'start' => 1]],
                ],
                'protect' => ['no_same_pool_in_round_1' => ['w']],
                'draw' => ['mode' => 'direct', 'timing' => 'post'],
            ],
        ],
    ];

    /** @var array<int, User> number => demo user */
    private array $users = [];

    public function run(): void
    {
        for ($n = 1; $n <= 24; $n++) {
            $this->users[$n] = User::updateOrCreate(
                ['email' => sprintf('tn-demo-%02d@example.test', $n)],
                ['alias' => sprintf('Demo Spieler %02d', $n), 'status' => 'enabled', 'created' => self::CREATED]
            );
        }

        $real = $this->realChampionship();
        $this->testCopy($real);
        $this->finishedChampionship();
    }

    private function realChampionship(): Tournament
    {
        $t = Tournament::query()->create([
            'title' => 'Demo-Meisterschaft', 'season' => 2027, 'is_test' => 0, 'status' => 'setup', 'active' => 1,
            'format' => $this->json(self::FORMAT), 'itn_default' => '10.0', 'points_per_win' => 2,
            'tiebreak_scope' => 'tied', 'runnerup_mode' => 'drop_last', 'created' => self::CREATED,
        ]);
        $tid = (int) $t->tid;

        $tpid = [];
        for ($seed = 1; $seed <= 24; $seed++) {
            $tpid[$seed] = (int) TournamentPlayer::query()->create([
                'tid' => $tid, 'uid' => $this->users[$seed]->uid, 'label' => sprintf('Demo Spieler %02d', $seed),
                'gender' => $seed % 3 === 0 ? 'w' : 'm', 'itn' => number_format(2.0 + $seed * 0.3, 1, '.', ''),
                'itn_rated' => number_format(2.0 + $seed * 0.3, 1, '.', ''), 'seed' => $seed,
            ])->tpid;
        }
        // Partners in the upper pot: the token rule keeps them out of one group.
        TournamentPlayer::query()->whereKey($tpid[6])->update(['partner_tpid' => $tpid[9]]);
        TournamentPlayer::query()->whereKey($tpid[9])->update(['partner_tpid' => $tpid[6]]);

        // Group heads, bracket lines and bracket matches.
        $this->generate($tid);

        // AC: executed, picking phase open, two players picked.
        $ac = $this->tokenDraw($t, 'AC', 'demo-tn-ac', 2001);
        $this->claim($ac, [1 => [4, 'pick'], 2 => [9, 'pick']], $tpid, writeGroups: true);
        Draw::query()->whereKey($ac->did)->update(['pick_deadline' => '2027-03-20 20:00:00']);

        // DG: closed (seven picks, three fallback) and applied.
        $dg = $this->tokenDraw($t, 'DG', 'demo-tn-dg', 2002);
        $claims = [];
        for ($position = 1; $position <= 10; $position++) {
            $claims[$position] = [14 + $position, $position <= 7 ? 'pick' : 'fallback'];
        }
        $this->claim($dg, $claims, $tpid, writeGroups: false);
        Draw::query()->whereKey($dg->did)->update(['status' => 'closed', 'closed' => '2027-03-05 20:00:00']);
        $this->container->make(DrawApplier::class)->apply($dg->fresh(), $tid, $this->users[1]);
        $this->generate($tid);

        $this->playLowerGroups($tid);

        // KO_A pool q drawn ahead and applied; pool ac only committed; a discarded KO_B draw.
        $q = $this->bracketDraw($t, 'KO_A', 'q', 'demo-tn-ko-a-q', 2003, executed: true);
        $this->container->make(DrawApplier::class)->apply($q, $tid, $this->users[1]);
        $this->bracketDraw($t, 'KO_A', 'ac', 'demo-tn-ko-a-ac', 2004, executed: false);
        $discarded = $this->bracketDraw($t, 'KO_B', 'rest', 'demo-tn-ko-b-rest', 2005, executed: true);
        Draw::query()->whereKey($discarded->did)->update(['discarded' => '2027-03-06 18:00:00', 'discarded_reason' => 'Demo: falsche Runde angekündigt']);

        return $t->fresh() ?? $t;
    }

    /**
     * The test copy as TournamentLifecycleService::cloneTest() writes it (heads keep their place, everybody
     * else back in the pot, partners mapped); the service itself also selects the copy in an admin's
     * session, which a seeder has not.
     */
    private function testCopy(Tournament $real): void
    {
        $copy = Tournament::query()->create([
            'title' => 'TEST '.$real->title, 'season' => $real->season, 'is_test' => 1, 'cloned_from' => $real->tid,
            'active' => 0, 'status' => 'setup', 'format' => $real->format, 'itn_default' => $real->itn_default,
            'points_per_win' => $real->points_per_win, 'tiebreak_scope' => $real->tiebreak_scope,
            'runnerup_mode' => $real->runnerup_mode, 'created' => '2027-03-01 09:00:00',
        ]);

        $map = [];
        $partners = [];
        foreach (TournamentPlayer::query()->where('tid', $real->tid)->orderBy('tpid')->get() as $player) {
            $isHead = (int) $player->grp_pos === 1;
            $map[(int) $player->tpid] = (int) TournamentPlayer::query()->create([
                'tid' => $copy->tid, 'uid' => $player->uid, 'label' => $player->label, 'gender' => $player->gender,
                'itn' => $player->itn, 'itn_rated' => $player->itn_rated, 'seed' => $player->seed,
                'grp' => $isHead ? $player->grp : null, 'grp_pos' => $isHead ? 1 : null, 'points_adjust' => 0,
            ])->tpid;
            if ($player->partner_tpid) {
                $partners[(int) $player->tpid] = (int) $player->partner_tpid;
            }
        }

        foreach ($partners as $tpid => $partner) {
            if (isset($map[$partner])) {
                TournamentPlayer::query()->whereKey($map[$tpid])->update(['partner_tpid' => $map[$partner]]);
            }
        }
    }

    private function finishedChampionship(): void
    {
        $t = Tournament::query()->create([
            'title' => 'Demo-Meisterschaft 2026', 'season' => 2026, 'is_test' => 0, 'status' => 'finished', 'active' => 0,
            'format' => $this->json(self::BYE_FORMAT), 'itn_default' => '10.0', 'points_per_win' => 2,
            'tiebreak_scope' => 'tied', 'runnerup_mode' => 'points', 'created' => '2026-01-10 10:00:00',
        ]);
        $tid = (int) $t->tid;

        $layout = [1 => ['A', 1], 2 => ['B', 1], 3 => ['C', 1], 4 => ['C', 2], 5 => ['B', 2], 6 => ['A', 2], 7 => ['A', 3], 8 => ['B', 3], 9 => ['C', 3]];
        foreach ($layout as $seed => [$group, $pos]) {
            TournamentPlayer::query()->create([
                'tid' => $tid, 'uid' => $this->users[$seed]->uid, 'label' => sprintf('Demo Spieler %02d', $seed),
                'itn_rated' => number_format(2.0 + $seed * 0.3, 1, '.', ''), 'seed' => $seed, 'grp' => $group, 'grp_pos' => $pos,
            ]);
        }

        $this->generate($tid);

        // Every group match played: the better seed wins.
        foreach (TournamentMatch::query()->where('tid', $tid)->whereNotNull('grp')->orderBy('tmid')->get() as $match) {
            $aWins = $this->seedOf((int) $match->tpid_a) < $this->seedOf((int) $match->tpid_b);
            $this->play($match, $aWins, [6, 3], [6, 2]);
        }
    }

    private function seedOf(int $tpid): int
    {
        return (int) TournamentPlayer::query()->whereKey($tpid)->value('seed');
    }

    /** D: a complete three-way tie (each wins once 6:4 6:4); E: one match; F: two matches; G: open. */
    private function playLowerGroups(int $tid): void
    {
        $matches = fn (string $group) => TournamentMatch::query()->where('tid', $tid)->where('grp', $group)->orderBy('tmid')->get();

        $d = $matches('D');
        $order = TournamentPlayer::query()->where('tid', $tid)->where('grp', 'D')->orderBy('grp_pos')->pluck('tpid')->map(fn ($id): int => (int) $id)->all();
        foreach ($d as $match) {
            $a = array_search((int) $match->tpid_a, $order, true);
            $b = array_search((int) $match->tpid_b, $order, true);
            // Cycle 1 beats 2, 2 beats 3, 3 beats 1.
            $this->play($match, ((int) $b - (int) $a + 3) % 3 === 1, [6, 4], [6, 4]);
        }

        $this->play($matches('E')->first(), true, [6, 2], [3, 6], [10, 7]);

        foreach ($matches('F')->take(2) as $i => $match) {
            $this->play($match, $i === 0, [7, 6], [6, 4]);
        }
    }

    /**
     * @param  array{int, int}  $first
     * @param  array{int, int}  $second
     * @param  array{int, int}|null  $tiebreak
     */
    private function play(?TournamentMatch $match, bool $aWins, array $first, array $second, ?array $tiebreak = null): void
    {
        if ($match === null) {
            return;
        }

        $side = fn (array $set): array => $aWins ? $set : [$set[1], $set[0]];
        [$s1a, $s1b] = $side($first);
        [$s2a, $s2b] = $side($second);
        $input = ['s1a' => (string) $s1a, 's1b' => (string) $s1b, 's2a' => (string) $s2a, 's2b' => (string) $s2b];

        if ($tiebreak !== null) {
            [$input['mtba'], $input['mtbb']] = array_map('strval', $side($tiebreak));
        }

        $this->container->make(MatchResultService::class)->save($match, $input, $this->users[1], true);
    }

    private function generate(int $tid): void
    {
        $this->container->make(GenerationService::class)->generate($tid);
    }

    /** A token draw over the phase's pot and slots, executed (picking) with fixed seeds. */
    private function tokenDraw(Tournament $t, string $phaseKey, string $slug, int $round): Draw
    {
        $m = $this->container->make(TournamentManager::class);
        $tid = (int) $t->tid;
        $m->forget($tid);
        $format = $m->format($tid);
        $slots = $format->drawSlots($phaseKey);
        $pool = $m->drawPool($tid, $phaseKey);
        $tokens = new TokenService;
        $payload = $tokens->payload($tokens->tokenNames(count($slots), $phaseKey), $slots, sprintf(TokenDrawService::RULE, $round));

        $draw = Draw::factory()
            ->state($this->drawState($t, $slug, $round, sprintf(TokenDrawService::TITLE, $t->title, $format->phase($phaseKey)['label'])))
            ->token(count($slots), $phaseKey, $slots)
            ->withPayload($payload)
            ->executed(hash('sha256', 'demo-tn-beacon-'.$slug))
            ->create();

        foreach ($pool as $player) {
            DrawEligible::query()->create(['did' => $draw->did, 'tpid' => (int) $player['tpid']]);
        }

        return $draw;
    }

    private function bracketDraw(Tournament $t, string $phaseKey, string $pool, string $slug, int $round, bool $executed): Draw
    {
        $phase = self::FORMAT['phases'][$phaseKey === 'KO_A' ? 2 : 3];
        $code = $phase['lines']['pool_codes'][$pool];
        $entries = [];
        $slots = [];
        foreach ($phase['lines']['pools'][$pool] as $i => $line) {
            $entries[] = $code['prefix'].($code['start'] + $i);
            $slots[] = $phaseKey.':'.$line;
        }

        $factory = Draw::factory()
            ->state($this->drawState($t, $slug, $round, sprintf(BracketDrawService::TITLE, $t->title, $phase['label'], strtoupper($pool))))
            ->withPayload(['entries' => $entries, 'slots' => $slots, 'rule' => sprintf(BracketDrawService::RULE, $phaseKey, $pool)]);

        $draw = ($executed ? $factory->executed(hash('sha256', 'demo-tn-beacon-'.$slug)) : $factory)->create();

        return $draw;
    }

    /** @return array<string, mixed> */
    private function drawState(Tournament $t, string $slug, int $round, string $title): array
    {
        return [
            'tid' => $t->tid,
            'slug' => $slug,
            'title' => mb_substr($title, 0, 160),
            'server_seed' => hash('sha256', 'demo-tn-server-'.$slug),
            'beacon_source' => 'drand:round:'.$round,
            'created_uid' => $this->users[1]->uid,
            'created' => '2027-03-01 18:00:00',
        ];
    }

    /**
     * @param  array<int, array{int, string}>  $byPosition  token position => [seed, pick|fallback]
     * @param  array<int, int>  $tpid  seed => tpid
     */
    private function claim(Draw $draw, array $byPosition, array $tpid, bool $writeGroups): void
    {
        foreach ($byPosition as $position => [$seed, $how]) {
            $token = DrawToken::query()->where('did', $draw->did)->where('position', $position)->firstOrFail();
            $token->newQuery()->whereKey($token->dtid)->update([
                'claimed_tpid' => $tpid[$seed],
                'claimed_uid' => $this->users[$seed]->uid,
                'claimed' => '2027-03-02 19:00:00',
                'assigned_by' => $how,
            ]);

            // As a pick does: the group is effective at once (the draw ran before the picking phase).
            if ($writeGroups && preg_match('/^([A-Z]+)([0-9]+)$/', (string) $token->slot, $parts)) {
                TournamentPlayer::query()->whereKey($tpid[$seed])->update(['grp' => $parts[1], 'grp_pos' => (int) $parts[2]]);
            }
        }

        if ($writeGroups) {
            $this->generate((int) $draw->tid);
        }
    }

    /** @param  array<string, mixed>  $format */
    private function json(array $format): string
    {
        return (string) json_encode($format, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
