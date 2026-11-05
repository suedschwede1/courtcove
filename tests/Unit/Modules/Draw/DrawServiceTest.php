<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Services\DrawService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DrawServiceTest extends TestCase
{
    private function payload(): array
    {
        return ['entries' => ['Q1', 'Q2', 'Q3'], 'slots' => ['KO_A:3', 'KO_A:5', 'KO_A:7'], 'rule' => 'test'];
    }

    #[Test]
    public function commitment_is_reproducible(): void
    {
        $s = new DrawService;
        $seed = str_repeat('ab', 32);
        $commitment = $s->commit($this->payload(), $seed);

        $this->assertTrue($s->verifyCommitment($this->payload(), $seed, $commitment));
        $this->assertFalse($s->verifyCommitment(['rule' => 'other'] + $this->payload(), $seed, $commitment));
    }

    #[Test]
    public function string_lots_are_drawn_deterministically(): void
    {
        $s = new DrawService;
        $seed = str_repeat('ab', 32);
        $beacon = str_repeat('cd', 32);

        $first = $s->execute($this->payload(), $seed, $beacon);
        $this->assertSame($first, $s->execute($this->payload(), $seed, $beacon));
        $this->assertCount(3, $first['assignment']);
    }

    #[Test]
    public function canonical_keeps_php_escaping_of_line_separators(): void
    {
        $this->assertSame('{"r":"a'.'\u2028'.'b/ä"}', (new DrawService)->canonical(['r' => "a\u{2028}b/ä"]));
    }

    #[Test]
    public function canonical_throws_on_invalid_utf8(): void
    {
        $this->expectException(\JsonException::class);
        (new DrawService)->canonical(['r' => "\xff"]);
    }

    #[Test]
    public function server_seed_is_64_lowercase_hex(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (new DrawService)->generateServerSeed());
    }
}
