<?php

declare(strict_types=1);

namespace App\Notifications\Registry;

use App\Models\User;
use Closure;
use InvalidArgumentException;

/**
 * A consent purpose (stored in `notification_consents.purpose`): its texts, the version recorded with
 * each consent and an optional extra gate prerequisite. The types of a purpose follow from the type
 * definitions ({@see NotificationRegistry::typesOfPurpose()}); revoking the purpose deletes their
 * subscriptions.
 *
 * `gateCondition` is a {@see PurposeGateCondition}, its class name (resolved from the container) or a
 * closure `fn (User $user): ?string`; null means no prerequisite beyond the general gate.
 */
final readonly class NotificationPurposeDefinition
{
    private const KEY_PATTERN = '/^[a-z0-9_]{1,32}$/';

    /**
     * @param  PurposeGateCondition|class-string<PurposeGateCondition>|(Closure(User): ?string)|null  $gateCondition
     */
    public function __construct(
        public string $key,
        public string $labelKey,
        public string $descriptionKey,
        public string $consentTextKey,
        public string $consentVersionKey,
        public PurposeGateCondition|Closure|string|null $gateCondition = null,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("Invalid consent purpose key [$key].");
        }
    }

    /** Version of the consent text, stored with every consent of this purpose (max. 32 characters). */
    public function consentVersion(): string
    {
        return substr((string) trans($this->consentVersionKey), 0, 32);
    }

    /** Gate reason when the account misses the purpose's own prerequisite, null when it qualifies. */
    public function gateFailure(User $user): ?string
    {
        $condition = $this->gateCondition;
        if ($condition === null) {
            return null;
        }
        if ($condition instanceof Closure) {
            return $condition($user);
        }
        if (is_string($condition)) {
            $condition = app($condition);
        }

        return $condition instanceof PurposeGateCondition ? $condition->failure($user) : null;
    }
}
