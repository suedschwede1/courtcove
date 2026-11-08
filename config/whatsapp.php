<?php

declare(strict_types=1);

/*
 * WhatsApp notifications. Every value here is an .env override: an empty value counts as not set and
 * the admin option (bs_options, `whatsapp.*`) or the built-in default applies. The main switch and the
 * budget have no .env override on purpose (admin only). The provider literals (base URL, endpoint paths,
 * signature header name) have no tracked default: set them via .env or the admin option.
 */
return [
    'driver' => env('WHATSAPP_DRIVER'),
    'account_sid' => env('WHATSAPP_ACCOUNT_SID'),
    'auth_token' => env('WHATSAPP_AUTH_TOKEN'),
    'messaging_service_sid' => env('WHATSAPP_MESSAGING_SERVICE_SID'),
    'sender' => env('WHATSAPP_SENDER'),
    'sandbox' => env('WHATSAPP_SANDBOX'),
    'sandbox_sender' => env('WHATSAPP_SANDBOX_SENDER'),
    'webhook_base_url' => env('WHATSAPP_WEBHOOK_BASE_URL'),
    'allow_live_on_staging' => env('WHATSAPP_ALLOW_LIVE_ON_STAGING'),

    'api_base' => env('WHATSAPP_API_BASE'),
    'api_messages_path' => env('WHATSAPP_API_MESSAGES_PATH'),
    'api_account_path' => env('WHATSAPP_API_ACCOUNT_PATH'),
    'api_service_path' => env('WHATSAPP_API_SERVICE_PATH'),
    'signature_header' => env('WHATSAPP_SIGNATURE_HEADER'),
];
