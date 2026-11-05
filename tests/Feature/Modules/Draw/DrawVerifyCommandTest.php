<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrawVerifyCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function verifies_one_slug(): void
    {
        $draw = Draw::factory()->executed()->create();

        $this->artisan('draw:verify', ['slug' => $draw->slug])
            ->expectsOutputToContain("{$draw->slug} [executed/direct] OK")
            ->doesntExpectOutputToContain($draw->server_seed)
            ->doesntExpectOutputToContain($draw->final_seed)
            ->assertExitCode(0);
    }

    #[Test]
    public function all_reports_failures_with_exit_1(): void
    {
        Draw::factory()->create();
        $bad = Draw::factory()->executed()->create();
        $bad->update(['commitment' => str_repeat('0', 64)]);

        $this->artisan('draw:verify', ['--all' => true])
            ->expectsOutputToContain("{$bad->slug} [executed/direct] FAIL: commitment")
            ->expectsOutputToContain('not executed (canonical OK)')
            ->assertExitCode(1);
    }

    #[Test]
    public function requires_slug_or_all(): void
    {
        $this->artisan('draw:verify')->expectsOutputToContain('Pass a slug or --all.')->assertExitCode(2);
    }

    #[Test]
    public function unknown_slug_exits_1(): void
    {
        $this->artisan('draw:verify', ['slug' => 'nope'])->expectsOutputToContain('Draw nope not found.')->assertExitCode(1);
    }
}
