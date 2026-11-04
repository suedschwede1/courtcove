<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GoldenVectorsFileTest extends TestCase
{
    #[Test]
    public function vectors_carry_provenance(): void
    {
        $v = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/draw/vectors.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $v['meta']['old_repo_commit']);
        $this->assertStringStartsWith('8.4.', $v['meta']['php_version']);
        $this->assertSame(['runphp-strings', 'n2-int', 'n3-int', 'n16-int', 'n24-int', 'mixed-int-string', 'numeric-strings',
            'unicode-slash-rule', 'emoji-rule', 'line-separator-rule', 'numeric-slots', 'token-ac6', 'beacon-non-hex'],
            array_column($v['draw'], 'name'));
        $this->assertCount(13, glob(dirname(__DIR__, 3).'/Fixtures/draw/synthetic-records/*.json'));
    }

    #[Test]
    public function vectors_have_every_section(): void
    {
        $v = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/draw/vectors.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(
            ['meta', 'draw', 'shuffle', 'errors', 'token_names', 'token_payload', 'assign_slots', 'fallback',
                'parse_lines', 'score_evaluate', 'score_describe'],
            array_keys($v)
        );
    }
}
