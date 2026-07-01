<?php

declare(strict_types=1);

return [
    'name' => env('BOOKING_NAME', env('APP_NAME', 'Tennis-Booking')),
    'logo_path' => env('BOOKING_LOGO_PATH', 'imgs-client/layout/client-logo.jpg'),
    'square_names' => [
        '1' => env('BOOKING_SQUARE_1_NAME', 'Platz1'),
        '2' => env('BOOKING_SQUARE_2_NAME', 'Platz2'),
        '3' => env('BOOKING_SQUARE_3_NAME', 'Platz3'),
    ],
    'peak_limit' => [
        'window_1_start' => '08:00',
        'window_1_end'   => '12:00',
        'window_2_start' => '17:00',
        'window_2_end'   => '21:00',
    ],
];

