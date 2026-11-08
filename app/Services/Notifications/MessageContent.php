<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/** Content rebuilt at send time; lives only in memory, never in the outbox table. */
final readonly class MessageContent
{
    /**
     * @param  array<int|string, string>  $variables  template variables by position ("1", "2", ...)
     * @param  string  $freeText  full text for sandbox mode
     */
    public function __construct(
        public array $variables,
        public ?string $buttonSuffix,
        public string $freeText,
    ) {}
}
