<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Services\PayloadGuard;
use App\Modules\TournamentBase\Support\ActionRefused;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayloadGuardTest extends TestCase
{
    /** @return array<string, array<string, mixed>> */
    private function vectorCases(): array
    {
        $v = json_decode((string) file_get_contents(base_path('tests/Fixtures/draw/vectors.json')), true);

        return array_column($v['draw'], null, 'name');
    }

    /** @param array<string, mixed> $payload */
    private function refusalKey(array $payload): ?string
    {
        try {
            app(PayloadGuard::class)->assertVerifierSafe($payload);
        } catch (ActionRefused $e) {
            return $e->key;
        }

        return null;
    }

    #[Test]
    public function vectors_that_break_the_python_verifier_are_rejected(): void
    {
        $cases = $this->vectorCases();

        foreach (['line-separator-rule' => 'draw::admin.commit.line_separator', 'numeric-slots' => 'draw::admin.commit.numeric_slot'] as $name => $key) {
            try {
                app(PayloadGuard::class)->assertVerifierSafe($cases[$name]['payload']);
                $this->fail($name);
            } catch (ActionRefused $e) {
                $this->assertSame($key, $e->key);
            }
        }
    }

    #[Test]
    public function every_other_vector_payload_passes(): void
    {
        $cases = $this->vectorCases();
        unset($cases['line-separator-rule'], $cases['numeric-slots']);
        $this->assertGreaterThanOrEqual(10, count($cases));

        foreach ($cases as $name => $case) {
            $this->assertNull($this->refusalKey($case['payload']), $name);
        }
    }

    #[Test]
    public function invalid_utf8_is_rejected(): void
    {
        $base = ['entries' => [1, 2], 'slots' => ['A2', 'B2'], 'rule' => 'Regel'];

        $this->assertSame('draw::admin.commit.invalid_utf8', $this->refusalKey(['rule' => "Regel \xC3"] + $base));
        $this->assertSame('draw::admin.commit.invalid_utf8', $this->refusalKey(['slots' => ['A2', "B\xFF"]] + $base));
        $this->assertSame('draw::admin.commit.invalid_utf8', $this->refusalKey(['entries' => [1, "Q\xE2\x80"]] + $base));
    }

    #[Test]
    public function u2029_in_a_slot_is_rejected(): void
    {
        $base = ['entries' => [1, 2], 'slots' => ['A2', 'B2'], 'rule' => 'Regel'];

        $this->assertSame('draw::admin.commit.line_separator', $this->refusalKey(['slots' => ['A2', "B\u{2029}2"]] + $base));
        $this->assertSame('draw::admin.commit.line_separator', $this->refusalKey(['entries' => [1, "Q\u{2028}"]] + $base));
        $this->assertSame('draw::admin.commit.line_separator', $this->refusalKey(['rule' => "a\u{2029}b"] + $base));
    }

    #[Test]
    public function only_purely_numeric_slots_are_rejected(): void
    {
        $base = ['entries' => [1, 2], 'rule' => 'Regel'];

        $this->assertSame('draw::admin.commit.numeric_slot', $this->refusalKey(['slots' => ['A2', '7']] + $base));
        $this->assertSame('draw::admin.commit.numeric_slot', $this->refusalKey(['slots' => ['A2', '12']] + $base));
        $this->assertSame('draw::admin.commit.numeric_slot', $this->refusalKey(['slots' => ['305', 'B2']] + $base));
        $this->assertNull($this->refusalKey(['slots' => ['KO_A:1', 'S1']] + $base));
        $this->assertNull($this->refusalKey(['slots' => ['1A', 'A12']] + $base)); // partial digits are fine
    }
}
