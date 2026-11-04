<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TournamentLinks;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentLinksTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // bs_tournaments is owned by the legacy ZF2 system and has no Laravel migration.
        Schema::dropIfExists('bs_tournaments');
        Schema::create('bs_tournaments', function ($table): void {
            $table->increments('tid');
            $table->string('title');
            $table->string('status')->default('running');
            $table->integer('season')->default(2026);
            $table->tinyInteger('active')->default(0);
            $table->tinyInteger('is_test')->default(0);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('bs_tournaments');

        parent::tearDown();
    }

    private function links(): TournamentLinks
    {
        return app(TournamentLinks::class);
    }

    /** @param array<string, mixed> $attributes */
    private function tournament(array $attributes): void
    {
        DB::table('bs_tournaments')->insert($attributes + [
            'title' => 'VM',
            'status' => 'running',
            'season' => 2026,
            'active' => 0,
            'is_test' => 0,
        ]);
    }

    #[Test]
    public function returns_null_when_table_is_missing(): void
    {
        Schema::dropIfExists('bs_tournaments');

        $this->assertNull($this->links()->currentChampionship());
    }

    #[Test]
    public function returns_null_when_no_tournament(): void
    {
        $this->assertNull($this->links()->currentChampionship());
    }

    #[Test]
    public function ignores_test_tournaments(): void
    {
        $this->tournament(['title' => 'Test', 'is_test' => 1]);

        $this->assertNull($this->links()->currentChampionship());
    }

    #[Test]
    public function returns_null_when_current_is_finished(): void
    {
        $this->tournament(['status' => 'finished']);

        $this->assertNull($this->links()->currentChampionship());
    }

    #[Test]
    public function prefers_active_then_latest_season(): void
    {
        $this->tournament(['title' => 'VM 2025', 'season' => 2025, 'active' => 1]);
        $this->tournament(['title' => 'VM 2026', 'season' => 2026, 'active' => 0]);

        $this->assertSame([
            'title' => 'VM 2025',
            'url' => 'https://tournament.example.test/tournament',
        ], $this->links()->currentChampionship());
    }

    #[Test]
    public function falls_back_to_default_base_url_when_env_is_empty(): void
    {
        // An empty TOURNAMENT_BASE_URL= in .env makes env() return '' instead of the default.
        putenv('TOURNAMENT_BASE_URL=');
        $_ENV['TOURNAMENT_BASE_URL'] = '';
        $_SERVER['TOURNAMENT_BASE_URL'] = '';
        $services = require base_path('config/services.php');
        putenv('TOURNAMENT_BASE_URL');
        unset($_ENV['TOURNAMENT_BASE_URL'], $_SERVER['TOURNAMENT_BASE_URL']);

        $this->assertSame('https://tournament.example.test', $services['tournament']['base_url']);
    }

    #[Test]
    public function builds_pyramid_and_mixed_urls_from_config(): void
    {
        config(['services.tournament.base_url' => 'https://t.example/']);

        $this->assertSame('https://t.example/pyramide', $this->links()->pyramidUrl());
        $this->assertSame('https://t.example/mixed-tournament', $this->links()->mixedUrl());
    }
}
