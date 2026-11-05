<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Services\GroupingService;
use App\Modules\MixedTournament\Services\HistoryService;
use App\Modules\MixedTournament\Services\RotationService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Mixed\DeterministicCallables;
use Throwable;

class GroupingServiceTest extends TestCase
{
    private function grouping(): GroupingService
    {
        return new GroupingService(new RotationService, new HistoryService);
    }

    /** @return array<string, mixed> */
    private function golden(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 4).'/tests/Fixtures/mixed/golden.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param  array<string, mixed>  $case */
    private function assertCase(array $case, callable $run): void
    {
        if (isset($case['error'])) {
            try {
                $run();
                $this->fail($case['name'].': expected '.$case['error']['class']);
            } catch (Throwable $e) {
                $this->assertSame($case['error']['class'], $e::class, $case['name']);
                $this->assertSame($case['error']['message'], $e->getMessage(), $case['name']);
            }

            return;
        }
        $this->assertSame($case['result'], $run(), $case['name']);
    }

    /** @return list<array{id: int, gender: string, itn: float}> */
    private function participants8(): array
    {
        return [
            ['id' => 1, 'gender' => 'f', 'itn' => 5.0],
            ['id' => 2, 'gender' => 'm', 'itn' => 4.0],
            ['id' => 3, 'gender' => 'f', 'itn' => 3.0],
            ['id' => 4, 'gender' => 'm', 'itn' => 2.0],
            ['id' => 5, 'gender' => 'f', 'itn' => 6.0],
            ['id' => 6, 'gender' => 'm', 'itn' => 1.0],
            ['id' => 7, 'gender' => 'f', 'itn' => 7.0],
            ['id' => 8, 'gender' => 'm', 'itn' => 8.0],
        ];
    }

    private function identity(): callable
    {
        return DeterministicCallables::shuffle('identity');
    }

    #[Test]
    public function golden_group_sizes(): void
    {
        $cases = $this->golden()['group_sizes'];
        $this->assertNotEmpty($cases);
        foreach ($cases as $c) {
            $this->assertCase($c, fn () => $this->grouping()->groupSizes($c['args']['count'], $c['args']['mode']));
        }
    }

    #[Test]
    public function golden_initial_assignment(): void
    {
        $cases = $this->golden()['initial_assignment'];
        $this->assertNotEmpty($cases);
        foreach ($cases as $c) {
            $a = $c['args'];
            $this->assertCase($c, fn () => $this->grouping()->initialAssignment($a['participants'], $a['sizes'], $a['mode'], DeterministicCallables::shuffle($a['shuffle'])));
        }
    }

    #[Test]
    public function golden_form_extra_group(): void
    {
        $cases = $this->golden()['form_extra_group'];
        $this->assertNotEmpty($cases);
        foreach ($cases as $c) {
            $a = $c['args'];
            $this->assertCase($c, fn () => $this->grouping()->formExtraGroup($a['groups'], $a['leftover_ids'], $a['excluded_filler_ids']));
        }
    }

    #[Test]
    public function golden_penalty(): void
    {
        $cases = $this->golden()['penalty'];
        $this->assertNotEmpty($cases);
        foreach ($cases as $c) {
            $a = $c['args'];
            $this->assertCase($c, fn () => $this->grouping()->penalty($a['groups'], $a['history'], $a['avoid_pairs']));
        }
    }

    #[Test]
    public function golden_repair(): void
    {
        $cases = $this->golden()['repair'];
        $this->assertNotEmpty($cases);
        foreach ($cases as $c) {
            $a = $c['args'];
            $this->assertCase($c, fn () => $this->grouping()->repair(
                $a['groups'], $a['history'], $a['avoid_pairs'],
                isset($a['random_int']['constant']) ? DeterministicCallables::constantMin() : DeterministicCallables::randomInt($a['random_int']['sequence']),
                $a['max_iterations'],
            ));
        }
    }

    #[Test]
    public function twelve_four_only(): void
    {
        $this->assertSame(['sizes' => [4, 4, 4], 'leftover' => 0], $this->grouping()->groupSizes(12, '4_only'));
    }

    #[Test]
    public function thirteen_four_only_leaves_one(): void
    {
        $this->assertSame(['sizes' => [4, 4, 4], 'leftover' => 1], $this->grouping()->groupSizes(13, '4_only'));
    }

    #[Test]
    public function fourteen_mixed_is_5_5_4(): void
    {
        $r = $this->grouping()->groupSizes(14, '4_and_5');
        rsort($r['sizes']);
        $this->assertSame(['sizes' => [5, 5, 4], 'leftover' => 0], $r);
    }

    #[Test]
    public function thirteen_mixed_is_5_4_4(): void
    {
        $r = $this->grouping()->groupSizes(13, '4_and_5');
        rsort($r['sizes']);
        $this->assertSame(['sizes' => [5, 4, 4], 'leftover' => 0], $r);
    }

    #[Test]
    public function fewer_than_four_participants_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 4 participants are needed');
        $this->grouping()->groupSizes(3, '4_only');
    }

    #[Test]
    public function itn_blocks(): void
    {
        $g = $this->grouping()->initialAssignment($this->participants8(), [4, 4], 'itn', $this->identity());
        $this->assertSame([[8, 7, 5, 1], [2, 3, 4, 6]], $g);
    }

    #[Test]
    public function itn_balanced_snake(): void
    {
        $g = $this->grouping()->initialAssignment($this->participants8(), [4, 4], 'itn_balanced', $this->identity());
        $this->assertSame([[8, 1, 2, 6], [7, 5, 3, 4]], $g);
    }

    #[Test]
    public function age_youngest_first_null_last(): void
    {
        $ages = [30, 45, 22, 60, 25, null, 33, 19];
        $participants = [];
        foreach ($ages as $i => $age) {
            $participants[] = ['id' => $i + 1, 'gender' => $i % 2 === 0 ? 'f' : 'm', 'itn' => null, 'age' => $age];
        }
        $g = $this->grouping()->initialAssignment($participants, [4, 4], 'age', $this->identity());
        $this->assertSame([[8, 3, 5, 1], [7, 2, 4, 6]], $g);
    }

    #[Test]
    public function ladies_apart_spreads_women(): void
    {
        $g = $this->grouping()->initialAssignment($this->participants8(), [4, 4], 'ladies_apart', $this->identity());
        $this->assertCount(2, array_intersect($g[0], [1, 3, 5, 7]));
        $this->assertCount(2, array_intersect($g[1], [1, 3, 5, 7]));
    }

    #[Test]
    public function extra_group_needs_distinct_source_groups(): void
    {
        $grouping = $this->grouping();
        try {
            $grouping->formExtraGroup([[1, 2, 3, 4], [5, 6, 7, 8]], [9]);
            $this->fail('expected exception');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('not enough eligible groups to fill the extra group without doubling a source group', $e->getMessage());
        }

        $groups = [[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12]];
        $extra = $grouping->formExtraGroup($groups, [13]);
        $this->assertCount(4, $extra['group']);
        $this->assertContains(13, $extra['group']);
        $this->assertCount(3, $extra['doubled']);
        $sources = [];
        foreach ($extra['doubled'] as $id) {
            foreach ($groups as $gi => $group) {
                if (in_array($id, $group, true)) {
                    $sources[] = $gi;
                }
            }
        }
        $this->assertSame($sources, array_values(array_unique($sources)));

        $one = $grouping->formExtraGroup([[1, 2, 3, 4]], [9, 10, 11]);
        $this->assertSame(['group' => [9, 10, 11, 1], 'doubled' => [1]], $one);
    }

    #[Test]
    public function penalty_prefers_separated_pairs(): void
    {
        $history = ['partners' => ['1:2' => 1], 'opponents' => []];
        $together = $this->grouping()->penalty([[1, 2, 3, 4]], $history, []);
        $apart = $this->grouping()->penalty([[1, 3, 5, 6], [2, 4, 7, 8]], $history, []);
        $this->assertGreaterThan($apart, $together);
    }

    #[Test]
    public function avoid_pair_costs_1000(): void
    {
        $empty = ['partners' => [], 'opponents' => []];
        // 1 and 2 face each other in two of the three matches of group [1,2,3,4]: 2 x 1000.
        $this->assertSame(2000, $this->grouping()->penalty([[1, 2, 3, 4]], $empty, [['a' => 1, 'b' => 2]]));
        $this->assertSame(2000, $this->grouping()->penalty([[1, 2, 3, 4]], $empty, [['a' => 2, 'b' => 1]]));
    }

    #[Test]
    public function repair_keeps_members_and_sizes(): void
    {
        $in = [[1, 2, 3, 4], [5, 6, 7, 8]];
        $out = $this->grouping()->repair($in, ['partners' => [], 'opponents' => []], [], DeterministicCallables::randomInt([0, 1, 0, 1, 1, 0]), 10);
        $ids = array_merge($out[0], $out[1]);
        sort($ids);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $ids);
        $this->assertCount(4, $out[0]);
        $this->assertCount(4, $out[1]);
    }

    #[Test]
    public function repair_consumes_random_ints_in_the_old_order(): void
    {
        $g = $this->grouping();
        $g->repair([[1, 2, 3, 4], [5, 6, 7, 8]], ['partners' => ['1:2' => 1], 'opponents' => []], [], DeterministicCallables::randomInt([0, 0, 1, 0, 1, 2]), 2);
        $this->assertSame([[0, 1], [0, 1], [0, 1], [0, 1], [0, 3], [0, 3]], DeterministicCallables::calls());
    }
}
