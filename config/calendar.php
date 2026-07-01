<?php

declare(strict_types=1);

$colors = [
    'free' => [
        'bg' => env('CAL_COLOR_FREE_BG', '#ffffff'),
        'bg_hover' => env('CAL_COLOR_FREE_BG_HOVER', '#ffffff'),
    ],
    'own' => [
        'bg' => env('CAL_COLOR_OWN_BG', '#d8e9fb'),
        'bg_hover' => env('CAL_COLOR_OWN_BG_HOVER', '#cfe1f4'),
        'accent' => env('CAL_COLOR_OWN_ACCENT', 'rgba(26, 103, 179, 0.45)'),
        'label' => env('CAL_COLOR_OWN_LABEL', '#1a3a6b'),
        'label_secondary' => env('CAL_COLOR_OWN_LABEL_SECONDARY', '#5a7ab3'),
    ],
    'single_future' => [
        'bg' => env('CAL_COLOR_SINGLE_FUTURE_BG', '#dbe8f6'),
        'bg_hover' => env('CAL_COLOR_SINGLE_FUTURE_BG_HOVER', '#d2e0ef'),
    ],
    'series' => [
        'accent' => env('CAL_COLOR_SERIES_ACCENT', '#bf4316'),
        'label' => env('CAL_COLOR_SERIES_LABEL', '#7f2010'),
    ],
    'past' => [
        'bg' => env('CAL_COLOR_PAST_BG', '#f4f4f4'),
        'label' => env('CAL_COLOR_PAST_LABEL', '#8a8d90'),
    ],
    'event' => [
        'bg' => env('CAL_COLOR_EVENT_BG', '#fde8e1'),
        'bg_hover' => env('CAL_COLOR_EVENT_BG_HOVER', '#fbded5'),
        'accent' => env('CAL_COLOR_EVENT_ACCENT', 'rgba(191, 67, 22, 0.55)'),
        'label' => env('CAL_COLOR_EVENT_LABEL', '#7f2010'),
    ],
];

// Optional, per-installation file (not tracked in git) to override individual
// color values without touching this file. See calendar.local.example.php.
$localFile = __DIR__ . '/calendar.local.php';
if (file_exists($localFile)) {
    $colors = array_replace_recursive($colors, require $localFile);
}

return [
    'colors' => $colors,
];
