<?php

declare(strict_types=1);

namespace App\Services\Notifications\Transport;

interface WhatsAppTransport
{
    /**
     * @param  array<int|string, string>  $vars  template variables by position ("1", "2", ...)
     */
    public function send(string $toE164, TemplateRef $template, array $vars, ?string $buttonSuffix): TransportResult;

    /** Connection check; reports status only and never sends a message. */
    public function probe(): ProbeResult;
}
