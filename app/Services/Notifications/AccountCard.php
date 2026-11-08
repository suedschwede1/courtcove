<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * View state of the account card "WhatsApp-Benachrichtigungen": hidden (main switch off), locked (a gate
 * reason keeps the member out), ready (may consent) or active (consent given). Never carries the full number.
 */
final class AccountCard
{
    /** Notification type switched by the "player search" switch of the card. */
    public const SEARCH_TYPE = 'player_search.opened';

    /** Gate reasons that the member fixes in the profile (link to the phone field). */
    private const PHONE_REASONS = ['no_phone', 'phone_invalid', 'phone_not_mobile'];

    public function __construct(
        private readonly ConsentService $consents,
        private readonly WhatsAppSettings $settings,
    ) {}

    /**
     * @return array{
     *     state: 'hidden'|'locked'|'ready'|'active',
     *     reason: string|null,
     *     phoneReason: bool,
     *     maskedPhone: string|null,
     *     consentedAt: Carbon|null,
     *     searchOn: bool,
     *     filters: array{weekdays: list<int>, time_from: string, time_to: string, quantities: list<int>},
     *     pausedReason: string|null,
     *     notice: array{kind: 'stop'|'phone_changed', date: Carbon|null}|null
     * }
     */
    public function for(User $user): array
    {
        $card = [
            'state' => 'hidden',
            'reason' => null,
            'phoneReason' => false,
            'maskedPhone' => null,
            'consentedAt' => null,
            'searchOn' => false,
            'filters' => ['weekdays' => [], 'time_from' => '', 'time_to' => '', 'quantities' => []],
            'pausedReason' => null,
            'notice' => null,
        ];

        if (! $this->settings->enabled()) {
            return $card;
        }

        $gate = $this->consents->gate($user);
        $card['maskedPhone'] = $this->mask($gate->e164, $gate->last2);

        $consent = $this->consents->activeConsent($user);
        if ($consent !== null && $this->consents->matchesCurrentPhone($consent, $user)) {
            $subscription = NotificationSubscription::query()
                ->where('user_id', $user->getKey())
                ->where('channel', ConsentService::CHANNEL)
                ->where('type', self::SEARCH_TYPE)
                ->first();
            $filters = is_array($subscription?->filters) ? $subscription->filters : [];

            return [
                'state' => 'active',
                'consentedAt' => $consent->consented_at,
                'searchOn' => $subscription !== null,
                'filters' => [
                    'weekdays' => array_values(array_map('intval', (array) ($filters['weekdays'] ?? []))),
                    'time_from' => (string) ($filters['time_from'] ?? ''),
                    'time_to' => (string) ($filters['time_to'] ?? ''),
                    'quantities' => array_values(array_map('intval', (array) ($filters['quantities'] ?? []))),
                ],
                'pausedReason' => $gate->passed ? null : (string) $gate->reason,
            ] + $card;
        }

        $card['notice'] = $this->revocationNotice($user);
        if (! $gate->passed) {
            $card['state'] = 'locked';
            $card['reason'] = (string) $gate->reason;
            $card['phoneReason'] = in_array($gate->reason, self::PHONE_REASONS, true);

            return $card;
        }

        $card['state'] = 'ready';

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

    /** The latest end of a consent that the member did not cause in the account: STOP or a changed number. */
    private function revocationNotice(User $user): ?array
    {
        $revoked = NotificationConsent::query()
            ->where('user_id', $user->getKey())
            ->where('channel', ConsentService::CHANNEL)
            ->where('purpose', NotificationConsent::PURPOSE_PLAYER_SEARCH)
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
