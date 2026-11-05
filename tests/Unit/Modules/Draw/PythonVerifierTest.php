<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PythonVerifierTest extends TestCase
{
    /** sha256 of tools/verify_draw.py (the old file with only the example URL in the usage comment neutralized). */
    private const VERIFIER_SHA256 = '4acb5e441140b03cfbfc9cd41728327ec65d213f05e791b413181f32fc989831';

    private const KNOWN_DIVERGENCES = ['line-separator-rule', 'numeric-slots'];

    private function python(): string
    {
        $python = (new ExecutableFinder)->find('python3');
        if ($python === null) {
            $this->markTestSkipped('python3 not found');
        }

        return $python;
    }

    /** @return array{0:int,1:string} */
    private function verify(string $record): array
    {
        $process = new Process([$this->python(), base_path('tools/verify_draw.py'), $record]);
        $process->run();

        return [$process->getExitCode(), $process->getOutput()];
    }

    private function record(string $name): string
    {
        return base_path('tests/Fixtures/draw/synthetic-records/'.$name.'.json');
    }

    #[Test]
    public function python_verifier_accepts_every_synthetic_record(): void
    {
        $this->python();
        $checked = 0;

        foreach (glob($this->record('*')) as $file) {
            if (in_array(basename($file, '.json'), self::KNOWN_DIVERGENCES, true)) {
                continue;
            }
            [$code, $out] = $this->verify($file);
            $this->assertSame(0, $code, basename($file)."\n".$out);
            $this->assertStringContainsString('Commitment  : OK', $out, basename($file));
            $this->assertStringContainsString('Zuteilung   : OK', $out, basename($file));
            $checked++;
        }

        $this->assertSame(11, $checked);
    }

    #[Test]
    public function python_verifier_reports_the_known_divergences(): void
    {
        $this->python();

        [$code, $out] = $this->verify($this->record('line-separator-rule'));
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Commitment  : ABWEICHUNG', $out);

        [$code, $out] = $this->verify($this->record('numeric-slots'));
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Zuteilung   : ABWEICHUNG', $out);
    }

    #[Test]
    public function copied_verifier_matches_the_old_one(): void
    {
        $new = base_path('tools/verify_draw.py');
        $this->assertSame(self::VERIFIER_SHA256, hash_file('sha256', $new));

        $old = '/mnt/c/development/booking/tools/verify_draw.py';
        if (! is_file($old)) {
            $this->markTestSkipped('old repository not present');
        }

        // Identical apart from the single neutralized example URL line in the docstring.
        $oldLines = explode("\n", file_get_contents($old));
        $newLines = explode("\n", file_get_contents($new));
        $this->assertSameSize($oldLines, $newLines);
        $differing = array_keys(array_diff_assoc($oldLines, $newLines));
        $this->assertSame([11], $differing);
    }
}
