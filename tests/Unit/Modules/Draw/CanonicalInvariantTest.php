<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Services\DrawService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CanonicalInvariantTest extends TestCase
{
    #[Test]
    public function every_golden_canonical_round_trips(): void
    {
        $s = new DrawService;
        $vectors = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/draw/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($vectors['draw']);

        foreach ($vectors['draw'] as $c) {
            $this->assertTrue($s->isCanonical($c['canonical']), $c['name']);
        }
    }

    #[Test]
    public function drift_is_detected(): void
    {
        $s = new DrawService;

        foreach ([
            '{"entries":{},"slots":[],"rule":"x"}',
            '{"entries":[1],"slots":["A\/2"],"rule":"x"}',
            '{"rule":"\u00e4"}',
            ' {"rule":"x"}',
            "{\n\"rule\":\"x\"}",
            '{"rule":"x"',
            '"x"',
            '[1]x',
        ] as $stored) {
            $this->assertFalse($s->isCanonical($stored), $stored);
        }

        $this->assertTrue($s->isCanonical('{"entries":["1",2],"slots":["A2","B2"],"rule":"a/b ä"}'));
    }
}
