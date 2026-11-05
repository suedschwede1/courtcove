<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Pyramid;

use App\Modules\Pyramid\Services\RuleService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Same assertions as modulex/tests/pyramid.php of the old application.
 */
class RuleServiceTest extends TestCase
{
    private RuleService $rules;

    protected function setUp(): void
    {
        $this->rules = new RuleService;
    }

    /**
     * @return list<array{id: int, position: int}>
     */
    private function players(int $n): array
    {
        return array_map(fn (int $i): array => ['id' => 100 + $i, 'position' => $i], range(1, $n));
    }

    /**
     * @return array<int, int>
     */
    private function ladder(): array
    {
        return [101 => 1, 102 => 2, 103 => 3, 104 => 4, 105 => 5];
    }

    #[Test]
    public function position_1_is_in_row_1(): void
    {
        $this->assertSame(1, $this->rules->rowOf(1));
    }

    #[Test]
    public function position_2_is_in_row_2(): void
    {
        $this->assertSame(2, $this->rules->rowOf(2));
    }

    #[Test]
    public function position_3_is_in_row_2(): void
    {
        $this->assertSame(2, $this->rules->rowOf(3));
    }

    #[Test]
    public function position_4_is_in_row_3(): void
    {
        $this->assertSame(3, $this->rules->rowOf(4));
    }

    #[Test]
    public function position_6_closes_row_3(): void
    {
        $this->assertSame(3, $this->rules->rowOf(6));
    }

    #[Test]
    public function position_7_opens_row_4(): void
    {
        $this->assertSame(4, $this->rules->rowOf(7));
    }

    #[Test]
    public function position_15_closes_row_5(): void
    {
        $this->assertSame(5, $this->rules->rowOf(15));
    }

    #[Test]
    public function position_16_opens_row_6(): void
    {
        $this->assertSame(6, $this->rules->rowOf(16));
    }

    #[Test]
    public function position_1_is_column_1(): void
    {
        $this->assertSame(1, $this->rules->colOf(1));
    }

    #[Test]
    public function position_3_is_column_2(): void
    {
        $this->assertSame(2, $this->rules->colOf(3));
    }

    #[Test]
    public function position_7_is_column_1(): void
    {
        $this->assertSame(1, $this->rules->colOf(7));
    }

    #[Test]
    public function position_10_is_column_4(): void
    {
        $this->assertSame(4, $this->rules->colOf(10));
    }

    #[Test]
    public function row_4_starts_at_7(): void
    {
        $this->assertSame(7, $this->rules->firstOfRow(4));
    }

    #[Test]
    public function row_4_ends_at_10(): void
    {
        $this->assertSame(10, $this->rules->lastOfRow(4));
    }

    #[Test]
    public function position_0_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rules->rowOf(0);
    }

    #[Test]
    public function negative_position_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rules->rowOf(-3);
    }

    #[Test]
    public function row_0_is_invalid_for_first_of_row(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rules->firstOfRow(0);
    }

    #[Test]
    public function row_0_is_invalid_for_last_of_row(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rules->lastOfRow(0);
    }

    #[Test]
    public function numeric_string_positions_are_accepted(): void
    {
        $this->assertSame(4, $this->rules->rowOf('10'));
        $this->assertSame([2, 3], $this->rules->challengeable('4'));
    }

    #[Test]
    public function the_top_challenges_nobody(): void
    {
        $this->assertSame([], $this->rules->challengeable(1));
    }

    #[Test]
    public function position_2_challenges_only_the_top(): void
    {
        $this->assertSame([1], $this->rules->challengeable(2));
    }

    #[Test]
    public function position_3_challenges_1_and_2(): void
    {
        $this->assertSame([1, 2], $this->rules->challengeable(3));
    }

    #[Test]
    public function position_4_does_not_reach_the_top_directly(): void
    {
        $this->assertSame([2, 3], $this->rules->challengeable(4));
    }

    #[Test]
    public function position_5_challenges_row_2_and_4(): void
    {
        $this->assertSame([2, 3, 4], $this->rules->challengeable(5));
    }

    #[Test]
    public function position_8_challenges_row_3_and_7(): void
    {
        $this->assertSame([4, 5, 6, 7], $this->rules->challengeable(8));
    }

    #[Test]
    public function position_15_challenges_row_4_and_its_own_row_to_the_left(): void
    {
        $this->assertSame([7, 8, 9, 10, 11, 12, 13, 14], $this->rules->challengeable(15));
    }

    #[Test]
    public function cap_keeps_what_exists_anyway(): void
    {
        $this->assertSame([4, 5, 6, 7], $this->rules->challengeable(8, 8));
    }

    #[Test]
    public function cap_at_5_participants(): void
    {
        $this->assertSame([2, 3, 4], $this->rules->challengeable(5, 5));
    }

    #[Test]
    public function cap_removes_missing_positions(): void
    {
        $this->assertSame([7, 8], $this->rules->challengeable(11, 8));
    }

    #[Test]
    public function winner_takes_loser_place(): void
    {
        $after = $this->rules->shiftForWin($this->ladder(), 4, 2);

        $this->assertSame([101 => 1, 102 => 3, 103 => 4, 104 => 2, 105 => 5], $after);
    }

    #[Test]
    public function the_top_stays_untouched(): void
    {
        $this->assertSame(1, $this->rules->shiftForWin($this->ladder(), 4, 2)[101]);
    }

    #[Test]
    public function players_behind_the_winner_stay(): void
    {
        $this->assertSame(5, $this->rules->shiftForWin($this->ladder(), 4, 2)[105]);
    }

    #[Test]
    public function shift_creates_no_duplicate_position(): void
    {
        $after = $this->rules->shiftForWin($this->ladder(), 4, 2);

        $this->assertCount(count($after), array_unique($after));
    }

    #[Test]
    public function adjacent_positions_swap_effectively(): void
    {
        $after = $this->rules->shiftForWin($this->ladder(), 3, 2);

        $this->assertSame([101 => 1, 102 => 3, 103 => 2, 104 => 4, 105 => 5], $after);
    }

    #[Test]
    public function challenger_already_ahead_changes_nothing(): void
    {
        $this->assertSame($this->ladder(), $this->rules->shiftForWin($this->ladder(), 2, 4));
    }

    #[Test]
    public function same_position_changes_nothing(): void
    {
        $this->assertSame($this->ladder(), $this->rules->shiftForWin($this->ladder(), 3, 3));
    }

    #[Test]
    public function entry_grows_the_ladder(): void
    {
        $after = $this->rules->shiftForEntry($this->ladder(), 3);

        $this->assertSame([101 => 1, 102 => 2, 103 => 4, 104 => 5, 105 => 6], $after);
    }

    #[Test]
    public function entry_creates_no_duplicate_position(): void
    {
        $after = $this->rules->shiftForEntry($this->ladder(), 3);

        $this->assertCount(count($after), array_unique($after));
        $this->assertSame(6, max($after));
    }

    #[Test]
    public function entry_behind_the_last_place_moves_nobody(): void
    {
        $this->assertSame($this->ladder(), $this->rules->shiftForEntry($this->ladder(), 6));
    }

    #[Test]
    public function win_on_a_gapped_ladder_creates_no_duplicate(): void
    {
        $after = $this->rules->shiftForWin([201 => 1, 202 => 4, 203 => 5], 5, 1);

        $this->assertCount(count($after), array_unique($after));
    }

    #[Test]
    public function entry_on_a_gapped_ladder_creates_no_duplicate(): void
    {
        $after = $this->rules->shiftForEntry([201 => 1, 202 => 4, 203 => 5], 4);

        $this->assertCount(count($after), array_unique($after));
    }

    #[Test]
    public function unknown_winner_position_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rules->shiftForWin($this->ladder(), 9, 1);
    }

    #[Test]
    public function unknown_loser_position_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rules->shiftForWin($this->ladder(), 1, 9);
    }

    #[Test]
    public function four_players_make_three_rows(): void
    {
        $rows = $this->rules->pyramidRows($this->players(4));

        $this->assertCount(3, $rows);
        $this->assertSame([1, 2, 1], array_map('count', array_values($rows)));
    }

    #[Test]
    public function order_inside_a_row_is_kept(): void
    {
        $rows = $this->rules->pyramidRows($this->players(4));

        $this->assertSame(102, $rows[2][0]['id']);
    }

    #[Test]
    public function pyramid_rows_accepts_models_with_a_position(): void
    {
        $players = array_map(fn (array $p): object => (object) $p, $this->players(3));

        $rows = $this->rules->pyramidRows($players);

        $this->assertSame(102, $rows[2][0]->id);
    }

    #[Test]
    public function pyramid_rows_of_nobody_is_empty(): void
    {
        $this->assertSame([], $this->rules->pyramidRows([]));
    }
}
