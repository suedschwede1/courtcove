<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Services\DrawService;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LiveRecordFixturesTest extends TestCase
{
    private const KNOWN_KEYS = [
        'title', 'status', 'payload', 'commitment', 'beacon_source', 'created', 'discarded', 'discarded_reason',
        'server_seed', 'beacon_value', 'final_seed', 'assignment', 'executed',
    ];

    /** @return list<string> */
    private function records(): array
    {
        $files = glob(base_path('tests/Fixtures/draw/records/*.json')) ?: [];

        if ($files === []) {
            $this->markTestSkipped('no live records captured yet');
        }

        return $files;
    }

    #[Test]
    public function live_records_recompute(): void
    {
        $draws = new DrawService;

        foreach ($this->records() as $file) {
            $name = basename($file);
            $record = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

            $this->assertArrayHasKey('server_seed', $record, $name);
            $this->assertSame([], array_diff(array_keys($record), self::KNOWN_KEYS), "{$name}: unknown keys");
            $this->assertTrue($draws->isCanonical($draws->canonical($record['payload'])), $name);
            $this->assertSame($record['commitment'], $draws->commit($record['payload'], $record['server_seed']), "{$name}: commitment");
            $this->assertSame($record['final_seed'], $draws->finalSeed($record['server_seed'], $record['beacon_value']), "{$name}: final_seed");
            $this->assertSame(
                $record['assignment'],
                $draws->execute($record['payload'], $record['server_seed'], $record['beacon_value'])['assignment'],
                "{$name}: assignment"
            );
        }
    }

    #[Test]
    public function python_verifier_accepts_live_records(): void
    {
        $files = $this->records();
        $python = (new ExecutableFinder)->find('python3');
        if ($python === null) {
            $this->markTestSkipped('python3 not found');
        }

        foreach ($files as $file) {
            $process = new Process([$python, base_path('tools/verify_draw.py'), $file]);
            $process->run();
            $this->assertSame(0, $process->getExitCode(), basename($file)."\n".$process->getOutput());
        }
    }
}
