<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Services\GroupingService;
use App\Modules\MixedTournament\Services\HistoryService;
use App\Modules\MixedTournament\Services\RotationService;
use App\Modules\MixedTournament\Services\RoundGenerationService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\Mixed\DeterministicCallables;
use Throwable;

class RoundGenerationServiceTest extends TestCase
{
    private function service(): RoundGenerationService
    {
        $rotation = new RotationService;

        return new RoundGenerationService(new GroupingService($rotation, new HistoryService), $rotation);
    }

    /** @return array<string, mixed> */
    private function golden(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 4).'/tests/Fixtures/mixed/golden.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<array{id: int, gender: string, itn: float}> */
    private function participants(int $n): array
    {
        $out = [];
        for ($id = 1; $id <= $n; $id++) {
            $out[] = ['id' => $id, 'gender' => $id % 2 === 0 ? 'm' : 'f', 'itn' => 5.0];
        }

        return $out;
    }

    /** @return array{partners: array<string, int>, opponents: array<string, int>} */
    private function emptyHistory(): array
    {
        return ['partners' => [], 'opponents' => []];
    }

    #[Test]
    public function golden_propose(): void
    {
        $cases = $this->golden()['propose'];
        $this->assertNotEmpty($cases);
        foreach ($cases as $c) {
            $a = $c['args'];
            $randomInt = isset($a['random_int']['constant'])
                ? DeterministicCallables::constantMin()
                : DeterministicCallables::randomInt($a['random_int']['sequence']);
            $run = fn () => $this->service()->propose(
                $a['participants'], $a['group_size_mode'], $a['distribution'], $a['history'], $a['avoid_pairs'],
                DeterministicCallables::shuffle($a['shuffle']), $randomInt, $a['excluded_filler_ids'],
            );

            if (isset($c['error'])) {
                try {
                    $run();
                    $this->fail($c['name'].': expected '.$c['error']['class']);
                } catch (Throwable $e) {
                    $this->assertSame($c['error']['class'], $e::class, $c['name']);
                    $this->assertSame($c['error']['message'], $e->getMessage(), $c['name']);
                }

                continue;
            }
            $this->assertSame($c['result'], $run(), $c['name']);
        }
    }

    /** @return array{groups: list<list<int>>, matches: list<array<string, mixed>>} */
    private function thirteen(): array
    {
        return $this->service()->propose(
            $this->participants(13), '4_only', 'random', $this->emptyHistory(), [],
            DeterministicCallables::shuffle('identity'), DeterministicCallables::constantMin(),
        );
    }

    #[Test]
    public function thirteen_gives_three_groups_plus_extra(): void
    {
        $p = $this->thirteen();
        $this->assertCount(4, $p['groups']);
        $this->assertCount(4, $p['groups'][3]);
        $this->assertContains(13, $p['groups'][3]);
    }

    #[Test]
    public function extra_group_matches_are_flagged(): void
    {
        $p = $this->thirteen();
        $regular = array_filter($p['matches'], fn ($m) => ! $m['is_extra']);
        $extra = array_filter($p['matches'], fn ($m) => $m['is_extra']);
        $this->assertCount(9, $regular);
        $this->assertCount(3, $extra);
        foreach ($extra as $m) {
            $this->assertSame(3, $m['group']);
        }
    }

    #[Test]
    public function leftover_plays_three_filler_plays_six(): void
    {
        $p = $this->thirteen();
        $occurrences = [];
        foreach ($p['matches'] as $m) {
            foreach (array_merge($m['team_a'], $m['team_b']) as $id) {
                $occurrences[$id] = ($occurrences[$id] ?? 0) + 1;
            }
        }
        $this->assertSame(3, $occurrences[13]);
        $this->assertSame(6, $occurrences[$p['groups'][3][1]]);
    }

    #[Test]
    public function too_few_groups_for_the_extra_group_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->propose(
            $this->participants(9), '4_only', 'random', $this->emptyHistory(), [],
            DeterministicCallables::shuffle('identity'), DeterministicCallables::constantMin(),
        );
    }

    #[Test]
    public function invariants_hold_over_200_seeds(): void
    {
        $grouping = new GroupingService(new RotationService, new HistoryService);
        $rotation = new RotationService;
        $service = new RoundGenerationService($grouping, $rotation);
        $checked = 0;
        $skipped = 0;

        for ($seed = 1; $seed <= 200; $seed++) {
            $r = new Randomizer(new Mt19937($seed));
            $shuffle = fn (array $a): array => $r->shuffleArray($a);
            $randomInt = fn (int $a, int $b): int => $r->getInt($a, $b);

            for ($n = 4; $n <= 24; $n++) {
                $participants = [];
                for ($id = 1; $id <= $n; $id++) {
                    $participants[] = [
                        'id' => $id,
                        'gender' => $id % 3 === 0 ? 'm' : 'f',
                        'itn' => $id % 5 === 0 ? null : (float) (($id * 7) % 11),
                        'age' => $id % 4 === 0 ? null : 20 + (($id * 13) % 40),
                    ];
                }
                $excluded = $seed % 2 === 0 ? array_values(array_filter(range(1, $n), fn (int $id) => $id % 3 === 0)) : [];
                $avoid = $seed % 10 === 0 ? [['a' => 1, 'b' => 2]] : [];

                foreach (['4_only', '4_and_5'] as $mode) {
                    foreach (['random', 'itn', 'itn_balanced', 'age', 'ladies_apart'] as $distribution) {
                        $sizing = $grouping->groupSizes($n, $mode);
                        try {
                            $p = $service->propose($participants, $mode, $distribution, $this->emptyHistory(), $avoid, $shuffle, $randomInt, $excluded);
                        } catch (InvalidArgumentException $e) {
                            $this->assertSame('not enough eligible groups to fill the extra group without doubling a source group', $e->getMessage());
                            $this->assertGreaterThan(0, $sizing['leftover']);
                            $skipped++;

                            continue;
                        }
                        $checked++;
                        $ctx = "seed $seed n $n $mode $distribution";
                        $groups = $p['groups'];
                        $hasExtra = $sizing['leftover'] > 0;
                        $regular = $hasExtra ? array_slice($groups, 0, -1) : $groups;

                        $this->assertSame($sizing['sizes'], array_map('count', $regular), $ctx);
                        $regularIds = array_merge(...$regular);
                        $this->assertSame($regularIds, array_values(array_unique($regularIds)), "$ctx: regular groups disjoint");
                        $all = $regularIds;

                        if ($hasExtra) {
                            $extra = $groups[count($groups) - 1];
                            $this->assertCount(4, $extra, $ctx);
                            $fillers = array_values(array_intersect($extra, $regularIds));
                            $leftover = array_values(array_diff($extra, $regularIds));
                            $this->assertCount($sizing['leftover'], $leftover, "$ctx: leftover only in extra");
                            $this->assertCount(4 - $sizing['leftover'], $fillers, $ctx);
                            $sources = [];
                            foreach ($fillers as $f) {
                                $this->assertNotContains($f, $excluded, "$ctx: filler not excluded");
                                foreach ($regular as $gi => $g) {
                                    if (in_array($f, $g, true)) {
                                        $sources[] = $gi;
                                    }
                                }
                            }
                            $this->assertSame($sources, array_values(array_unique($sources)), "$ctx: fillers from distinct groups");
                            $all = array_merge($all, $leftover);
                        }
                        $sortedAll = $all;
                        sort($sortedAll);
                        $this->assertSame(range(1, $n), $sortedAll, "$ctx: everyone placed exactly once");

                        $expected = [];
                        foreach ($groups as $gi => $group) {
                            foreach ($rotation->matches(count($group)) as $mi => $m) {
                                $expected[] = [
                                    'group' => $gi, 'match_no' => $mi,
                                    'team_a' => [$group[$m['teamA'][0]], $group[$m['teamA'][1]]],
                                    'team_b' => [$group[$m['teamB'][0]], $group[$m['teamB'][1]]],
                                    'is_extra' => $hasExtra && $gi === count($groups) - 1,
                                ];
                            }
                        }
                        $this->assertSame($expected, $p['matches'], "$ctx: matches follow the rotation");
                    }
                }
            }
        }

        $this->assertGreaterThan(10000, $checked);
        $this->assertGreaterThan(0, $skipped);
    }
}
