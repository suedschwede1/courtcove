<?php

declare(strict_types=1);

namespace App\Console\Scheduling;

/**
 * Cron expression in plain words (de/en via booking.admin.scheduler.every.*): "jede Minute", "alle 5 Minuten",
 * "stündlich zur Minute 15", "täglich 03:10", "wöchentlich Montag 06:00", "monatlich am 1. um 04:00".
 * Anything else is shown as the raw expression.
 */
final class ScheduleDescriber
{
    public function describe(string $expression): string
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];
        if (count($parts) !== 5) {
            return $expression;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;
        $rest = [$day, $month, $weekday];

        if ($minute === '*' && $hour === '*' && $rest === ['*', '*', '*']) {
            return __('booking.admin.scheduler.every.minute');
        }

        if (preg_match('/^\*\/(\d+)$/', $minute, $m) === 1 && $hour === '*' && $rest === ['*', '*', '*']) {
            return __('booking.admin.scheduler.every.minutes', ['n' => (int) $m[1]]);
        }

        if (ctype_digit($minute) && $hour === '*' && $rest === ['*', '*', '*']) {
            return __('booking.admin.scheduler.every.hour', ['minute' => str_pad($minute, 2, '0', STR_PAD_LEFT)]);
        }

        if (! ctype_digit($minute) || ! ctype_digit($hour)) {
            return $expression;
        }

        $time = sprintf('%02d:%02d', (int) $hour, (int) $minute);

        if ($rest === ['*', '*', '*']) {
            return __('booking.admin.scheduler.every.day', ['time' => $time]);
        }

        if ($day === '*' && $month === '*' && ctype_digit($weekday) && (int) $weekday <= 7) {
            return __('booking.admin.scheduler.every.week', [
                'weekday' => __('booking.admin.scheduler.weekdays.'.((int) $weekday % 7)),
                'time' => $time,
            ]);
        }

        if (ctype_digit($day) && $month === '*' && $weekday === '*') {
            return __('booking.admin.scheduler.every.month', ['day' => (int) $day, 'time' => $time]);
        }

        return $expression;
    }
}
