<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Services\TournamentLinks;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * currentChampionship() is looked up once per request (menu, module overview and sidebar ask for it) and never
 * remembered beyond the request: a new request, or a new worker job, sees a created or finished championship at once.
 */
final class CurrentChampionshipCacheTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function championshipQueries(): int
    {
        return count(array_filter(DB::getQueryLog(), fn (array $q): bool => str_contains($q['query'], 'bs_tournaments')));
    }

    private function newRequest(): void
    {
        $this->app->instance('request', Request::create('/'));
    }

    #[Test]
    public function repeated_calls_in_one_request_run_one_query(): void
    {
        DB::table('bs_tournaments')->insert(['title' => 'VM', 'status' => 'running', 'active' => 1]);
        DB::enableQueryLog();

        $links = app(TournamentLinks::class);
        $first = $links->currentChampionship();
        $links->currentChampionship();
        app(TournamentLinks::class)->currentChampionship();

        $this->assertSame('VM', $first['title'] ?? null);
        $this->assertSame(1, $this->championshipQueries());
    }

    #[Test]
    public function a_missing_championship_is_remembered_for_the_request_too(): void
    {
        DB::enableQueryLog();

        $links = app(TournamentLinks::class);
        $this->assertNull($links->currentChampionship());
        $this->assertNull($links->currentChampionship());

        $this->assertSame(1, $this->championshipQueries());
    }

    #[Test]
    public function a_new_request_sees_a_created_and_a_finished_championship_at_once(): void
    {
        $links = app(TournamentLinks::class);
        $this->assertNull($links->currentChampionship());

        DB::table('bs_tournaments')->insert(['title' => 'VM', 'status' => 'running', 'active' => 1]);
        $this->newRequest();
        $this->assertSame('VM', $links->currentChampionship()['title'] ?? null);

        DB::table('bs_tournaments')->update(['status' => 'finished']);
        $this->newRequest();
        $this->assertNull($links->currentChampionship());
    }

    #[Test]
    public function a_new_worker_job_starts_fresh(): void
    {
        $links = app(TournamentLinks::class);
        $this->assertNull($links->currentChampionship());

        DB::table('bs_tournaments')->insert(['title' => 'VM', 'status' => 'running', 'active' => 1]);
        $this->app->forgetScopedInstances();

        $this->assertSame('VM', app(TournamentLinks::class)->currentChampionship()['title'] ?? null);
    }
}
