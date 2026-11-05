<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Services\DrawService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DrawGoldenVectorsTest extends TestCase
{
    private function vectors(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/draw/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function draw_vectors_are_reproduced_byte_for_byte(): void
    {
        $s = new DrawService;
        $cases = $this->vectors()['draw'];
        $this->assertNotEmpty($cases);

        foreach ($cases as $c) {
            $n = $c['name'];
            $this->assertSame($c['canonical'], $s->canonical($c['payload']), "$n canonical");
            $this->assertSame($c['commitment'], $s->commit($c['payload'], $c['server_seed']), "$n commitment");
            $this->assertTrue($s->verifyCommitment($c['payload'], $c['server_seed'], $c['commitment']), "$n verify");
            $this->assertSame($c['final_seed'], $s->finalSeed($c['server_seed'], $c['beacon_value']), "$n final_seed");

            $r = $s->execute($c['payload'], $c['server_seed'], $c['beacon_value']);
            $this->assertSame($c['final_seed'], $r['final_seed'], "$n execute final_seed");
            $this->assertSame($c['shuffled'], $r['shuffled'], "$n shuffled");
            $this->assertSame($c['assignment'], $r['assignment'], "$n assignment");
            $this->assertSame(
                $c['assignment_json'],
                json_encode($r['assignment'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                "$n assignment_json"
            );
        }
    }

    #[Test]
    public function shuffle_vectors_are_reproduced(): void
    {
        $s = new DrawService;
        $cases = $this->vectors()['shuffle'];
        $this->assertNotEmpty($cases);

        foreach ($cases as $i => $c) {
            $this->assertSame($c['result'], $s->shuffle($c['items'], $c['seed']), "shuffle #$i");
        }
    }

    #[Test]
    public function parse_lines_vectors_are_reproduced(): void
    {
        $s = new DrawService;
        $cases = $this->vectors()['parse_lines'];
        $this->assertNotEmpty($cases);

        foreach ($cases as $i => $c) {
            $this->assertSame($c['result'], $s->parseLines($c['text']), "parse_lines #$i");
        }
    }

    #[Test]
    public function error_vectors_throw_the_same_exception_and_message(): void
    {
        $s = new DrawService;
        $cases = $this->vectors()['errors'];
        $this->assertCount(6, $cases);

        foreach ($cases as $i => $c) {
            $a = $c['args'];
            $thrown = null;
            try {
                match ($c['call']) {
                    'execute' => $s->execute($a['payload'], $a['server_seed'], $a['beacon_value']),
                    'commit' => $s->commit($a['payload'], $a['server_seed']),
                    'finalSeed' => $s->finalSeed($a['server_seed'], $a['beacon_value']),
                };
            } catch (\Throwable $e) {
                $thrown = $e;
            }
            $this->assertNotNull($thrown, "errors #$i did not throw");
            $this->assertSame($c['class'], $thrown::class, "errors #$i class");
            $this->assertSame($c['message'], $thrown->getMessage(), "errors #$i message");
        }
    }
}
