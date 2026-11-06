<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tournament;

use App\Modules\Tournament\Models\TournamentMatch;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentMatchFactoryTest extends TestCase
{
    #[Test]
    public function the_match_tiebreak_counts_as_a_set_and_as_one_game_like_the_old_score_service(): void
    {
        // 6:4 3:6 and a match tie-break 10:8 (a wins): sets 2:1, games 9+1 : 10
        $m = TournamentMatch::factory()->played(1, [[6, 4], [3, 6], [10, 8]])->make();

        $this->assertSame(2, $m->sets_a);
        $this->assertSame(1, $m->sets_b);
        $this->assertSame(10, $m->games_a);
        $this->assertSame(10, $m->games_b);
        $this->assertSame(10, $m->mtba);
    }

    #[Test]
    public function a_match_without_tiebreak_adds_only_the_regular_games(): void
    {
        $m = TournamentMatch::factory()->played(1, [[6, 3], [6, 2]])->make();

        $this->assertSame([2, 0, 12, 5], [$m->sets_a, $m->sets_b, $m->games_a, $m->games_b]);
    }
}
