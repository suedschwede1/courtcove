<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use App\Notifications\Types\NotificationType;
use Carbon\CarbonInterface;

/**
 * What a notification hands to the WhatsApp outbox channel. Only the keys are stored (type, subject,
 * dedupe key, due time and `subjectPayload` with IDs). `$variables` and `$buttonSuffix` are never
 * persisted: the subject resolver rebuilds the content at send time, so no name or text lands in the
 * outbox table.
 */
final readonly class WhatsAppMessage
{
    /**
     * @param  array<int|string, string>  $variables  ordered template variables (not stored)
     * @param  array<string, int|string|null>  $subjectPayload  IDs and keys only, never text, names or numbers
     */
    public function __construct(
        public NotificationType $type,
        public array $variables,
        public ?string $buttonSuffix,
        public string $dedupeKey,
        public ?string $subjectType,
        public ?int $subjectId,
        public CarbonInterface $notBefore,
        public array $subjectPayload = [],
    ) {}
}
