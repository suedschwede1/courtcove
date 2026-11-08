<?php

declare(strict_types=1);

namespace App\Services\Notifications\Transport;

use Illuminate\Support\Str;

/** Test double: remembers calls, the result is steerable. */
final class FakeTransport implements WhatsAppTransport
{
    /** @var list<array{to: string, template: TemplateRef, vars: array<int|string, string>, buttonSuffix: ?string}> */
    private array $sent = [];

    private ?TransportResult $result = null;

    private ProbeResult $probe;

    public function __construct()
    {
        $this->probe = new ProbeResult(true, 'fake');
    }

    public function willReturn(TransportResult $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function willProbe(ProbeResult $probe): self
    {
        $this->probe = $probe;

        return $this;
    }

    public function send(string $toE164, TemplateRef $template, array $vars, ?string $buttonSuffix): TransportResult
    {
        $this->sent[] = ['to' => $toE164, 'template' => $template, 'vars' => $vars, 'buttonSuffix' => $buttonSuffix];

        return $this->result ?? TransportResult::accepted('fake-'.Str::uuid()->toString());
    }

    public function probe(): ProbeResult
    {
        return $this->probe;
    }

    /** @return list<array{to: string, template: TemplateRef, vars: array<int|string, string>, buttonSuffix: ?string}> */
    public function sent(): array
    {
        return $this->sent;
    }
}
