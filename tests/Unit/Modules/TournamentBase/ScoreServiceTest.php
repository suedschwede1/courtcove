<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TournamentBase;

use App\Modules\TournamentBase\Services\InvalidScoreException;
use App\Modules\TournamentBase\Services\ScoreService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScoreServiceTest extends TestCase
{
    #[Test]
    public function mtb_counts_as_set_and_one_game(): void
    {
        $r = (new ScoreService)->evaluate(['s1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6, 'mtba' => 10, 'mtbb' => 8]);

        $this->assertSame(2, $r['sets_a']);
        $this->assertSame(10, $r['games_a']);
        $this->assertSame(10, $r['games_b']);
    }

    #[Test]
    public function mtb_after_two_sets_is_rejected(): void
    {
        try {
            (new ScoreService)->evaluate(['s1a' => 6, 's1b' => 4, 's2a' => 6, 's2b' => 3, 'mtba' => 10, 'mtbb' => 8]);
            $this->fail('expected exception');
        } catch (InvalidScoreException $e) {
            $this->assertSame('tournament-base::score.mtb_after_two_sets', $e->key);
        }
    }

    #[Test]
    public function ten_nine_is_rejected(): void
    {
        try {
            (new ScoreService)->evaluate(['s1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6, 'mtba' => 10, 'mtbb' => 9]);
            $this->fail('expected exception');
        } catch (InvalidScoreException $e) {
            $this->assertSame('tournament-base::score.mtb_ten_nine', $e->key);
            $this->assertSame(['score' => '10:9'], $e->replace);
        }
    }

    #[Test]
    public function twelve_ten_is_valid(): void
    {
        $r = (new ScoreService)->evaluate(['s1a' => 6, 's1b' => 4, 's2a' => 3, 's2b' => 6, 'mtba' => 12, 'mtbb' => 10]);

        $this->assertSame('a', $r['winner_side']);
    }
}
