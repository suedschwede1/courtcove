<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Modules\Draw\Models\Draw;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Runs against the in-memory sqlite database only; the database name is faked on the
 * connection object, no other database is ever opened.
 */
class Phase2ScrubStagingCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        Draw::factory()->create(['slug' => 'real-draw-1']);
    }

    private function untouched(): bool
    {
        return Draw::query()->where('slug', 'real-draw-1')->exists();
    }

    #[Test]
    public function refuses_without_staging_database_name(): void
    {
        $this->artisan('phase2:scrub-staging', ['--force' => true])
            ->expectsOutput("Refusing: database ':memory:' does not end in '_staging'.")
            ->assertExitCode(1);

        $this->assertTrue($this->untouched());
    }

    #[Test]
    public function refuses_the_live_database(): void
    {
        config(['tournament-base.live_database' => 'x_staging']);
        DB::connection()->setDatabaseName('x_staging');

        $this->artisan('phase2:scrub-staging', ['--force' => true])
            ->expectsOutput("Refusing: database 'x_staging' is the live database.")
            ->assertExitCode(1);

        $this->assertTrue($this->untouched());
    }

    #[Test]
    public function refuses_without_force(): void
    {
        DB::connection()->setDatabaseName('booking_staging');

        $this->artisan('phase2:scrub-staging')
            ->expectsOutput('Refusing: pass --force.')
            ->assertExitCode(1);

        $this->assertTrue($this->untouched());
    }

    #[Test]
    public function aborts_when_confirmation_is_declined(): void
    {
        DB::connection()->setDatabaseName('booking_staging');

        $this->artisan('phase2:scrub-staging', ['--force' => true])
            ->expectsOutput('Database: booking_staging')
            ->expectsConfirmation('Truncate these tables and reseed?', 'no')
            ->expectsOutput('Aborted.')
            ->assertExitCode(1);

        $this->assertTrue($this->untouched());
    }

    #[Test]
    public function scrubs_and_reseeds_without_interaction(): void
    {
        DB::connection()->setDatabaseName('booking_staging');
        $this->app['env'] = 'production';

        $this->artisan('phase2:scrub-staging', ['--force' => true, '--no-interaction' => true])
            ->expectsOutput('Tables: bs_draw_eligible, bs_draw_tokens, bs_draws')
            ->assertExitCode(0);

        $this->assertFalse($this->untouched());
        $this->assertTrue(Draw::query()->where('slug', 'demo-closed')->exists());
    }
}
