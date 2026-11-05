<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Database\Seeders\DrawDemoSeeder;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawVerifier;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrawDemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const SLUGS = ['demo-committed', 'demo-direct', 'demo-discarded', 'demo-picking', 'demo-closed'];

    #[Test]
    public function seeds_draws_in_every_state(): void
    {
        $this->seed(DrawDemoSeeder::class);

        $draws = Draw::query()->whereIn('slug', self::SLUGS)->get()->keyBy('slug');

        $this->assertCount(5, $draws);
        $this->assertSame(
            ['committed', 'executed', 'executed', 'picking', 'closed'],
            array_map(fn (string $slug): string => $draws[$slug]->status, self::SLUGS)
        );
        $this->assertSame(
            [false, true, true, false, true],
            array_map(fn (string $slug): bool => $draws[$slug]->isRevealed(), self::SLUGS)
        );
        $this->assertSame('Demo: verworfen', $draws['demo-discarded']->discarded_reason);
        $this->assertSame(2, $draws['demo-picking']->tokens()->whereNotNull('claimed_tpid')->count());
        $this->assertSame(6, $draws['demo-picking']->eligible()->count());
        $this->assertSame(4, $draws['demo-closed']->tokens()->where('assigned_by', 'pick')->count());
        $this->assertSame(2, $draws['demo-closed']->tokens()->where('assigned_by', 'fallback')->count());
    }

    #[Test]
    public function every_seeded_draw_verifies(): void
    {
        $this->seed(DrawDemoSeeder::class);

        foreach (Draw::query()->get() as $draw) {
            $this->assertTrue(app(DrawVerifier::class)->verify($draw)->ok(), $draw->slug);
        }
    }

    #[Test]
    public function seeding_twice_after_truncate_is_stable(): void
    {
        $this->seed(DrawDemoSeeder::class);
        $first = Draw::query()->orderBy('slug')->pluck('commitment', 'slug')->all();

        foreach (['bs_draw_eligible', 'bs_draw_tokens', 'bs_draws'] as $table) {
            DB::table($table)->truncate();
        }
        $this->seed(DrawDemoSeeder::class);

        $this->assertSame($first, Draw::query()->orderBy('slug')->pluck('commitment', 'slug')->all());
    }

    #[Test]
    public function only_example_test_users_are_created(): void
    {
        $before = User::query()->pluck('uid')->all();

        $this->seed(DrawDemoSeeder::class);

        $created = User::query()->whereNotIn('uid', $before)->pluck('email')->all();
        $this->assertNotEmpty($created);
        foreach ($created as $email) {
            $this->assertStringEndsWith('@example.test', $email);
        }
    }
}
