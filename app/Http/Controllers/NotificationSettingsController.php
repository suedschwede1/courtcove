<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationConsent;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\Notifications\Registry\NotificationPurposeDefinition;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Registry\SubscriptionFilters;
use App\Services\Notifications\AccountCard;
use App\Services\Notifications\ConsentRefused;
use App\Services\Notifications\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;

/**
 * The account cards "WhatsApp-Benachrichtigungen": consent, type switches with filters and withdrawal, per
 * consent purpose of the registry (route parameter `{purpose}`; without it the player search purpose, so the
 * original URLs keep working; an unregistered purpose is a 404). Every action works on the logged-in user's own
 * consent only (no id in the request).
 */
final class NotificationSettingsController extends Controller
{
    public function __construct(
        private readonly ConsentService $consents,
        private readonly NotificationRegistry $registry,
        private readonly AccountCard $card,
    ) {}

    /** Give the consent of the purpose (needs the ticked checkbox and the complete gate of the purpose). */
    public function enable(Request $request, ?string $purpose = null): RedirectResponse
    {
        $definition = $this->purpose($purpose);
        $request->validate(
            ['consent' => ['accepted']],
            ['consent.accepted' => __('booking.notifications.account_card.consent_required')],
        );

        try {
            $this->consents->optIn($this->user($request), $definition->key, app()->getLocale());
        } catch (ConsentRefused $e) {
            return $this->back()->with('error', __(self::refusedKey($e->reason)));
        }

        return $this->back()->with('success', __('booking.notifications.account_card.enabled_flash'));
    }

    /**
     * Switch the purpose's types on or off and save their filters; the consent itself stays. Field names:
     * `on[<type slug>]` and `filters[<type slug>][...]` (rules derived from the type's filter definition).
     */
    public function update(Request $request, ?string $purpose = null): RedirectResponse
    {
        $definition = $this->purpose($purpose);
        $user = $this->user($request);
        if ($this->consents->activeConsent($user, $definition->key) === null) {
            return $this->back()->with('error', __('booking.notifications.account_card.no_consent_flash'));
        }

        $types = $this->card->switchableTypes($definition->key);
        $rules = [];
        $attributes = [];
        foreach ($types as $type) {
            $rules['on.'.$type->slug()] = ['nullable', 'boolean'];
            $rules += SubscriptionFilters::rules($type);
            $attributes += SubscriptionFilters::attributes($type);
        }
        $data = Validator::make($request->all(), $rules, [], $attributes)->validate();

        foreach ($types as $type) {
            $query = NotificationSubscription::query()
                ->where('user_id', $user->getKey())
                ->where('channel', ConsentService::CHANNEL)
                ->where('type', $type->key);

            if (! filter_var($data['on'][$type->slug()] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $query->delete();

                continue;
            }

            $existing = $query->first();
            $input = (array) ($data['filters'][$type->slug()] ?? []);
            $filters = SubscriptionFilters::normalise($type, $input, is_array($existing?->filters) ? $existing->filters : []);
            if ($existing === null) {
                NotificationSubscription::create([
                    'user_id' => $user->getKey(),
                    'type' => $type->key,
                    'channel' => ConsentService::CHANNEL,
                    'filters' => $filters,
                ]);
            } else {
                $existing->update(['filters' => $filters]);
            }
        }

        return $this->back()->with('success', __('booking.notifications.account_card.saved_flash'));
    }

    /** Withdraw the consent of the purpose (works even when the main switch is off). */
    public function revoke(Request $request, ?string $purpose = null): RedirectResponse
    {
        $definition = $this->purpose($purpose);
        $this->consents->revoke($this->user($request), 'account', $definition->key);

        return $this->back()->with('success', __('booking.notifications.account_card.revoked_flash'));
    }

    /** Text key of a refusal: the reason's own text, else the generic one. */
    public static function refusedKey(?string $reason): string
    {
        $key = 'booking.notifications.refused.'.$reason;

        return $reason !== null && $reason !== '' && Lang::has($key) ? $key : 'booking.notifications.refused.unavailable';
    }

    private function purpose(?string $key): NotificationPurposeDefinition
    {
        $definition = $this->registry->purpose($key ?? NotificationConsent::PURPOSE_PLAYER_SEARCH);
        abort_if($definition === null, 404);

        return $definition;
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
