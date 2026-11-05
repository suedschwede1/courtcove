<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\MixedTournament;

use App\Modules\MixedTournament\Domain\MixedRuleException;
use App\Modules\MixedTournament\Domain\ProposalGenerator;
use App\Modules\MixedTournament\Domain\RoundService;
use App\Modules\MixedTournament\Models\MixedAvoidPair;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedMatch;
use App\Modules\MixedTournament\Models\MixedParticipant;
use App\Modules\MixedTournament\Models\MixedRound;
use App\Modules\MixedTournament\Models\MixedRoundPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\SeedsRandomizer;
use Tests\TestCase;

class ProposalGeneratorTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsRandomizer;

    private const SNAPSHOT_DIR = __DIR__.'/../../../Fixtures/mixed/snapshots';

    private function generator(): ProposalGenerator
    {
        return $this->app->make(ProposalGenerator::class);
    }

    private function rounds(): RoundService
    {
        return $this->app->make(RoundService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>
     */
    private function participants(MixedEvent $event, int $count, array $attributes = []): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = MixedParticipant::factory()->create(['event_id' => $event->id] + $attributes)->id;
        }

        return $ids;
    }

    /** @return array{groups: array<int, list<int>>, sitters: list<int>, matches: list<array<string, mixed>>} */
    private function dump(MixedRound $round): array
    {
        $groups = [];
        $sitters = [];
        foreach (MixedRoundPlayer::query()->where('round_id', $round->id)->orderBy('id')->get() as $row) {
            if ($row->group_no === null) {
                $sitters[] = (int) $row->participant_id;
            } else {
                $groups[(int) $row->group_no][] = (int) $row->participant_id;
            }
        }
        ksort($groups);

        $matches = MixedMatch::query()->where('round_id', $round->id)->orderBy('group_no')->orderBy('match_no')->orderBy('id')->get()
            ->map(fn (MixedMatch $m): array => [
                'group' => (int) $m->group_no, 'match_no' => (int) $m->match_no,
                'a' => [(int) $m->team_a_p1, (int) $m->team_a_p2], 'b' => [(int) $m->team_b_p1, (int) $m->team_b_p2],
                'is_extra' => (bool) $m->is_extra,
            ])
            ->all();

        return ['groups' => $groups, 'sitters' => $sitters, 'matches' => $matches];
    }

    /**
     * Same dump with participant ids replaced by their 1-based creation
     * ordinal, so snapshots do not depend on database auto-increment values.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function normalised(MixedRound $round, array $ids): array
    {
        $ordinal = array_flip($ids);
        $map = fn (int $id): int => $ordinal[$id] + 1;
        $dump = $this->dump($round);

        return [
            'groups' => array_map(fn (array $g): array => array_map($map, $g), $dump['groups']),
            'sitters' => array_map($map, $dump['sitters']),
            'matches' => array_map(fn (array $m): array => [
                'group' => $m['group'], 'match_no' => $m['match_no'],
                'a' => array_map($map, $m['a']), 'b' => array_map($map, $m['b']),
                'is_extra' => $m['is_extra'],
            ], $dump['matches']),
        ];
    }

    /**
     * Compares with tests/Fixtures/mixed/snapshots/<name>.json. A missing or
     * differing snapshot FAILS; it is only (re)written when $update is true,
     * which defaults to the UPDATE_MIXED_SNAPSHOTS environment flag.
     *
     * @param  array<string, mixed>  $actual
     */
    private function assertSnapshot(string $name, array $actual, ?bool $update = null, ?string $dir = null): void
    {
        $update ??= getenv('UPDATE_MIXED_SNAPSHOTS') === '1';
        $dir ??= self::SNAPSHOT_DIR;
        $file = $dir.'/'.$name.'.json';
        $json = json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

        if ($update) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($file, $json);

            return;
        }

        if (! is_file($file)) {
            $this->fail("Snapshot {$name}.json is missing; review the output and create it with UPDATE_MIXED_SNAPSHOTS=1.");
        }

        $this->assertSame(file_get_contents($file), $json, "Snapshot {$name}.json differs: the seeded random protocol changed.");
    }

    private function assertRule(string $key, callable $call): void
    {
        try {
            $call();
        } catch (MixedRuleException $e) {
            $this->assertSame($key, $e->key);

            return;
        }

        $this->fail("MixedRuleException {$key} expected");
    }

    /**
     * Confirms, scores (cycling results, so the standings differ) and completes a round.
     */
    private function finishRound(MixedRound $round): void
    {
        $results = ['4:0', '3:1', '2:2', '1:3', '0:4'];
        $this->rounds()->confirm($round);
        foreach (MixedMatch::query()->where('round_id', $round->id)->orderBy('id')->pluck('id') as $i => $id) {
            $this->rounds()->recordScore($round, (int) $id, $results[$i % 5]);
        }
        $this->rounds()->complete($round);
    }

    #[Test]
    public function failed_generation_leaves_no_round_and_no_rows(): void
    {
        $this->seedRandomizer(1);
        $event = MixedEvent::factory()->create(['mode' => 'americano']);
        $ids = $this->participants($event, 9);

        $this->assertRule('extra_group_impossible', fn () => $this->generator()->createAndPropose($event, '4_only', 'random', $ids));

        $this->assertSame(0, MixedRound::query()->count());
        $this->assertSame(0, MixedRoundPlayer::query()->count());
        $this->assertSame(0, MixedMatch::query()->count());
    }

    #[Test]
    public function fewer_than_four_selected_is_refused_without_a_round(): void
    {
        $this->seedRandomizer(1);
        $event = MixedEvent::factory()->create(['mode' => 'americano']);
        $ids = $this->participants($event, 6);

        $this->assertRule('min_four', fn () => $this->generator()->createAndPropose($event, '4_only', 'random', array_slice($ids, 0, 3)));
        // ids of other events or unknown ids do not count
        $this->assertRule('min_four', fn () => $this->generator()->createAndPropose($event, '4_only', 'random', [...array_slice($ids, 0, 3), 999999]));

        $this->assertSame(0, MixedRound::query()->count());
        $this->assertSame('Mindestens 4 Teilnehmer auswählen.', __('mixed::messages.min_four', [], 'de'));
        $this->assertStringContainsString('Zusatzgruppe', __('mixed::messages.extra_group_impossible', [], 'de'));
    }

    #[Test]
    public function regenerate_replaces_a_proposal_and_refuses_other_states(): void
    {
        $this->seedRandomizer(3);
        $event = MixedEvent::factory()->create(['mode' => 'americano']);
        $ids = $this->participants($event, 8);

        $round = $this->generator()->createAndPropose($event, '4_only', 'random', $ids);
        $this->generator()->regenerate($round, array_slice($ids, 0, 4));

        $dump = $this->dump($round);
        $this->assertCount(1, $dump['groups']);
        $this->assertSame(4, MixedRoundPlayer::query()->where('round_id', $round->id)->count());

        $this->rounds()->confirm($round);
        $this->assertRule('round_state', fn () => $this->generator()->regenerate($round, $ids));
        $this->assertSame(4, MixedRoundPlayer::query()->where('round_id', $round->id)->count());
    }

    #[Test]
    public function failed_regenerate_keeps_the_old_proposal(): void
    {
        $this->seedRandomizer(3);
        $event = MixedEvent::factory()->create(['mode' => 'americano']);
        $ids = $this->participants($event, 8);
        $round = $this->generator()->createAndPropose($event, '4_only', 'random', $ids);
        $before = $this->dump($round);

        $this->assertRule('min_four', fn () => $this->generator()->regenerate($round, array_slice($ids, 0, 3)));
        $this->assertSame($before, $this->dump($round));

        // 9 selected with 4_only is impossible (see failed_generation_...): still the old rows
        $nine = [...$ids, ...$this->participants($event, 1)];
        $this->assertRule('extra_group_impossible', fn () => $this->generator()->regenerate($round, $nine));
        $this->assertSame($before, $this->dump($round));
    }

    #[Test]
    public function americano_invariants_hold_for_many_seeds(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'americano']);
        $ids = $this->participants($event, 13);

        for ($seed = 1; $seed <= 50; $seed++) {
            $this->seedRandomizer($seed);
            $round = $this->generator()->createAndPropose($event, '4_only', 'random', $ids);

            $dump = $this->dump($round);
            $this->assertCount(4, $dump['groups'], "seed {$seed}");
            $this->assertSame([], $dump['sitters']);

            $counts = array_count_values(array_merge(...array_values($dump['groups'])));
            $this->assertSame($ids, array_values(array_intersect($ids, array_keys($counts))), "seed {$seed}: every selected placed");

            $fillers = array_keys(array_filter($counts, fn (int $c): bool => $c === 2));
            $this->assertCount(3, $fillers, "seed {$seed}");
            $this->assertSame(16, array_sum($counts));

            foreach ($dump['matches'] as $m) {
                $this->assertSame($m['group'] === 3, $m['is_extra'], "seed {$seed}: only the last group is extra");
            }
            $this->assertCount(4, $dump['groups'][3]);
            // the extra group is last and its three fillers also sit in a normal group
            $this->assertCount(3, array_intersect($fillers, $dump['groups'][3]));

            $this->rounds()->delete($round);
        }
    }

    #[Test]
    public function no_extra_match_players_are_never_fillers(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'americano']);
        $ids = $this->participants($event, 13);
        $excluded = array_slice($ids, 0, 3);
        MixedParticipant::query()->whereIn('id', $excluded)->update(['no_extra_match' => 1]);

        for ($seed = 1; $seed <= 30; $seed++) {
            $this->seedRandomizer($seed);
            $round = $this->generator()->createAndPropose($event, '4_only', 'random', $ids);

            $dump = $this->dump($round);
            $counts = array_count_values(array_merge(...array_values($dump['groups'])));
            $fillers = array_keys(array_filter($counts, fn (int $c): bool => $c === 2));
            $this->assertCount(3, $fillers, "seed {$seed}");
            $this->assertSame([], array_values(array_intersect($fillers, $excluded)), "seed {$seed}");

            $this->rounds()->delete($round);
        }
    }

    #[Test]
    public function mexicano_first_round_is_random_then_by_standings(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'mexicano']);
        $ids = $this->participants($event, 12);

        // no completed round: a plain shuffle of the selected ids, cut into fours
        $this->seedRandomizer(7);
        $round1 = $this->generator()->createAndPropose($event, '4_only', 'random', $ids);
        $expected = array_chunk((new Randomizer(new Mt19937(7)))->shuffleArray($ids), 4);
        $this->assertSame($expected, array_values($this->dump($round1)['groups']));
        foreach ($this->dump($round1)['matches'] as $m) {
            $this->assertSame(0, $m['match_no']);
            $this->assertFalse($m['is_extra']);
        }

        $this->finishRound($round1);

        // 14 players, round 2: two sitters with the fewest counts, groups follow points_avg
        $more = $this->participants($event, 2);
        $all = [...$ids, ...$more];
        $this->seedRandomizer(11);
        $round2 = $this->generator()->createAndPropose($event, '4_only', 'random', $all);

        $dump = $this->dump($round2);
        $this->assertCount(2, $dump['sitters']);
        $this->assertCount(3, $dump['groups']);
        // nobody sat in round 1, every count is 0: sitters are any two, never twice a participant
        $this->assertCount(14, array_unique([...$dump['sitters'], ...array_merge(...array_values($dump['groups']))]));

        $points = [];
        foreach ($this->rounds()->standings($event) as $row) {
            $points[$row['id']] = $row['points_avg'];
        }
        $groupKeys = array_keys($dump['groups']);
        for ($g = 0; $g < count($groupKeys) - 1; $g++) {
            $lowerOfBetter = min(array_map(fn (int $id): float => (float) ($points[$id] ?? 0), $dump['groups'][$groupKeys[$g]]));
            $higherOfWorse = max(array_map(fn (int $id): float => (float) ($points[$id] ?? 0), $dump['groups'][$groupKeys[$g + 1]]));
            $this->assertGreaterThanOrEqual($higherOfWorse, $lowerOfBetter, 'group '.$g.' ranks above group '.($g + 1));
        }

        // round 2 gets confirmed and completed; round 3 must now favour the round 2 sitters NOT sitting again
        $this->finishRound($round2);
        $counts = $this->rounds()->sitOutCounts($event);
        $this->seedRandomizer(5);
        $round3 = $this->generator()->createAndPropose($event, '4_only', 'random', $all);
        $sitters3 = $this->dump($round3)['sitters'];
        $this->assertCount(2, $sitters3);
        foreach ($sitters3 as $id) {
            $this->assertSame(0, $counts[$id], 'sitters with the fewest counts');
        }
    }

    #[Test]
    public function seeded_snapshot_americano(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'americano']);
        $ids = [];
        for ($i = 0; $i < 13; $i++) {
            $ids[] = MixedParticipant::factory()->create([
                'event_id' => $event->id,
                'gender' => $i % 3 === 0 ? 'f' : 'm',
                'itn' => 4.0 + $i * 0.5,
                'age' => 25 + $i * 3,
            ])->id;
        }

        // history: one confirmed round with two groups of four
        $past = DB::transaction(fn (): MixedRound => $this->rounds()->createRound($event, '4_only', 'random'));
        $groups = [array_slice($ids, 0, 4), array_slice($ids, 4, 4)];
        $matches = [];
        foreach ($groups as $g => $m) {
            foreach ([[[0, 1], [2, 3]], [[0, 2], [1, 3]], [[0, 3], [1, 2]]] as $no => [$a, $b]) {
                $matches[] = ['group' => $g, 'match_no' => $no, 'team_a' => [$m[$a[0]], $m[$a[1]]], 'team_b' => [$m[$b[0]], $m[$b[1]]], 'is_extra' => false];
            }
        }
        $this->rounds()->persistProposal($past, $groups, $matches);
        $this->rounds()->confirm($past);

        MixedAvoidPair::query()->create(['event_id' => $event->id, 'participant_a' => $ids[0], 'participant_b' => $ids[5]]);

        $this->seedRandomizer(42);
        $round = $this->generator()->createAndPropose($event, '4_only', 'itn_balanced', $ids);

        $this->assertSnapshot('americano-seed42', $this->normalised($round, $ids));
    }

    #[Test]
    public function seeded_snapshot_mexicano(): void
    {
        $event = MixedEvent::factory()->create(['mode' => 'mexicano']);
        $ids = $this->participants($event, 14);

        $this->seedRandomizer(1);
        $round1 = $this->generator()->createAndPropose($event, '4_only', 'random', $ids);
        $this->finishRound($round1);

        $this->seedRandomizer(42);
        $round2 = $this->generator()->createAndPropose($event, '4_only', 'random', $ids);

        $this->assertSnapshot('mexicano-seed42', $this->normalised($round2, $ids));
    }

    #[Test]
    public function snapshots_are_never_rewritten_without_the_env_flag(): void
    {
        $dir = sys_get_temp_dir().'/mixed-snap-'.bin2hex(random_bytes(4));
        mkdir($dir);

        try {
            // missing file and no flag: fails and creates nothing
            try {
                $this->assertSnapshot('x', ['a' => 1], false, $dir);
                $this->fail('A missing snapshot must fail without the flag.');
            } catch (AssertionFailedError $e) {
                $this->assertStringContainsString('missing', $e->getMessage());
            }
            $this->assertFileDoesNotExist($dir.'/x.json');

            // the DEFAULT path (no explicit $update) must read the env flag: unset means "do not write"
            $original = getenv('UPDATE_MIXED_SNAPSHOTS');
            putenv('UPDATE_MIXED_SNAPSHOTS');
            try {
                try {
                    $this->assertSnapshot('y', ['a' => 1], null, $dir);
                    $this->fail('unreachable: default path must not write');
                } catch (AssertionFailedError $e) {
                    $this->assertStringContainsString('missing', $e->getMessage());
                }
                $this->assertFileDoesNotExist($dir.'/y.json');
            } finally {
                if ($original === false) {
                    putenv('UPDATE_MIXED_SNAPSHOTS');
                } else {
                    putenv('UPDATE_MIXED_SNAPSHOTS='.$original);
                }
            }

            // with the flag it is written ...
            $this->assertSnapshot('x', ['a' => 1], true, $dir);
            $this->assertFileExists($dir.'/x.json');
            $before = file_get_contents($dir.'/x.json');

            // ... and a differing result without the flag fails and leaves the file untouched
            try {
                $this->assertSnapshot('x', ['a' => 2], false, $dir);
                $this->fail('A differing snapshot must fail without the flag.');
            } catch (AssertionFailedError $e) {
                $this->assertStringContainsString('differs', $e->getMessage());
            }
            $this->assertSame($before, file_get_contents($dir.'/x.json'));

            // the default is "no flag": the committed snapshots exist and the helper reads the real env
            $this->assertFileExists(self::SNAPSHOT_DIR.'/americano-seed42.json');
            $this->assertFileExists(self::SNAPSHOT_DIR.'/mexicano-seed42.json');
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            rmdir($dir);
        }
    }

    #[Test]
    public function randomness_comes_only_from_the_container(): void
    {
        $offenders = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules/MixedTournament')));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = '';
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (is_array($token)) {
                    if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $code .= $token[1];
                } else {
                    $code .= $token;
                }
            }

            if (preg_match_all('/(?<![$>\w])(shuffle|mt_rand|rand|random_int|array_rand|str_shuffle)\(/', $code, $m)) {
                $offenders[$file->getPathname()] = $m[0];
            }
        }

        $this->assertSame([], $offenders);
    }
}
