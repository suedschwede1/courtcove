<?php

declare(strict_types=1);

namespace App\Services\Notifications\Transport;

use App\Services\Notifications\WhatsAppSettings;

final class TransportFactory
{
    public function __construct(private readonly WhatsAppSettings $settings) {}

    public function make(): WhatsAppTransport
    {
        return match ($this->settings->effectiveDriver()) {
            'live' => app(ProviderTransport::class),
            'fake' => app(FakeTransport::class),
            default => new LogTransport,
        };
    }
}
