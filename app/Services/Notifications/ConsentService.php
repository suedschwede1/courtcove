<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\Notifications\Registry\NotificationRegistry;
use App\Support\Phone\PhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Consent to WhatsApp notifications: the gate that decides whether a booking account may receive them,
 * opt-in, revocation and the revocation triggers (phone change, account status). The consent row
 * holds an HMAC of the number and its last two digits, never the number. Log lines carry IDs and the
 * reason only, never numbers or texts.
 *
 * The hooks run from model events and User::setMeta(); mass updates fire no events, so the send-time
 * check (gate() and matchesCurrentPhone()) stays the real safeguard.
 */
final class ConsentService
{
    public const CHANNEL = 'whatsapp';

    /** Account states that end every consent (merged storage keeps them as plain status values). */
    private const REVOKING_STATUSES = ['blocked', 'deleted', 'disabled'];

    public function __construct(
        private readonly WhatsAppSettings $settings,
        private readonly NotificationRegistry $registry,
    ) {}

    /**
     * Checks the booking account only, in order: main switch, account enabled (not blocked, disabled or
     * deleted), mobile number in the account, then the purpose's own prerequisite (player search: player
     * exchange switched on; none for a purpose without condition or when `$purpose` is null). A member record
     * (linked or not, in whatever state) plays no part. The admin test skips only the purpose prerequisite,
     * so an admin account can test without being in the exchange.
     */
    public function gate(User $user, bool $forAdminTest = false, ?string $purpose = NotificationConsent::PURPOSE_PLAYER_SEARCH): GateResult
    {
        if (! $this->settings->enabled()) {
            return GateResult::fail('disabled');
        }

        if (! $user->isEnabled()) {
            return GateResult::fail('account_inactive');
        }

        $phone = PhoneNumber::parse($user->getMeta('phone'), $this->settings->defaultCountry());
        if ($phone->error !== null || $phone->e164 === null) {
            return GateResult::fail(match ($phone->error) {
                'not_mobile' => 'phone_not_mobile',
                'invalid' => 'phone_invalid',
                default => 'no_phone',
            }, $phone->e164, $phone->last2());
        }

        $failure = $forAdminTest || $purpose === null ? null : $this->registry->purpose($purpose)?->gateFailure($user);
        if ($failure !== null) {
            return GateResult::fail($failure, $phone->e164, $phone->last2());
        }

        return GateResult::pass($phone->e164, $phone->last2());
    }

    public function activeConsent(User $user, string $purpose = NotificationConsent::PURPOSE_PLAYER_SEARCH): ?NotificationConsent
    {
        return NotificationConsent::query()
            ->where('user_id', $user->getKey())
            ->where('channel', self::CHANNEL)
            ->where('purpose', $purpose)
            ->active()
            ->latest('id')
            ->first();
    }

    /**
     * Records the consent with the text version of the purpose and switches on the purpose's opt-in types
     * (subscription without filters). Idempotent: an active consent for the current number is returned as is.
     *
     * @throws ConsentRefused when the full gate (including the purpose's prerequisite) fails
     * @throws InvalidArgumentException for a purpose that is not registered
     */
    public function optIn(User $user, string $purpose, string $locale): NotificationConsent
    {
        $definition = $this->registry->purpose($purpose) ?? throw new InvalidArgumentException("Unknown consent purpose [$purpose].");

        $gate = $this->gate($user, false, $purpose);
        if (! $gate->passed || $gate->e164 === null) {
            throw new ConsentRefused((string) $gate->reason);
        }

        $hash = $this->phoneHash($gate->e164);

        try {
            return DB::transaction(function () use ($user, $purpose, $definition, $locale, $gate, $hash): NotificationConsent {
                $existing = $this->activeConsent($user, $purpose);
                if ($existing !== null && hash_equals($existing->phone_hash, $hash)) {
                    $this->subscribe($user, $purpose);

                    return $existing;
                }
                // An active row for another number only exists after an event-less mass update.
                $existing?->revoke('phone_changed');

                $consent = NotificationConsent::create([
                    'user_id' => $user->getKey(),
                    'channel' => self::CHANNEL,
                    'purpose' => $purpose,
                    'phone_hash' => $hash,
                    'phone_last2' => (string) $gate->last2,
                    'locale' => substr($locale, 0, 5),
                    'consent_text_version' => $definition->consentVersion(),
                    'consented_at' => Carbon::now(),
                    'active_marker' => NotificationConsent::ACTIVE_MARKER,
                ]);
                $this->subscribe($user, $purpose);

                Log::info('whatsapp consent given', ['user_id' => $user->getKey(), 'purpose' => $purpose]);

                return $consent;
            });
        } catch (UniqueConstraintViolationException) {
            // A parallel request won the race; its row is the consent.
            return $this->activeConsent($user, $purpose) ?? throw new ConsentRefused('conflict');
        }
    }

    /** Revokes the user's active consents (all purposes when null) and deletes their subscriptions. */
    public function revoke(User $user, string $via, ?string $purpose = null): int
    {
        return $this->revokeUser((int) $user->getKey(), $via, $purpose);
    }

    /** Revokes every active consent for this number, across users (STOP reply). */
    public function revokeByPhoneHash(string $hash, string $via): int
    {
        $count = 0;
        $consents = NotificationConsent::query()->where('channel', self::CHANNEL)->where('phone_hash', $hash)->active()->get();
        foreach ($consents as $consent) {
            $this->revokeRow($consent, $via);
            $count++;
        }
        if ($count > 0) {
            Log::info('whatsapp consent revoked by number', ['users' => $consents->pluck('user_id')->unique()->values()->all(), 'via' => $via, 'count' => $count]);
        }

        return $count;
    }

    public function phoneHash(string $e164): string
    {
        return hash_hmac('sha256', $e164, 'whatsapp-phone|'.config('app.key'));
    }

    /** Whether the consent still belongs to the number currently stored on the account. */
    public function matchesCurrentPhone(NotificationConsent $consent, User $user): bool
    {
        $e164 = PhoneNumber::parse($user->getMeta('phone'), $this->settings->defaultCountry())->e164;

        return $e164 !== null && hash_equals($consent->phone_hash, $this->phoneHash($e164));
    }

    /**
     * Hook of User::setMeta('phone', ...): every active consent whose hash differs from the new,
     * normalised number is revoked at once (also when the number is removed or invalid). The same
     * number in another notation keeps the consent.
     */
    public function phoneChanged(User $user, ?string $rawPhone): void
    {
        $this->tolerant(function () use ($user, $rawPhone): void {
            $e164 = PhoneNumber::parse($rawPhone, $this->settings->defaultCountry())->e164;
            $hash = $e164 === null ? null : $this->phoneHash($e164);

            $consents = NotificationConsent::query()->where('user_id', $user->getKey())->active()->get();
            $count = 0;
            foreach ($consents as $consent) {
                if ($hash === null || ! hash_equals($consent->phone_hash, $hash)) {
                    $this->revokeRow($consent, 'phone_changed');
                    $count++;
                }
            }
            if ($count > 0) {
                Log::info('whatsapp consent revoked', ['user_id' => $user->getKey(), 'via' => 'phone_changed', 'count' => $count]);
            }
        });
    }

    /** Hook of the User `updated` event: blocked, deleted and disabled accounts lose their consents. */
    public function userUpdated(User $user): void
    {
        if (! $user->wasChanged('status') || ! in_array($user->accountStatus(), self::REVOKING_STATUSES, true)) {
            return;
        }

        $this->tolerant(fn () => $this->revokeUser((int) $user->getKey(), 'user_status'));
    }

    private function revokeUser(int $userId, string $via, ?string $purpose = null): int
    {
        $query = NotificationConsent::query()->where('user_id', $userId)->active();
        if ($purpose !== null) {
            $query->where('purpose', $purpose);
        }

        $count = 0;
        foreach ($query->get() as $consent) {
            $this->revokeRow($consent, $via);
            $count++;
        }

        $subscriptions = NotificationSubscription::query()->where('user_id', $userId)->where('channel', self::CHANNEL);
        if ($purpose !== null) {
            $subscriptions->whereIn('type', $this->registry->typesOfPurpose($purpose));
        }
        $subscriptions->delete();

        if ($count > 0) {
            Log::info('whatsapp consent revoked', ['user_id' => $userId, 'via' => $via, 'count' => $count]);
        }

        return $count;
    }

    private function revokeRow(NotificationConsent $consent, string $via): void
    {
        $consent->revoke($via);
        NotificationSubscription::query()
            ->where('user_id', $consent->user_id)
            ->where('channel', $consent->channel)
            ->whereIn('type', $this->registry->typesOfPurpose($consent->purpose))
            ->delete();
    }

    private function subscribe(User $user, string $purpose): void
    {
        foreach ($this->registry->optInTypesOfPurpose($purpose) as $type) {
            NotificationSubscription::firstOrCreate(
                ['user_id' => $user->getKey(), 'type' => $type, 'channel' => self::CHANNEL],
                ['filters' => null],
            );
        }
    }

    /**
     * Runs a hook; a missing notification table (not migrated yet) is skipped, any other database error
     * is rethrown.
     */
    private function tolerant(callable $hook): void
    {
        try {
            $hook();
        } catch (QueryException $e) {
            if (Schema::hasTable('notification_consents') && Schema::hasTable('notification_subscriptions')) {
                throw $e;
            }
        }
    }
}
