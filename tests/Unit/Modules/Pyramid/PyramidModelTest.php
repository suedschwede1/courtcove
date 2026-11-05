<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Pyramid;

use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PyramidModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function raw_strings_are_kept(): void
    {
        $ladder = PyramidLadder::factory()->create();
        $challenge = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id,
            'created' => '2026-03-01 10:00:00',
        ]);

        $this->assertSame('2026-03-01 10:00:00', $challenge->fresh()->created);
        $this->assertSame(['id' => 'int'], (new PyramidChallenge)->getCasts());
    }

    #[Test]
    public function relations_resolve(): void
    {
        $ladder = PyramidLadder::factory()->create();
        $challenger = PyramidPlayer::factory()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler A', 'position' => 1]);
        $opponent = PyramidPlayer::factory()->create(['ladder_id' => $ladder->id, 'label' => 'Spieler B', 'position' => 2]);
        $challenge = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
        ]);

        $this->assertSame('Spieler A', $challenge->challenger->label);
        $this->assertSame('Spieler B', $challenge->opponent->label);
        $this->assertSame($ladder->id, $challenge->ladder->id);
        $this->assertSame($ladder->id, $challenger->ladder->id);
        $this->assertSame(2, $ladder->players()->count());
        $this->assertSame(1, $ladder->challenges()->count());
        $this->assertTrue($ladder->isRunning());
        $this->assertTrue($challenge->isRunning());
    }

    #[Test]
    public function with_ranking_builds_positions_1_to_n(): void
    {
        $ladder = PyramidLadder::factory()->withRanking(3)->create();

        $players = $ladder->players()->orderBy('position')->get();
        $this->assertSame([1, 2, 3], $players->pluck('position')->all());
        $this->assertSame(['Spieler 1', 'Spieler 2', 'Spieler 3'], $players->pluck('label')->all());
        $this->assertSame(['active'], $players->pluck('status')->unique()->all());
    }
}
