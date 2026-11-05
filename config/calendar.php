<?php

declare(strict_types=1);

// Defaults reference design tokens (var(--color-*)); overrides may use any CSS colour.
$colors = [
    'free' => [
        'bg' => env('CAL_COLOR_FREE_BG', 'var(--color-surface)'),
        'bg_hover' => env('CAL_COLOR_FREE_BG_HOVER', 'var(--color-clay-soft)'),
        'line' => env('CAL_COLOR_FREE_LINE', 'var(--color-cell-free-line)'),
    ],
    // Own booking: pale blue fill with a ring in the line colour (the only ringed booking state).
    'own' => [
        'bg' => env('CAL_COLOR_OWN_BG', 'var(--color-cell-own)'),
        'line' => env('CAL_COLOR_OWN_LINE', 'var(--color-cell-own-line)'),
        'label' => env('CAL_COLOR_OWN_LABEL', 'var(--color-cell-own-ink)'),
        'label_secondary' => env('CAL_COLOR_OWN_LABEL_SECONDARY', 'var(--color-cell-own-ink)'),
    ],
    'single_future' => [
        'bg' => env('CAL_COLOR_SINGLE_FUTURE_BG', 'var(--color-cell-booked)'),
        'label' => env('CAL_COLOR_SINGLE_FUTURE_LABEL', 'var(--color-ink)'),
        'label_secondary' => env('CAL_COLOR_SINGLE_FUTURE_LABEL_SECONDARY', 'var(--color-ink-2)'),
    ],
    'series' => [
        'bg' => env('CAL_COLOR_SERIES_BG', 'var(--color-cell-series)'),
        'label' => env('CAL_COLOR_SERIES_LABEL', 'var(--color-cell-series-ink)'),
    ],
    'past' => [
        'bg' => env('CAL_COLOR_PAST_BG', 'var(--color-cell-past-a)'),
        'bg_alt' => env('CAL_COLOR_PAST_BG_ALT', 'var(--color-cell-past-b)'),
        'label' => env('CAL_COLOR_PAST_LABEL', 'var(--color-ink-3)'),
    ],
    'event' => [
        'bg' => env('CAL_COLOR_EVENT_BG', 'var(--color-cell-event)'),
        'line' => env('CAL_COLOR_EVENT_LINE', 'var(--color-cell-event-line)'),
        'label' => env('CAL_COLOR_EVENT_LABEL', 'var(--color-cell-event-ink)'),
    ],
    // Court closure: diagonal hatching in two stripe colours, text in the muted ink.
    'blocked' => [
        'a' => env('CAL_COLOR_BLOCKED_A', 'var(--color-cell-blocked-a)'),
        'b' => env('CAL_COLOR_BLOCKED_B', 'var(--color-cell-blocked-b)'),
        'label' => env('CAL_COLOR_BLOCKED_LABEL', 'var(--color-ink-2)'),
    ],
    'tournament' => [
        'bg' => env('CAL_COLOR_TOURNAMENT_BG', 'var(--color-cell-match)'),
        'line' => env('CAL_COLOR_TOURNAMENT_LINE', 'var(--color-cell-match-line)'),
        'label' => env('CAL_COLOR_TOURNAMENT_LABEL', 'var(--color-cell-match-ink)'),
    ],
];

// Optional, per-installation file (not tracked in git) to override individual
// color values without touching this file. See calendar.local.example.php.
$localFile = __DIR__.'/calendar.local.php';
if (file_exists($localFile)) {
    $colors = array_replace_recursive($colors, require $localFile);
}

return [
    'colors' => $colors,

    // Events whose name contains one of these words (case-insensitive) count as a court
    // closure when no explicit kind is stored (older events). The admin form's "Art"
    // select (event meta "kind") always wins over this fallback.
    'closure_keywords' => ['Platzsperre', 'Sperre'],
];
