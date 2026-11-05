<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Modules\Draw\Models\Draw;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Phase2PreflightCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function unknown_module_exits_2(): void
    {
        $this->artisan('phase2:preflight', ['module' => 'nope'])
            ->expectsOutputToContain('Unknown module nope. Registered: draw')
            ->assertExitCode(2);
    }

    #[Test]
    public function draw_module_exit_codes(): void
    {
        Draw::factory()->executed()->create();
        $this->artisan('phase2:preflight', ['module' => 'draw'])->assertExitCode(0);

        Draw::factory()->executed()->create()->update(['final_seed' => str_repeat('1', 64)]);
        $this->artisan('phase2:preflight', ['module' => 'draw'])->assertExitCode(1);
    }

    #[Test]
    public function summary_line_is_printed(): void
    {
        $exit = Artisan::call('phase2:preflight', ['module' => 'draw']);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('[PASS] tables: ', $output);
        $this->assertMatchesRegularExpression('/^Preflight draw: \d+ PASS, 0 WARN, 0 FAIL, \d+ SKIP$/m', $output);
    }
}
