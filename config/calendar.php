<?php

declare(strict_types=1);

// Defaults reference design tokens (var(--color-*)); overrides may use any CSS colour.
$colors = [
    'free' => [
        'bg' => env('CAL_COLOR_FREE_BG', 'var(--color-surface)'),
        'bg_hover' => env('CAL_COLOR_FREE_BG_HOVER', 'var(--color-clay-soft)'),
        'line' => env('CAL_COLOR_FREE_LINE', 'var(--color-cell-free-line)'),
    ],
    'own' => [
        'bg' => env('CAL_COLOR_OWN_BG', 'var(--color-clay)'),
        'bg_hover' => env('CAL_COLOR_OWN_BG_HOVER', 'var(--color-clay-hover)'),
        'accent' => env('CAL_COLOR_OWN_ACCENT', 'var(--color-clay-hover)'),
        'label' => env('CAL_COLOR_OWN_LABEL', 'var(--color-on-clay)'),
        'label_secondary' => env('CAL_COLOR_OWN_LABEL_SECONDARY', 'var(--color-on-clay)'),
    ],
    'single_future' => [
        'bg' => env('CAL_COLOR_SINGLE_FUTURE_BG', 'var(--color-cell-booked)'),
        'bg_hover' => env('CAL_COLOR_SINGLE_FUTURE_BG_HOVER', 'var(--color-line)'),
        'label' => env('CAL_COLOR_SINGLE_FUTURE_LABEL', 'var(--color-ink)'),
        'label_secondary' => env('CAL_COLOR_SINGLE_FUTURE_LABEL_SECONDARY', 'var(--color-ink-2)'),
    ],
    'series' => [
        'accent' => env('CAL_COLOR_SERIES_ACCENT', 'var(--color-line-strong)'),
        'label' => env('CAL_COLOR_SERIES_LABEL', 'var(--color-ink-2)'),
    ],
    'past' => [
        'bg' => env('CAL_COLOR_PAST_BG', 'var(--color-cell-past-a)'),
        'bg_alt' => env('CAL_COLOR_PAST_BG_ALT', 'var(--color-cell-past-b)'),
        'label' => env('CAL_COLOR_PAST_LABEL', 'var(--color-ink-3)'),
    ],
    'event' => [
        'bg' => env('CAL_COLOR_EVENT_BG', 'var(--color-court)'),
        'bg_hover' => env('CAL_COLOR_EVENT_BG_HOVER', 'var(--color-court-ink)'),
        'accent' => env('CAL_COLOR_EVENT_ACCENT', 'var(--color-court)'),
        'label' => env('CAL_COLOR_EVENT_LABEL', 'var(--color-on-court)'),
    ],
    'tournament' => [
        'bg' => env('CAL_COLOR_TOURNAMENT_BG', 'var(--color-court-soft)'),
        'line' => env('CAL_COLOR_TOURNAMENT_LINE', 'var(--color-court)'),
        'label' => env('CAL_COLOR_TOURNAMENT_LABEL', 'var(--color-court-ink)'),
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
];
