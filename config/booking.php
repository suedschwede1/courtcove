<?php

declare(strict_types=1);

return [
    'name' => env('BOOKING_NAME', env('APP_NAME', 'ExampleClub-Booking')),
    'logo_path' => env('BOOKING_LOGO_PATH', 'imgs-client/layout/client-logo.jpg'),
    'logo_width' => (int) env('BOOKING_LOGO_WIDTH', 112),
    'logo_height' => (int) env('BOOKING_LOGO_HEIGHT', 108),
];