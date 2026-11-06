<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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
        config(['tournament-base.live_database' => 'fictional_live']);
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
    #[DataProvider('unsetLiveDatabaseValues')]
    public function refuses_when_live_database_is_not_configured(mixed $value): void
    {
        config(['tournament-base.live_database' => $value]);
        DB::connection()->setDatabaseName('booking_staging');

        $this->artisan('phase2:scrub-staging', ['--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain('PHASE2_LIVE_DB_DATABASE')
            ->assertExitCode(1);

        $this->assertTrue($this->untouched());
    }

    /** @return array<string, array{mixed}> */
    public static function unsetLiveDatabaseValues(): array
    {
        return ['null' => [null], 'empty' => ['']];
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
        $user = User::factory()->create(['email' => 'keep.me@real-club.invalid']);
        DB::table('migrations')->insert(['migration' => 'survivor-row', 'batch' => 9999]);

        $this->artisan('phase2:scrub-staging', ['--force' => true, '--no-interaction' => true])
            ->expectsOutput('Tables: bs_draw_eligible, bs_draw_tokens, bs_draws, bs_pyramid_events, bs_pyramid_challenges, bs_pyramid_grants, bs_pyramid_players, bs_pyramid_ladders, bs_mixed_event_grants, bs_mixed_matches, bs_mixed_round_players, bs_mixed_rounds, bs_mixed_avoid_pairs, bs_mixed_participants, bs_mixed_events, bs_mixed_members, bs_tournament_lines, bs_tournament_matches, bs_tournament_players, bs_tournaments')
            ->assertExitCode(0);

        $this->assertFalse($this->untouched());
        $this->assertTrue(Draw::query()->where('slug', 'demo-closed')->exists());
        $this->assertTrue(DB::table('bs_pyramid_ladders')->where('title', 'Demo Herren')->exists());
        $this->assertTrue(User::query()->whereKey($user->getKey())->where('email', 'keep.me@real-club.invalid')->exists());
        $this->assertTrue(DB::table('migrations')->where('migration', 'survivor-row')->exists());
    }
}
