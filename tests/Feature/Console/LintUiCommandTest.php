<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LintUiCommandTest extends TestCase
{
    #[Test]
    public function it_fails_when_findings_exceed_the_limit(): void
    {
        $this->artisan('lint:ui --summary --fail-over=-1')
            ->expectsOutputToContain('warnings in')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_succeeds_when_findings_are_within_the_limit(): void
    {
        $this->artisan('lint:ui --summary --fail-over=1000000')
            ->expectsOutputToContain('warnings in')
            ->assertExitCode(0);
    }
}
