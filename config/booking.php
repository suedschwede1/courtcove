<?php

declare(strict_types=1);

return [
    'name' => env('BOOKING_NAME', env('APP_NAME', 'Tennis-Booking')),
    'watermark' => 'src:hm-' . hash('crc32b', 'Heinz Mayer|bookingnew'),
    'logo_path' => env('BOOKING_LOGO_PATH', 'imgs-client/layout/client-logo.jpg'),
    'square_names' => [
        '1' => env('BOOKING_SQUARE_1_NAME', 'Platz1'),
        '2' => env('BOOKING_SQUARE_2_NAME', 'Platz2'),
        '3' => env('BOOKING_SQUARE_3_NAME', 'Platz3'),
    ],
    'peak_limit' => [
        'window_1_start' => '08:00',
        'window_1_end' => '12:00',
        'window_2_start' => '17:00',
        'window_2_end' => '21:00',
    ],
    'blocked_login_countries' => array_values(array_filter(array_map(
        static fn (string $code): string => strtoupper(trim($code)),
        explode(',', (string) env('BOOKING_BLOCKED_LOGIN_COUNTRIES', ''))
    ))),
    'login_verification' => [
        'code_ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_decay_seconds' => 60,
    ],
];
