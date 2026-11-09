<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use App\Models\Square;
use Illuminate\Support\Collection;

/** Minute-accurate occupied intervals and free gaps within an hourly calendar row. */
final class CalendarSegments
{
    public static function seconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));

        return $parts[0] * 3600 + ($parts[1] ?? 0) * 60 + ($parts[2] ?? 0);
    }

    /** @return list<array{start: int, end: int, reservation: ?Reservation, bookEnd: int, closed: bool, tooShort: bool}> */
    public function forHour(Collection $reservations, Square $square, int $hour, ?int $minimumSeconds = null): array
    {
        $start = $hour * 3600;
        $end = $start + 3600;
        $open = self::seconds((string) $square->time_start);
        $close = self::seconds((string) $square->time_end);
        $minimumSeconds ??= app(BookingRules::class)->minimumSeconds($square);
        $parts = [];
        $cursor = $start;
        $sorted = $reservations->sortBy('time_start');
        $addGap = function (int $from, int $to) use (&$parts, $open, $close, $sorted, $minimumSeconds): void {
            $bounds = array_values(array_unique([$from, max($from, min($to, $open)), max($from, min($to, $close)), $to]));
            sort($bounds);
            for ($i = 0; $i < count($bounds) - 1; $i++) {
                $a = $bounds[$i];
                $b = $bounds[$i + 1];
                if ($b <= $a) {
                    continue;
                }
                $availableUntil = $close;
                foreach ($sorted as $reservation) {
                    $next = self::seconds((string) $reservation->time_start);
                    if ($next >= $b) {
                        $availableUntil = min($availableUntil, $next);
                    }
                }
                $bookEnd = max($b, $a + $minimumSeconds);
                $parts[] = ['start' => $a, 'end' => $b, 'reservation' => null, 'bookEnd' => $bookEnd,
                    'closed' => $a < $open || $b > $close, 'tooShort' => $bookEnd > $availableUntil];
            }
        };
        foreach ($sorted as $reservation) {
            $a = max($start, self::seconds((string) $reservation->time_start));
            $b = min($end, self::seconds((string) $reservation->time_end));
            if ($b <= $a) {
                continue;
            }
            if ($a > $cursor) {
                $addGap($cursor, $a);
            }
            $parts[] = ['start' => $a, 'end' => $b, 'reservation' => $reservation, 'bookEnd' => $b, 'closed' => false, 'tooShort' => false];
            $cursor = max($cursor, $b);
        }
        if ($cursor < $end) {
            $addGap($cursor, $end);
        }

        return $parts;
    }
}
