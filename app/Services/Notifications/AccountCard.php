<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\Notifications\Registry\NotificationPurposeDefinition;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Registry\NotificationTypeDefinition;
use App\Notifications\Registry\SubscriptionFilters;
use Illuminate\Support\Carbon;

/**
 * View state of the account card "WhatsApp-Benachrichtigungen": one number section for every purpose and a list
 * with one row per consent purpose of the registry. The whole card is hidden while the main switch is off.
 *
 * Number section: the masked number, and the reason when the account or the number keeps the user out (not
 * active, no, invalid or no mobile number); then every row that is not on is locked. A revocation notice (STOP,
 * changed number) that concerns every row stands here once instead of in each row.
 *
 * Row (purpose): shown when at least one of its subscribable types (not the admin test, not a follow-up) is
 * switched on, or when the user still holds an active consent for it (then only switching off is left when no
 * type is on). State locked (gate reason of the account or of the purpose), ready (off, may consent) or active
 * (consent given for the current number). A row that is on means: consent given and the purpose's opt-in types
 * subscribed; switching it off withdraws the consent. Types with filters, and every type when a purpose has
 * several or one that is not subscribed, show in the row's settings. No member record is needed. Never carries
 * the full number.
 */
final class AccountCard
{
    /** Gate reasons that the user fixes in the profile (link to the phone field). */
    private const PHONE_REASONS = ['no_phone', 'phone_invalid', 'phone_not_mobile'];

    public function __construct(
        private readonly ConsentService $consents,
        private readonly WhatsAppSettings $settings,
        private readonly NotificationRegistry $registry,
    ) {}

    /**
     * @return array{state: 'hidden'|'shown', maskedPhone: string|null, reason: string|null, phoneReason: bool, paused: bool, notice: array{kind: string, date: Carbon|null}|null, purposes: list<array<string, mixed>>}
     */
    public function for(User $user): array
    {
        $card = ['state' => 'hidden', 'maskedPhone' => null, 'reason' => null, 'phoneReason' => false, 'paused' => false, 'notice' => null, 'purposes' => []];
        if (! $this->settings->enabled()) {
            return $card;
        }

        // The gate without a purpose: account and number, the same for every row.
        $account = $this->consents->gate($user, false, null);
        $accountReason = $account->passed ? null : (string) $account->reason;

        $rows = [];
        foreach ($this->registry->purposes() as $purpose) {
            $row = $this->row($user, $purpose, $accountReason);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        if ($rows === []) {
            return $card;
        }

        $anyActive = array_filter($rows, fn (array $row): bool => $row['state'] === 'active') !== [];
        $card = [
            'state' => 'shown',
            'maskedPhone' => $this->mask($account->e164, $account->last2),
            'reason' => $accountReason,
            'phoneReason' => in_array($accountReason, self::PHONE_REASONS, true),
            'paused' => $accountReason !== null && $anyActive,
            'notice' => null,
            'purposes' => $rows,
        ];

        // A notice that every row shares (one STOP or one number change ends all consents) is said once.
        $notices = array_map(fn (array $row): ?array => $row['notice'], $rows);
        $signatures = array_unique(array_map(
            fn (?array $notice): string => $notice === null ? '' : $notice['kind'].'|'.($notice['date']?->toDateString() ?? ''),
            $notices,
        ));
        if (count($signatures) === 1 && $notices[0] !== null) {
            $card['notice'] = $notices[0];
            foreach (array_keys($card['purposes']) as $i) {
                $card['purposes'][$i]['notice'] = null;
            }
        }

        return $card;
    }

    /**
     * Subscribable types of a purpose that are switched on (the types of its row).
     *
     * @return list<NotificationTypeDefinition>
     */
    public function switchableTypes(string $purpose): array
    {
        return array_values(array_filter(
            $this->registry->types(),
            fn (NotificationTypeDefinition $t): bool => $t->purpose === $purpose && $t->isSubscribable() && $this->settings->typeEnabled($t->key),
        ));
    }

    /**
     * Whether the purpose is offered for a new consent: at least one subscribable type of it is switched on (a
     * follow-up or the admin test alone does not count, they have no subscription of their own).
     */
    public function offered(string $purpose): bool
    {
        return $this->switchableTypes($purpose) !== [];
    }

    /** @return array<string, mixed>|null */
    private function row(User $user, NotificationPurposeDefinition $purpose, ?string $accountReason): ?array
    {
        $consent = $this->consents->activeConsent($user, $purpose->key);
        if (! $this->offered($purpose->key) && $consent === null) {
            return null;
        }

        $gate = $this->consents->gate($user, false, $purpose->key);
        // The purpose's own reason; one of the account or the number stands in the number section.
        $purposeReason = $gate->passed || $accountReason !== null ? null : (string) $gate->reason;
        $row = [
            'key' => $purpose->key,
            'label' => (string) __($purpose->labelKey),
            'description' => (string) __($purpose->descriptionKey),
            'consentText' => (string) __($purpose->consentTextKey),
            'consentVersion' => $purpose->consentVersion(),
            'state' => 'ready',
            'reason' => null,
            'consentedAt' => null,
            'pausedReason' => null,
            'notice' => null,
            'types' => [],
        ];

        if ($consent !== null && $this->consents->matchesCurrentPhone($consent, $user)) {
            return [
                'state' => 'active',
                'consentedAt' => $consent->consented_at,
                'pausedReason' => $purposeReason,
                'types' => $this->types($user, $purpose),
            ] + $row;
        }

        $row['notice'] = $this->revocationNotice($user, $purpose->key);
        if (! $gate->passed) {
            $row['state'] = 'locked';
            $row['reason'] = $purposeReason;
        }

        return $row;
    }

    /**
     * The row's types with their subscription state and filter values. `choice`: the type gets its own box in
     * the row's settings (several types, a type that is not subscribed with the consent, or one that is off);
     * otherwise it simply follows the row switch.
     *
     * @return list<array<string, mixed>>
     */
    private function types(User $user, NotificationPurposeDefinition $purpose): array
    {
        $subscriptions = NotificationSubscription::query()
            ->where('user_id', $user->getKey())
            ->where('channel', ConsentService::CHANNEL)
            ->whereIn('type', $this->registry->typesOfPurpose($purpose->key))
            ->get()
            ->keyBy('type');

        $switchable = $this->switchableTypes($purpose->key);
        $types = [];
        foreach ($switchable as $type) {
            $subscription = $subscriptions->get($type->key);
            $types[] = [
                'key' => $type->key,
                'slug' => $type->slug(),
                'label' => (string) __($type->labelKey),
                'description' => (string) __($type->descriptionKey),
                'on' => $subscription !== null,
                'choice' => count($switchable) > 1 || ! $type->optIn || $subscription === null,
                'filters' => $type->filters,
                'values' => SubscriptionFilters::values($type, is_array($subscription?->filters) ? $subscription->filters : null),
            ];
        }

        return $types;
    }

    /** "+43 … 42": calling code (only known for +43 and +49) and the last two digits. */
    private function mask(?string $e164, ?string $last2): ?string
    {
        if ($e164 === null || $last2 === null) {
            return null;
        }
        $prefix = preg_match('/^\+(43|49)/', $e164, $m) === 1 ? '+'.$m[1].' ' : '';

        return $prefix.'… '.$last2;
    }

    /**
     * The latest end of a consent of the purpose that the user did not cause in the account: STOP or a changed number.
     *
     * @return array{kind: 'stop'|'phone_changed', date: Carbon|null}|null
     */
    private function revocationNotice(User $user, string $purpose): ?array
    {
        $revoked = NotificationConsent::query()
            ->where('user_id', $user->getKey())
            ->where('channel', ConsentService::CHANNEL)
            ->where('purpose', $purpose)
            ->whereNotNull('revoked_at')
            ->latest('id')
            ->first();

        return match ($revoked?->revoked_via) {
            'stop' => ['kind' => 'stop', 'date' => $revoked->revoked_at],
            'phone_changed' => ['kind' => 'phone_changed', 'date' => $revoked->revoked_at],
            default => null,
        };
    }
}
