<?php

declare(strict_types=1);

return [
    'name' => env('BOOKING_NAME', env('APP_NAME', 'Tennis-Booking')),
    'logo_path' => env('BOOKING_LOGO_PATH', 'imgs-client/layout/client-logo.jpg'),
    'logo_width' => (int) env('BOOKING_LOGO_WIDTH', 112),
    'logo_height' => (int) env('BOOKING_LOGO_HEIGHT', 108),
    'square_names' => [
        '1' => env('BOOKING_SQUARE_1_NAME', 'Court B'),
        '2' => env('BOOKING_SQUARE_2_NAME', 'Court D'),
        '3' => env('BOOKING_SQUARE_3_NAME', 'Court C'),
    ],
];
