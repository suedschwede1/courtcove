<?php

declare(strict_types=1);

namespace App\Modules\TournamentBase\Services;

/**
 * Match score entry and validation (ported unchanged from the legacy rules).
 *
 * Best of three: sets 1 and 2 regular up to 6 with a tie-break at 6:6, a possible third set
 * is a match tie-break to 10 points (two points apart).
 *
 * Counting: the match tie-break counts as a won set and as 1:0 in games; its points do NOT enter
 * the game difference, otherwise a close three-set win would move the table more than a clean two-set win.
 */
class ScoreService
{
    private const P = 'tournament-base::score.';

    /**
     * @param  array<string, mixed>  $input  s1a, s1b, s2a, s2b and optional mtba, mtbb
     * @return array<string, mixed>
     *
     * @throws InvalidScoreException
     */
    public function evaluate(array $input): array
    {
        $s1a = $this->intOrNull($input, 's1a');
        $s1b = $this->intOrNull($input, 's1b');
        $s2a = $this->intOrNull($input, 's2a');
        $s2b = $this->intOrNull($input, 's2b');
        $mtba = $this->intOrNull($input, 'mtba');
        $mtbb = $this->intOrNull($input, 'mtbb');

        if ($s1a === null || $s1b === null || $s2a === null || $s2b === null) {
            throw new InvalidScoreException(self::P.'sets_incomplete');
        }

        $w1 = $this->winnerOfSet($s1a, $s1b, 1);
        $w2 = $this->winnerOfSet($s2a, $s2b, 2);

        $setsA = ($w1 === 'a' ? 1 : 0) + ($w2 === 'a' ? 1 : 0);
        $setsB = ($w1 === 'b' ? 1 : 0) + ($w2 === 'b' ? 1 : 0);

        $hasMtb = ($mtba !== null || $mtbb !== null);

        if ($setsA === 2 || $setsB === 2) {
            // decided in two sets - a third must not exist
            if ($hasMtb) {
                throw new InvalidScoreException(self::P.'mtb_after_two_sets');
            }

            $winnerSide = ($setsA === 2) ? 'a' : 'b';
        } else {
            // 1:1 - the match tie-break is mandatory
            if (! $hasMtb) {
                throw new InvalidScoreException(self::P.'mtb_required');
            }

            if ($mtba === null || $mtbb === null) {
                throw new InvalidScoreException(self::P.'mtb_both_scores');
            }

            $winnerSide = $this->winnerOfMatchTiebreak($mtba, $mtbb);

            if ($winnerSide === 'a') {
                $setsA++;
            } else {
                $setsB++;
            }
        }

        // games: regular sets only, the match tie-break as 1:0
        $gamesA = $s1a + $s2a;
        $gamesB = $s1b + $s2b;

        if ($hasMtb) {
            if ($winnerSide === 'a') {
                $gamesA++;
            } else {
                $gamesB++;
            }
        }

        return [
            's1a' => $s1a, 's1b' => $s1b,
            's2a' => $s2a, 's2b' => $s2b,
            'mtba' => $hasMtb ? $mtba : null,
            'mtbb' => $hasMtb ? $mtbb : null,
            'sets_a' => $setsA,
            'sets_b' => $setsB,
            'games_a' => $gamesA,
            'games_b' => $gamesB,
            'winner_side' => $winnerSide,
            'score' => $this->format($s1a, $s1b, $s2a, $s2b, $hasMtb ? $mtba : null, $hasMtb ? $mtbb : null),
        ];
    }

    /** Display text, e.g. "6:4 3:6 10:7" */
    public function format(mixed $s1a, mixed $s1b, mixed $s2a, mixed $s2b, mixed $mtba = null, mixed $mtbb = null): string
    {
        $parts = [$s1a.':'.$s1b, $s2a.':'.$s2b];

        if ($mtba !== null && $mtbb !== null) {
            $parts[] = $mtba.':'.$mtbb;
        }

        return implode(' ', $parts);
    }

    /** Valid set results: 6:0 to 6:4, 7:5 and 7:6 (either way round). */
    protected function winnerOfSet(int $a, int $b, int $number): string
    {
        if ($a < 0 || $b < 0 || $a > 7 || $b > 7) {
            throw new InvalidScoreException(self::P.'set_range', ['number' => $number]);
        }

        if (! $this->isValidSet($a, $b)) {
            throw new InvalidScoreException(self::P.'set_invalid', ['number' => $number, 'score' => $a.':'.$b]);
        }

        return ($a > $b) ? 'a' : 'b';
    }

    /** Match tie-break to 10, two points apart: 10:0 to 10:8, then 11:9, 12:10, ... */
    protected function winnerOfMatchTiebreak(int $a, int $b): string
    {
        if ($a < 0 || $b < 0) {
            throw new InvalidScoreException(self::P.'mtb_negative');
        }

        $high = max($a, $b);
        $low = min($a, $b);
        $score = ['score' => $a.':'.$b];

        if ($high < 10) {
            throw new InvalidScoreException(self::P.'mtb_min_ten', $score);
        }

        if ($high === 10 && $low > 8) {
            throw new InvalidScoreException(self::P.'mtb_ten_nine', $score);
        }

        if ($high > 10 && ($high - $low) !== 2) {
            throw new InvalidScoreException(self::P.'mtb_two_apart', $score);
        }

        if ($a === $b) {
            throw new InvalidScoreException(self::P.'mtb_draw');
        }

        return ($a > $b) ? 'a' : 'b';
    }

    /**
     * Describes the state of an input for display, including intermediate states that are not yet savable.
     * `message` is a full translation key or null.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function describe(array $input, bool $hasSchedule = false): array
    {
        $d = self::P.'describe.';
        $s1a = $this->intOrNullSafe($input, 's1a');
        $s1b = $this->intOrNullSafe($input, 's1b');
        $s2a = $this->intOrNullSafe($input, 's2a');
        $s2b = $this->intOrNullSafe($input, 's2b');
        $mtba = $this->intOrNullSafe($input, 'mtba');
        $mtbb = $this->intOrNullSafe($input, 'mtbb');

        $empty = ($s1a === null && $s1b === null && $s2a === null && $s2b === null
            && $mtba === null && $mtbb === null);

        if ($empty) {
            if ($hasSchedule) {
                return ['state' => 'success', 'message' => $d.'schedule_only', 'replace' => [], 'canSave' => true];
            }

            return ['state' => 'neutral', 'message' => $d.'enter_sets', 'replace' => [], 'canSave' => false];
        }

        if ($s1a === null || $s1b === null || ! $this->isValidSet($s1a, $s1b)) {
            return ['state' => 'error', 'message' => $d.'set1_invalid', 'replace' => [], 'canSave' => false];
        }

        if ($s2a === null || $s2b === null || ! $this->isValidSet($s2a, $s2b)) {
            return ['state' => 'error', 'message' => $d.'set2_invalid', 'replace' => [], 'canSave' => false];
        }

        $w1 = ($s1a > $s1b) ? 'a' : 'b';
        $w2 = ($s2a > $s2b) ? 'a' : 'b';
        $hasMtb = ($mtba !== null || $mtbb !== null);

        if ($w1 === $w2) {
            if ($hasMtb) {
                return ['state' => 'error', 'message' => $d.'mtb_must_be_empty', 'replace' => [], 'canSave' => false];
            }

            return ['state' => 'success', 'message' => null, 'replace' => [], 'canSave' => true, 'winner' => $w1];
        }

        if (! $hasMtb) {
            return ['state' => 'warning', 'message' => $d.'mtb_needed', 'replace' => [], 'canSave' => false];
        }

        if ($mtba === null || $mtbb === null || ! $this->isValidMatchTiebreak($mtba, $mtbb)) {
            return ['state' => 'error', 'message' => $d.'mtb_invalid', 'replace' => [], 'canSave' => false];
        }

        return [
            'state' => 'success',
            'message' => null,
            'replace' => [],
            'canSave' => true,
            'winner' => ($mtba > $mtbb) ? 'a' : 'b',
        ];
    }

    public function isValidSet(int $a, int $b): bool
    {
        if ($a < 0 || $b < 0 || $a > 7 || $b > 7 || $a === $b) {
            return false;
        }

        $high = max($a, $b);
        $low = min($a, $b);

        return ($high === 6 && $low <= 4) || ($high === 7 && ($low === 5 || $low === 6));
    }

    public function isValidMatchTiebreak(int $a, int $b): bool
    {
        if ($a < 0 || $b < 0 || $a === $b) {
            return false;
        }

        $high = max($a, $b);
        $low = min($a, $b);

        if ($high < 10) {
            return false;
        }

        if ($high === 10) {
            return $low <= 8;
        }

        return ($high - $low) === 2;
    }

    /** @param array<string, mixed> $input */
    protected function intOrNullSafe(array $input, string $key): ?int
    {
        try {
            return $this->intOrNull($input, $key);
        } catch (InvalidScoreException) {
            return null;
        }
    }

    /** @param array<string, mixed> $input */
    protected function intOrNull(array $input, string $key): ?int
    {
        if (! isset($input[$key]) || $input[$key] === '') {
            return null;
        }

        if (! preg_match('/^[0-9]{1,2}$/', trim((string) $input[$key]))) {
            throw new InvalidScoreException(self::P.'not_integer', ['field' => $key]);
        }

        return (int) $input[$key];
    }
}
