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
 * View state of the account cards "WhatsApp-Benachrichtigungen", one per consent purpose of the registry. The
 * whole block is hidden while the main switch is off. A purpose is shown when at least one of its subscribable
 * types (not the admin test, not a follow-up) is switched on, or when the user still holds an active consent for
 * it (then only withdrawal is left when no type is on).
 * Per purpose: locked (a gate reason of the booking account or of the purpose keeps the user out), ready (may
 * consent) or active (consent given), with one switch per subscribable type and the filters its definition
 * declares. No member record is needed. Never carries the full number.
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
     * @return array{state: 'hidden'|'shown', purposes: list<array<string, mixed>>}
     */
    public function for(User $user): array
    {
        if (! $this->settings->enabled()) {
            return ['state' => 'hidden', 'purposes' => []];
        }

        $purposes = [];
        foreach ($this->registry->purposes() as $purpose) {
            $card = $this->purposeCard($user, $purpose);
            if ($card !== null) {
                $purposes[] = $card;
            }
        }

        return ['state' => $purposes === [] ? 'hidden' : 'shown', 'purposes' => $purposes];
    }

    /**
     * Subscribable types of a purpose that are switched on (the switches of its card).
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
    private function purposeCard(User $user, NotificationPurposeDefinition $purpose): ?array
    {
        $consent = $this->consents->activeConsent($user, $purpose->key);
        if (! $this->offered($purpose->key) && $consent === null) {
            return null;
        }

        $gate = $this->consents->gate($user, false, $purpose->key);
        $card = [
            'key' => $purpose->key,
            'label' => (string) __($purpose->labelKey),
            'description' => (string) __($purpose->descriptionKey),
            'consentText' => (string) __($purpose->consentTextKey),
            'state' => 'ready',
            'reason' => null,
            'phoneReason' => false,
            'maskedPhone' => $this->mask($gate->e164, $gate->last2),
            'consentedAt' => null,
            'pausedReason' => null,
            'notice' => null,
            'types' => [],
        ];

        if ($consent !== null && $this->consents->matchesCurrentPhone($consent, $user)) {
            $subscriptions = NotificationSubscription::query()
                ->where('user_id', $user->getKey())
                ->where('channel', ConsentService::CHANNEL)
                ->whereIn('type', $this->registry->typesOfPurpose($purpose->key))
                ->get()
                ->keyBy('type');

            foreach ($this->switchableTypes($purpose->key) as $type) {
                $subscription = $subscriptions->get($type->key);
                $card['types'][] = [
                    'key' => $type->key,
                    'slug' => $type->slug(),
                    'label' => (string) __($type->labelKey),
                    'description' => (string) __($type->descriptionKey),
                    'on' => $subscription !== null,
                    'filters' => $type->filters,
                    'values' => SubscriptionFilters::values($type, is_array($subscription?->filters) ? $subscription->filters : null),
                ];
            }

            return [
                'state' => 'active',
                'consentedAt' => $consent->consented_at,
                'pausedReason' => $gate->passed ? null : (string) $gate->reason,
            ] + $card;
        }

        $card['notice'] = $this->revocationNotice($user, $purpose->key);
        if (! $gate->passed) {
            $card['state'] = 'locked';
            $card['reason'] = (string) $gate->reason;
            $card['phoneReason'] = in_array($gate->reason, self::PHONE_REASONS, true);
        }

        return $card;
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
