<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\User;
use App\Modules\Pyramid\Database\Seeders\PyramidDemoSeeder;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidEvent;
use App\Modules\Pyramid\Models\PyramidGrant;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\Pyramid\Staging\PyramidScrub;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PyramidDemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function seeds_every_state(): void
    {
        $this->seed(PyramidDemoSeeder::class);

        $statuses = PyramidChallenge::query()->distinct()->pluck('status')->all();
        $this->assertEqualsCanonicalizing(
            ['open', 'scheduled', 'played', 'forfeited', 'declined', 'withdrawn', 'expired'],
            $statuses
        );
        $this->assertCount(3, PyramidLadder::query()->get());

        $herren = PyramidLadder::query()->where('title', 'Demo Herren')->where('season', 2027)->first();
        $this->assertSame('running', $herren->status);
        $this->assertSame(1, (int) $herren->is_default);

        $this->assertSame('setup', PyramidLadder::query()->where('title', 'Demo Damen')->value('status'));
        $this->assertSame('closed', PyramidLadder::query()->where('title', 'Demo Herren')->where('season', 2026)->value('status'));
        $this->assertSame(4, PyramidPlayer::query()->where('status', 'waiting')
            ->whereIn('ladder_id', PyramidLadder::query()->where('title', 'Demo Damen')->pluck('id'))->count());

        $this->assertSame(1, PyramidPlayer::query()->where('ladder_id', $herren->id)->where('status', 'paused')->count());
        $this->assertSame(1, PyramidPlayer::query()->where('ladder_id', $herren->id)->where('status', 'waiting')->count());
        $this->assertSame(1, PyramidPlayer::query()->where('ladder_id', $herren->id)->where('status', 'entering')->count());
        $this->assertSame(1, PyramidGrant::query()->where('ladder_id', $herren->id)->count());

        $overdue = PyramidChallenge::query()->where('status', 'open')->whereDate('deadline', '<', now()->toDateString())->count();
        $this->assertSame(1, $overdue);
        $this->assertSame('6:4 6:3', PyramidChallenge::query()->where('status', 'played')->value('score'));
        $this->assertSame('nicht angetreten', PyramidChallenge::query()->where('status', 'forfeited')->value('score'));

        foreach (PyramidChallenge::query()->get() as $challenge) {
            $this->assertGreaterThan(0, PyramidEvent::query()->where('challenge_id', $challenge->id)->count(), $challenge->status);
        }
    }

    #[Test]
    public function ranking_is_intact(): void
    {
        $this->seed(PyramidDemoSeeder::class);

        $herren = PyramidLadder::query()->where('title', 'Demo Herren')->where('season', 2027)->first();
        $positions = PyramidPlayer::query()->where('ladder_id', $herren->id)->whereNotNull('position')
            ->orderBy('position')->pluck('position')->map(fn ($p): int => (int) $p)->all();

        $this->assertSame(range(1, 15), $positions);
        $this->assertSame(
            0,
            PyramidPlayer::query()->where('ladder_id', $herren->id)->whereIn('status', ['waiting', 'entering'])->whereNotNull('position')->count()
        );
    }

    #[Test]
    public function only_example_test_users(): void
    {
        $this->seed(PyramidDemoSeeder::class);

        $this->assertGreaterThan(15, User::query()->count());

        foreach (User::query()->get() as $user) {
            $this->assertStringEndsWith('@example.test', (string) $user->email);
            $this->assertMatchesRegularExpression('/^Demo (Spieler \d{2}|Admin)$/', (string) $user->alias);
        }

        foreach (PyramidPlayer::query()->get() as $player) {
            $this->assertMatchesRegularExpression('/^Demo Spieler \d{2}$/', $player->label);
            $this->assertMatchesRegularExpression('/^\+43 000 000 00\d{2}$/', (string) $player->phone);
            $this->assertTrue($player->email === null || str_ends_with($player->email, '@example.test'));
        }
    }

    #[Test]
    public function seeding_twice_after_truncate_is_stable(): void
    {
        $this->seed(PyramidDemoSeeder::class);
        $first = $this->snapshot();

        foreach ((new PyramidScrub)->tables() as $table) {
            DB::table($table)->truncate();
        }

        $this->seed(PyramidDemoSeeder::class);

        $this->assertSame($first, $this->snapshot());
        $this->assertSame(1, User::query()->where('email', 'pyramid-demo-01@example.test')->count());
    }

    #[Test]
    public function scrub_is_registered(): void
    {
        $this->assertContains('pyramid', collect($this->app->tagged('phase2.scrub'))->map->module()->all());
        $this->assertSame(
            ['bs_pyramid_events', 'bs_pyramid_challenges', 'bs_pyramid_grants', 'bs_pyramid_players', 'bs_pyramid_ladders'],
            (new PyramidScrub)->tables()
        );
        $this->assertSame(PyramidDemoSeeder::class, (new PyramidScrub)->seeder());
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'players' => PyramidPlayer::query()->orderBy('ladder_id')->orderBy('id')
                ->get(['ladder_id', 'position', 'label', 'status'])->toArray(),
            'ladders' => PyramidLadder::query()->orderBy('id')->get(['title', 'season', 'status'])->toArray(),
            'challenges' => PyramidChallenge::query()->orderBy('id')->get(['status', 'score'])->toArray(),
        ];
    }
}
