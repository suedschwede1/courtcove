<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Models\User;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawVerifier;
use App\Modules\Tournament\Database\Seeders\TournamentDemoSeeder;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentLine;
use App\Modules\Tournament\Models\TournamentMatch;
use App\Modules\Tournament\Models\TournamentPlayer;
use App\Modules\Tournament\Services\FormatService;
use App\Modules\Tournament\Services\TournamentManager;
use App\Modules\Tournament\Staging\TournamentScrub;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentDemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function real(): Tournament
    {
        return Tournament::query()->where('title', 'Demo-Meisterschaft')->where('is_test', 0)->firstOrFail();
    }

    private function draw(string $slug): Draw
    {
        return Draw::query()->where('slug', $slug)->firstOrFail();
    }

    #[Test]
    public function seeds_every_state(): void
    {
        $this->seed(TournamentDemoSeeder::class);
        $real = $this->real();
        $tid = (int) $real->tid;

        $this->assertSame(1, (int) $real->active);
        $this->assertSame('setup', $real->status);
        $this->assertSame(json_decode((string) file_get_contents(base_path('tests/Fixtures/tournament/formats/reference.json')), true), json_decode((string) $real->format, true), 'the real format');
        $this->assertSame(24, TournamentPlayer::query()->where('tid', $tid)->count());

        // Group heads: seeds 1-3 and 11-14 on position 1.
        $heads = TournamentPlayer::query()->where('tid', $tid)->where('grp_pos', 1)->orderBy('seed')->pluck('grp', 'seed')->all();
        $this->assertSame([1 => 'A', 2 => 'B', 3 => 'C', 11 => 'D', 12 => 'E', 13 => 'F', 14 => 'G'], $heads);

        // AC: picking, two picks with their groups written.
        $ac = $this->draw('demo-tn-ac');
        $this->assertSame(['token', 'picking'], [$ac->mode, $ac->status]);
        $this->assertNotNull($ac->final_seed);
        $this->assertSame(7, DrawToken::query()->where('did', $ac->did)->count());
        $picked = DrawToken::query()->where('did', $ac->did)->whereNotNull('claimed_tpid')->get();
        $this->assertCount(2, $picked);
        foreach ($picked as $token) {
            $player = TournamentPlayer::query()->findOrFail($token->claimed_tpid);
            $this->assertSame($token->slot, $player->grp.$player->grp_pos);
        }

        // DG: closed and applied, every lower player placed.
        $dg = $this->draw('demo-tn-dg');
        $this->assertSame('closed', $dg->status);
        $this->assertNotNull($dg->applied_at);
        $this->assertSame(['fallback' => 3, 'pick' => 7], DrawToken::query()->where('did', $dg->did)->get()->countBy('assigned_by')->sortKeys()->all());
        $this->assertSame(0, TournamentPlayer::query()->where('tid', $tid)->whereBetween('seed', [11, 24])->whereNull('grp')->count());

        // Group D is a complete three-way tie, E and F half played, G open.
        $m = app(TournamentManager::class);
        $d = $m->rankedTable($tid, 'D');
        $this->assertCount(3, $d);
        $this->assertSame([true, true, true], array_map(fn (array $row): bool => (bool) $row['needs_lot'], $d));
        $played = fn (string $g): int => TournamentMatch::query()->where('tid', $tid)->where('grp', $g)->whereNotNull('winner')->count();
        $this->assertSame([3, 1, 2, 0], [$played('D'), $played('E'), $played('F'), $played('G')]);

        // KO lines with placeholders of the applied KO_A q draw; committed, executed and discarded bracket draws.
        $this->assertSame(['Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6'], TournamentLine::query()->where('tid', $tid)->where('phase', 'KO_A')
            ->where('pool', 'q')->whereNotNull('placeholder')->orderBy('placeholder')->pluck('placeholder')->all());
        $this->assertSame(16, TournamentLine::query()->where('tid', $tid)->where('phase', 'KO_A')->count());
        $this->assertSame('executed', $this->draw('demo-tn-ko-a-q')->status);
        $this->assertNotNull($this->draw('demo-tn-ko-a-q')->applied_at);
        $this->assertSame('committed', $this->draw('demo-tn-ko-a-ac')->status);
        $this->assertNotNull($this->draw('demo-tn-ko-b-rest')->discarded);

        // The test copy and the finished championship with its bye line.
        $copy = Tournament::query()->where('cloned_from', $tid)->firstOrFail();
        $this->assertSame([1, 'setup', 0], [(int) $copy->is_test, $copy->status, (int) $copy->active]);
        $this->assertSame(24, TournamentPlayer::query()->where('tid', $copy->tid)->count());

        $finished = Tournament::query()->where('status', 'finished')->firstOrFail();
        $this->assertSame([0, 0], [(int) $finished->is_test, (int) $finished->active]);
        $this->assertSame(9, TournamentMatch::query()->where('tid', $finished->tid)->whereNotNull('grp')->whereNotNull('winner')->count());
        $this->assertTrue(TournamentLine::query()->where('tid', $finished->tid)->where('kind', 'bye')->exists());
        (new FormatService((string) $finished->format))->validate();

        $this->assertSame(1, Tournament::query()->where('is_test', 0)->where('active', 1)->count());
    }

    #[Test]
    public function every_seeded_draw_verifies(): void
    {
        $this->seed(TournamentDemoSeeder::class);
        $verifier = app(DrawVerifier::class);

        $draws = Draw::query()->orderBy('did')->get();
        $this->assertCount(5, $draws);

        foreach ($draws as $draw) {
            $verification = $verifier->verify($draw);
            if ($draw->final_seed !== null) {
                $this->assertTrue($verification->ok(), $draw->slug.': '.implode(', ', $verification->failures()));
            } else {
                $this->assertNotFalse($verification->checks['canonical'] ?? null, $draw->slug);
            }
            $this->assertSame(64, strlen((string) $draw->commitment));
            $this->assertStringStartsWith('demo-tn-', $draw->slug);
        }
    }

    #[Test]
    public function only_example_test_users(): void
    {
        $this->seed(TournamentDemoSeeder::class);

        $users = User::query()->orderBy('email')->get();
        $this->assertCount(24, $users);
        foreach ($users as $i => $user) {
            $this->assertSame(sprintf('tn-demo-%02d@example.test', $i + 1), $user->email);
            $this->assertSame(sprintf('Demo Spieler %02d', $i + 1), $user->alias);
        }
        foreach (TournamentPlayer::query()->get() as $player) {
            $this->assertMatchesRegularExpression('/^Demo Spieler \d{2}$/', $player->label);
            $this->assertNull($player->phone);
        }
    }

    #[Test]
    public function scrub_is_registered(): void
    {
        $this->assertContains('tournament', collect($this->app->tagged('phase2.scrub'))->map->module()->all());
        $this->assertSame(['bs_tournament_lines', 'bs_tournament_matches', 'bs_tournament_players', 'bs_tournaments'], (new TournamentScrub)->tables());
        $this->assertSame(TournamentDemoSeeder::class, (new TournamentScrub)->seeder());

        // Draw tables are scrubbed by the draw module, before the tournament reseeds its draws.
        $modules = collect($this->app->tagged('phase2.scrub'))->map->module()->values()->all();
        $this->assertLessThan(array_search('tournament', $modules, true), array_search('draw', $modules, true));
    }

    #[Test]
    public function scrub_and_reseed_is_stable(): void
    {
        DB::connection()->setDatabaseName('booking_staging');
        config(['tournament-base.live_database' => 'fictional_live']);
        $this->app['env'] = 'production';
        Tournament::factory()->create(['title' => 'Echte Meisterschaft']);

        $this->artisan('phase2:scrub-staging', ['--force' => true, '--no-interaction' => true])->assertExitCode(0);
        $this->assertFalse(Tournament::query()->where('title', 'Echte Meisterschaft')->exists());
        $first = $this->snapshot();

        $this->artisan('phase2:scrub-staging', ['--force' => true, '--no-interaction' => true])->assertExitCode(0);

        $this->assertSame($first, $this->snapshot());
        $this->assertSame(1, User::query()->where('email', 'tn-demo-01@example.test')->count());
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $rows = fn (string $table, string $key, array $skip = []) => DB::table($table)->orderBy($key)->get()
            ->map(fn ($row): array => array_diff_key((array) $row, array_flip($skip)))->all();

        return [
            'tournaments' => $rows('bs_tournaments', 'tid', ['created']),
            'players' => $rows('bs_tournament_players', 'tpid'),
            'matches' => $rows('bs_tournament_matches', 'tmid', ['entered', 'confirmed']),
            'lines' => $rows('bs_tournament_lines', 'tlid'),
            'draws' => $rows('bs_draws', 'did', ['created', 'executed', 'applied_at', 'closed', 'discarded']),
            'tokens' => $rows('bs_draw_tokens', 'dtid', ['claimed']),
            'eligible' => $rows('bs_draw_eligible', 'deid'),
        ];
    }
}
