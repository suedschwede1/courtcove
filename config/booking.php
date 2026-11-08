<?php

declare(strict_types=1);

return [
    'name' => env('BOOKING_NAME', env('APP_NAME', 'Tennis-Booking')),
    'watermark' => 'src:hm-'.hash('crc32b', 'Heinz Mayer|bookingnew'),
    'logo_path' => env('BOOKING_LOGO_PATH', ''),
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
    // Decision point (see App\Support\AdminMode::tokenRequestsCountAsActive()): requests authenticated
    // with an API token (REST API, MCP) count as admin mode active; false makes them behave like members.
    'admin_mode' => [
        'api_tokens_active' => (bool) env('ADMIN_MODE_API_TOKENS_ACTIVE', true),
    ],
    // PHP CLI binary for the cron line shown on the admin page "Geplante Aufgaben" (cron may use another PHP
    // than the web server: use the full path of the PHP 8.4 CLI of the hosting).
    'scheduler' => [
        'php_binary' => env('SCHEDULER_PHP_BINARY', 'php'),
    ],
    'login_verification' => [
        'code_ttl_minutes' => 10,
        'max_attempts' => 5,
        // Pause between two codes of one account (a new code is always possible, only after this wait).
        'resend_decay_seconds' => 30,
        // Codes per account, hour and IP address, and per account and hour in total, against mail flooding.
        // Only waiting times, never a permanent lock.
        'max_sends_per_hour' => 5,
        'max_sends_per_hour_total' => 15,
    ],
    // Trusted devices (see App\Services\TrustedDeviceService): privileged accounts must sign in from a device
    // that was confirmed once by e-mail code. Fixed validity from issue, no renewal ("once a month").
    // scope: 'privileged' (admins and assistants, default), 'all' (every account) or 'off' (emergency switch,
    // e.g. when the mail system is down; see docs/security-runbook.md).
    'security' => [
        'trusted_device_days' => (int) env('BOOKING_TRUSTED_DEVICE_DAYS', 30),
        'trusted_device_scope' => (string) env('BOOKING_TRUSTED_DEVICE_SCOPE', 'privileged'),
    ],
];
