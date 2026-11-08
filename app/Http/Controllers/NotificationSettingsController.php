<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationConsent;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\Services\Notifications\AccountCard;
use App\Services\Notifications\ConsentRefused;
use App\Services\Notifications\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The account card "WhatsApp-Benachrichtigungen": consent, filters and withdrawal. Every action works on the
 * logged-in user's own consent only (no id in the request).
 */
final class NotificationSettingsController extends Controller
{
    private const KNOWN_REASONS = [
        'disabled', 'account_inactive', 'no_member', 'member_inactive', 'no_phone', 'phone_invalid', 'phone_not_mobile', 'exchange_off', 'conflict',
    ];

    public function __construct(private readonly ConsentService $consents) {}

    /** Give the consent (needs the ticked checkbox and the complete gate). */
    public function enable(Request $request): RedirectResponse
    {
        $request->validate(
            ['consent' => ['accepted']],
            ['consent.accepted' => __('booking.notifications.account_card.consent_required')],
        );

        try {
            $this->consents->optIn($this->user($request), NotificationConsent::PURPOSE_PLAYER_SEARCH, app()->getLocale());
        } catch (ConsentRefused $e) {
            $reason = in_array($e->reason, self::KNOWN_REASONS, true) ? $e->reason : 'conflict';

            return $this->back()->with('error', __('booking.notifications.refused.'.$reason));
        }

        return $this->back()->with('success', __('booking.notifications.account_card.enabled_flash'));
    }

    /** Switch the player search messages on or off and save their filters; the consent itself stays. */
    public function update(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        if ($this->consents->activeConsent($user) === null) {
            return $this->back()->with('error', __('booking.notifications.account_card.no_consent_flash'));
        }

        $data = $request->validate([
            'search' => ['nullable', 'boolean'],
            'weekdays' => ['nullable', 'array', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'time_from' => ['nullable', 'date_format:H:i', 'required_with:time_to', 'before:time_to'],
            'time_to' => ['nullable', 'date_format:H:i', 'required_with:time_from'],
            'quantities' => ['nullable', 'array', 'max:2'],
            'quantities.*' => ['integer', 'in:2,4'],
        ]);

        $query = NotificationSubscription::query()
            ->where('user_id', $user->getKey())
            ->where('channel', ConsentService::CHANNEL)
            ->where('type', AccountCard::SEARCH_TYPE);

        if (! $request->boolean('search')) {
            $query->delete();
        } else {
            $existing = $query->first();
            $filters = $this->filters($data, is_array($existing?->filters) ? $existing->filters : []);
            if ($existing === null) {
                NotificationSubscription::create([
                    'user_id' => $user->getKey(),
                    'type' => AccountCard::SEARCH_TYPE,
                    'channel' => ConsentService::CHANNEL,
                    'filters' => $filters,
                ]);
            } else {
                $existing->update(['filters' => $filters]);
            }
        }

        return $this->back()->with('success', __('booking.notifications.account_card.saved_flash'));
    }

    /** Withdraw the consent (works even when the main switch is off). */
    public function revoke(Request $request): RedirectResponse
    {
        $this->consents->revoke($this->user($request), 'account', NotificationConsent::PURPOSE_PLAYER_SEARCH);

        return $this->back()->with('success', __('booking.notifications.account_card.revoked_flash'));
    }

    /**
     * Normalised filters: weekdays and quantities sorted and unique, times as given; an empty selection leaves
     * the key out and no filters at all is stored as null (everything). Other keys (courts) are kept.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>|null
     */
    private function filters(array $data, array $existing): ?array
    {
        $filters = array_diff_key($existing, array_flip(['weekdays', 'time_from', 'time_to', 'quantities']));

        $weekdays = array_values(array_unique(array_map('intval', (array) ($data['weekdays'] ?? []))));
        sort($weekdays);
        if ($weekdays !== []) {
            $filters['weekdays'] = $weekdays;
        }
        if (($data['time_from'] ?? null) !== null && ($data['time_to'] ?? null) !== null) {
            $filters['time_from'] = (string) $data['time_from'];
            $filters['time_to'] = (string) $data['time_to'];
        }
        $quantities = array_values(array_unique(array_map('intval', (array) ($data['quantities'] ?? []))));
        sort($quantities);
        if ($quantities !== []) {
            $filters['quantities'] = $quantities;
        }

        return $filters === [] ? null : $filters;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('account.edit');
    }
}
