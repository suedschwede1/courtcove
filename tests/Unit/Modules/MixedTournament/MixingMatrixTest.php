<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Services\HistoryService;
use App\Modules\MixedTournament\Support\MixingMatrix;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MixingMatrixTest extends TestCase
{
    private function matrix(): MixingMatrix
    {
        return new MixingMatrix(new HistoryService);
    }

    #[Test]
    public function levels_cap_at_three(): void
    {
        $result = $this->matrix()->build(
            [1 => 'Spieler 01', 2 => 'Spieler 02', 3 => 'Spieler 03', 4 => 'Spieler 04'],
            ['1:2' => 5, '1:3' => 2, '1:4' => 1],
            [],
        );

        $row = $result['rows'][0];
        $this->assertSame('Spieler 01', $row['label']);
        $this->assertSame(['count' => 5, 'level' => 3], array_intersect_key($row['cells'][1], ['count' => 0, 'level' => 0]));
        $this->assertSame(2, $row['cells'][2]['level']);
        $this->assertSame(1, $row['cells'][3]['level']);
        // Symmetric: the pair key does not depend on the order.
        $this->assertSame(5, $result['rows'][1]['cells'][0]['count']);
        $this->assertSame(0, $result['rows'][1]['cells'][2]['count']);
        $this->assertSame(0, $result['rows'][1]['cells'][2]['level']);
        $this->assertSame(['01', '02', '03', '04'], $result['heads']);
    }

    #[Test]
    public function self_cells_are_empty(): void
    {
        $result = $this->matrix()->build([7 => 'Anna Beispiel', 9 => 'Bernd Muster'], ['7:9' => 1], []);

        $this->assertNull($result['rows'][0]['cells'][0]['count']);
        $this->assertNull($result['rows'][1]['cells'][1]['count']);
        $this->assertSame(1, $result['rows'][0]['cells'][1]['count']);
        $this->assertSame(['Bei', 'Mus'], $result['heads']);
    }

    #[Test]
    public function matches_played_counts_all_four_slots(): void
    {
        $result = $this->matrix()->build(
            [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E'],
            [],
            [
                ['team_a_p1' => 1, 'team_a_p2' => 2, 'team_b_p1' => 3, 'team_b_p2' => 4],
                ['team_a_p1' => '1', 'team_a_p2' => '3', 'team_b_p1' => '2', 'team_b_p2' => '4'],
            ],
        );

        $this->assertSame([1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 0], $result['matches_played']);
    }
}
