<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Mixed\DeterministicCallables;

class GoldenFileTest extends TestCase
{
    #[Test]
    public function golden_file_carries_provenance(): void
    {
        $g = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/tests/Fixtures/mixed/golden.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('6b780d3324936bab3749237183c2dab161873612', $g['meta']['old_repo_commit']);
        $this->assertStringStartsWith('8.4.', $g['meta']['php_version']);
        foreach (['rotation', 'group_sizes', 'initial_assignment', 'form_extra_group', 'penalty', 'repair', 'propose', 'history', 'mixing_score',
            'mexicano_rank', 'mexicano_sitters', 'mexicano_propose', 'mexicano_pair', 'standings', 'waves', 'mexicano_slots', 'wave_count', 'scorecard_template'] as $k) {
            $this->assertNotEmpty($g[$k], $k);
        }
    }

    #[Test]
    public function deterministic_callables_match_the_old_test_helper(): void
    {
        $r = DeterministicCallables::randomInt([0, 1, 0, 1, 1, 0]);
        $this->assertSame([0, 1, 0], [$r(0, 1), $r(0, 1), $r(0, 2)]);
        $this->assertSame([[0, 1], [0, 1], [0, 2]], DeterministicCallables::calls());
        $this->assertSame([3, 1, 2], DeterministicCallables::shuffle('reverse')([2, 1, 3]));
        $this->assertSame([2, 3, 1], DeterministicCallables::shuffle('rotate1')([1, 2, 3]));
    }
}
