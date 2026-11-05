<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;

trait BuildsPyramidLadders
{
    /**
     * A running ladder with active players "Spieler 1".."Spieler n" on positions 1..n.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeLadder(int $count, array $attributes = []): PyramidLadder
    {
        return PyramidLadder::factory()
            ->withRanking($count)
            ->create(array_merge(['status' => 'running'], $attributes));
    }

    protected function playerAt(PyramidLadder $ladder, int $position): PyramidPlayer
    {
        return PyramidPlayer::query()
            ->where('ladder_id', $ladder->id)
            ->where('position', $position)
            ->firstOrFail();
    }

    /**
     * @return array<string, int> label => position, ranked players only
     */
    protected function positionsByLabel(int $ladderId): array
    {
        return PyramidPlayer::query()
            ->where('ladder_id', $ladderId)
            ->whereNotNull('position')
            ->orderBy('position')
            ->pluck('position', 'label')
            ->map(fn ($position): int => (int) $position)
            ->all();
    }

    protected function assertRankingIntact(int $ladderId): void
    {
        $players = PyramidPlayer::query()->where('ladder_id', $ladderId)->get();

        $ranked = $players->whereIn('status', ['active', 'paused']);
        $this->assertSame(
            0,
            $ranked->whereNull('position')->count(),
            'Active or paused player without a position'
        );

        $unranked = $players->whereIn('status', ['waiting', 'entering', 'removed']);
        $this->assertSame(
            0,
            $unranked->whereNotNull('position')->count(),
            'Waiting, entering or removed player still holds a position'
        );

        $positions = $players->whereNotNull('position')->pluck('position')->map(fn ($p): int => (int) $p)->sort()->values()->all();
        $expected = $positions === [] ? [] : range(1, count($positions));
        $this->assertSame($expected, $positions, 'Positions are not a gapless 1..n');
    }
}
