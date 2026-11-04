<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Preflight\DrawPreflight;
use App\Modules\TournamentBase\Preflight\PreflightResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrawPreflightTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        return array_map(fn (PreflightResult $r): string => $r->line(), app(DrawPreflight::class)->run());
    }

    private function has(string $prefix): bool
    {
        foreach ($this->lines() as $line) {
            if (str_starts_with($line, $prefix)) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function healthy_database_passes(): void
    {
        Draw::factory()->create();
        Draw::factory()->executed()->create();
        Draw::factory()->token()->executed()->create();
        Draw::factory()->token()->executed()->closed()->create();
        Draw::factory()->executed()->discarded()->create();

        $lines = $this->lines();

        $this->assertSame([], array_values(array_filter($lines, fn (string $l): bool => str_starts_with($l, '[FAIL]'))));
        $this->assertContains('[SKIP] charset:bs_draws: not MySQL', $lines);
        $this->assertContains('[PASS] verify: 5 draws checked', $lines);
    }

    #[Test]
    public function missing_column_fails(): void
    {
        Schema::drop('bs_draw_eligible');
        Schema::create('bs_draw_eligible', function (Blueprint $table): void {
            $table->increments('deid');
            $table->unsignedInteger('did');
        });

        $this->assertTrue($this->has('[FAIL] columns:bs_draw_eligible'));
    }

    #[Test]
    public function extra_column_warns(): void
    {
        Schema::table('bs_draws', function (Blueprint $table): void {
            $table->string('surprise')->nullable();
        });

        $this->assertTrue($this->has('[WARN] columns:bs_draws'));
        $this->assertFalse($this->has('[FAIL]'));
    }

    #[Test]
    public function duplicate_slug_rows_fail(): void
    {
        Schema::table('bs_draws', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
        });
        Draw::factory()->create(['slug' => 'same']);
        Draw::factory()->create(['slug' => 'same']);

        $this->assertTrue($this->has('[FAIL] unique:bs_draws(slug)'));
    }

    #[Test]
    public function orphan_tokens_warn(): void
    {
        DrawToken::create(['did' => 9999, 'position' => 1, 'token' => 'AC1']);

        $this->assertTrue($this->has('[WARN] orphans:bs_draw_tokens.did->bs_draws.did: 1 orphaned rows'));
    }

    #[Test]
    public function tampered_draw_fails_verify(): void
    {
        $draw = Draw::factory()->executed()->create();
        $draw->update(['commitment' => str_repeat('0', 64)]);

        $this->assertTrue($this->has("[FAIL] verify:{$draw->slug}: commitment"));
    }

    #[Test]
    public function preflight_is_read_only(): void
    {
        Draw::factory()->token()->executed()->create();
        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });

        $this->lines();

        $this->assertNotEmpty($sql);
        foreach ($sql as $statement) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|create|drop|alter|truncate|replace)\b/i', $statement);
        }
    }
}
