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
 * The rows of the account card "WhatsApp-Benachrichtigungen", one per consent purpose of the registry (route
 * parameter `{purpose}`; without it the player search purpose, so the original URLs keep working; an unregistered
 * purpose is a 404): switching a row on gives the consent (and subscribes the purpose's opt-in types), the row's
 * settings save its types and filters, switching it off withdraws the consent (which deletes the subscriptions of
 * the purpose, saved filters included). Every action works on the logged-in user's own consent only (no id in
 * the request).
 */
final class NotificationSettingsController extends Controller
{
    public function __construct(
        private readonly ConsentService $consents,
        private readonly NotificationRegistry $registry,
        private readonly AccountCard $card,
    ) {}

    /**
     * Give the consent of the purpose: the row switch posts `consent=1` together with `consent_version`, the version
     * of the consent sentence the user saw. Refused without both, when that version is no longer the purpose's
     * current one (nothing is stored, the user switches on again under the new text) and when the purpose's complete
     * gate fails. Answers on the row's anchor.
     */
    public function enable(Request $request, ?string $purpose = null): RedirectResponse
    {
        $definition = $this->purpose($purpose);
        // A purpose without a switched-on subscribable type is not offered, so there is nothing to consent to
        // (an existing consent is answered by optIn() as before).
        abort_if(! $this->card->offered($definition->key) && $this->consents->activeConsent($this->user($request), $definition->key) === null, 404);

        $required = __('booking.notifications.account_card.consent_required');
        $validator = Validator::make($request->all(), [
            'consent' => ['accepted'],
            'consent_version' => ['required', 'string'],
        ], ['consent.accepted' => $required, 'consent_version.required' => $required, 'consent_version.string' => $required]);
        $validator->after(function ($validator) use ($request, $definition): void {
            $seen = $request->input('consent_version');
            if (is_string($seen) && $seen !== '' && $seen !== $definition->consentVersion()) {
                $validator->errors()->add('consent', __('booking.notifications.account_card.consent_outdated'));
            }
        });
        if ($validator->fails()) {
            // One message at the row, under the consent key the card shows.
            return $this->back($definition->key)->withErrors(['consent' => $validator->errors()->first()])->withInput();
        }

        try {
            $this->consents->optIn($this->user($request), $definition->key, app()->getLocale());
        } catch (ConsentRefused $e) {
            return $this->rowStatus($definition->key, 'problem', __(self::refusedKey($e->reason)));
        }

        return $this->rowStatus($definition->key, 'good', __('booking.notifications.account_card.enabled_flash'));
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

        return $this->rowStatus($definition->key, 'good', __('booking.notifications.account_card.revoked_flash'));
    }

    /**
     * Back on the purpose's row with a short status shown inline in that row (session key `whatsapp_row`), not the
     * global modal flash: switching should not cover the card or take the focus.
     *
     * @param  'good'|'problem'  $tone
     */
    private function rowStatus(string $purpose, string $tone, string $text): RedirectResponse
    {
        return $this->back($purpose)->with('whatsapp_row', ['purpose' => $purpose, 'tone' => $tone, 'text' => $text]);
    }

    /** Account page anchor of a purpose's row in the card (the row the switch was in). */
    public static function rowUrl(string $purpose): string
    {
        return route('account.edit').'#whatsapp-'.$purpose;
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

    /** Back to the account page, on the purpose's row when given. */
    private function back(?string $purpose = null): RedirectResponse
    {
        return $purpose === null ? redirect()->route('account.edit') : redirect()->to(self::rowUrl($purpose));
    }
}
