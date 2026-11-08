<?php

declare(strict_types=1);

namespace App\Notifications\Registry;

use App\Services\Notifications\SubjectResolver;
use InvalidArgumentException;

/**
 * Everything the channel, the dispatcher, the settings and the admin need to know about one notification
 * type. Registered in the {@see NotificationRegistry}; a new type needs only this definition, a resolver
 * and its texts (labels, template).
 *
 * - `key`: stored in `notification_log.type` and `notification_subscriptions.type` (max. 48 characters).
 * - `templateKey`: admin option `whatsapp.template.<templateKey>.<locale>` holds the template ID.
 * - `purpose`: consent purpose that must be active at send time (null: no consent needed).
 * - `defaultEnabled`: switch of the type while option `whatsapp.type.<key>.enabled` is not set.
 * - `filters`: filters a subscriber may set for this type, as data for the account card, e.g.
 *   `['weekdays' => ['kind' => 'weekdays'], 'quantities' => ['kind' => 'choice', 'options' => [2, 4]]]`.
 * - `priority`: lower goes first in the outbox.
 * - `followupOf`: key of the type this one follows up (sent only after that one went out); a follow-up
 *   has no subscription of its own and is switched on or off by its type option alone.
 * - `optIn`: a subscription without filters is created together with the consent of the purpose.
 * - `testType`: the admin test (skips the purpose prerequisite and the daily limit, books the global price).
 * - `legacyEnabledOption`: older option that still switches the type while the per-type option is unset.
 */
final readonly class NotificationTypeDefinition
{
    private const KEY_PATTERN = '/^[a-z0-9_]+(\.[a-z0-9_]+)*$/';

    private const TEMPLATE_KEY_PATTERN = '/^[a-z0-9_]+$/';

    /**
     * @param  class-string<SubjectResolver>  $resolver
     * @param  array<string, array<string, mixed>>  $filters
     */
    public function __construct(
        public string $key,
        public string $labelKey,
        public string $descriptionKey,
        public string $templateKey,
        public string $resolver,
        public ?string $purpose,
        public int $priority = 50,
        public bool $defaultEnabled = true,
        public array $filters = [],
        public ?string $followupOf = null,
        public bool $optIn = false,
        public bool $testType = false,
        public ?string $legacyEnabledOption = null,
    ) {
        if (strlen($key) > 48 || preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("Invalid notification type key [$key].");
        }
        if (strlen($templateKey) > 64 || preg_match(self::TEMPLATE_KEY_PATTERN, $templateKey) !== 1) {
            throw new InvalidArgumentException("Invalid template key [$templateKey] of notification type [$key].");
        }
        if ($followupOf === $key) {
            throw new InvalidArgumentException("Notification type [$key] cannot follow up itself.");
        }
    }

    public function isFollowup(): bool
    {
        return $this->followupOf !== null;
    }
}
