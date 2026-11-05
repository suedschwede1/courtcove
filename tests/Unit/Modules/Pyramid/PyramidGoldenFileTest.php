<?php

namespace Tests\Unit\Modules\Pyramid;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PyramidGoldenFileTest extends TestCase
{
    private function golden(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 4).'/tests/Fixtures/pyramid/golden.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function golden_file_carries_provenance(): void
    {
        $g = $this->golden();
        $this->assertSame('6b780d3324936bab3749237183c2dab161873612', $g['meta']['old_repo_commit']);
        $this->assertStringStartsWith('8.4.', $g['meta']['php_version']);
        $this->assertSame(['meta', 'rows', 'challengeable', 'shift_win', 'shift_entry', 'pyramid_rows', 'seed'], array_keys($g));
        $this->assertCount(28 * 31, $g['challengeable']);
        $this->assertSame(['Apfel', 'Zebra', 'Äpfel', 'Öl'], array_column($g['seed'][1]['result'], 'label')); // byte order
    }
}
