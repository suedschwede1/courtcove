<?php

declare(strict_types=1);

namespace App\Services\Notifications\Transport;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Default driver: writes a line without number and text and pretends the message was accepted. */
final class LogTransport implements WhatsAppTransport
{
    public function send(string $toE164, TemplateRef $template, array $vars, ?string $buttonSuffix): TransportResult
    {
        $digits = preg_replace('/\D/', '', $toE164) ?? '';
        $sid = 'log-'.Str::uuid()->toString();

        Log::info('whatsapp log transport', [
            'type' => $template->key,
            'to_tail' => '…'.substr($digits, -2),
            'sid' => $sid,
        ]);

        return TransportResult::accepted($sid);
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(true, 'log');
    }
}
