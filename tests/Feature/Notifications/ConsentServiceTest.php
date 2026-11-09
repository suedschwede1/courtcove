<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationSubscription;
use App\Notifications\Registry\NotificationRegistry;
use App\Services\Notifications\ConsentRefused;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class ConsentServiceTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    #[Test]
    public function the_consent_text_version_exists_in_both_languages(): void
    {
        $this->assertSame('wa-2026-11-v1', trans('booking.notifications.consent_text_version', [], 'de'));
        $this->assertSame('wa-2026-11-v1', trans('booking.notifications.consent_text_version', [], 'en'));
        // v1 stays for consents given under the earlier opt-in text; new consents use the purpose version (v2).
        $version = app(NotificationRegistry::class)->purpose(NotificationConsent::PURPOSE_PLAYER_SEARCH)?->consentVersion();
        $this->assertSame('wa-2026-11-v2', $version);
        foreach (['de', 'en'] as $locale) {
            $this->assertSame($version, trans('booking.notifications.purposes.player_search.consent_version', [], $locale));
        }
    }

    #[Test]
    public function opt_in_stores_timestamp_text_version_hash_and_last2_but_no_number(): void
    {
        Carbon::setTestNow('2026-11-08 10:00:00');
        $user = $this->eligibleUser('+43 660 0000042');

        $consent = $this->consents()->optIn($user, NotificationConsent::PURPOSE_PLAYER_SEARCH, 'en');

        $this->assertSame($user->uid, $consent->user_id);
        $this->assertSame('whatsapp', $consent->channel);
        $this->assertSame(NotificationConsent::PURPOSE_PLAYER_SEARCH, $consent->purpose);
        $this->assertSame('en', $consent->locale);
        $this->assertSame(app(NotificationRegistry::class)->purpose(NotificationConsent::PURPOSE_PLAYER_SEARCH)?->consentVersion(), $consent->consent_text_version);
        $this->assertSame('2026-11-08 10:00:00', $consent->consented_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $consent->active_marker);
        $this->assertNull($consent->revoked_at);
        $this->assertSame('42', $consent->phone_last2);
        $this->assertSame(hash_hmac('sha256', '+436600000042', 'whatsapp-phone|'.config('app.key')), $consent->phone_hash);
        $this->assertSame($consent->phone_hash, $this->consents()->phoneHash('+436600000042'));

        $row = (array) DB::table('notification_consents')->where('id', $consent->id)->first();
        foreach ($row as $column => $value) {
            if (! is_string($value)) {
                continue;
            }
            $this->assertStringNotContainsString('6600000042', $value, $column);
            $this->assertStringNotContainsString('660 0000042', $value, $column);
            try {
                Crypt::decryptString($value);
                $this->fail("column $column holds an encrypted value");
            } catch (DecryptException) {
                // expected: nothing in the row is encrypted (and so no hidden number)
            }
        }
    }

    #[Test]
    public function opt_in_is_idempotent(): void
    {
        $user = $this->eligibleUser();

        $first = $this->consents()->optIn($user, NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');
        $second = $this->consents()->optIn($user, NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, NotificationConsent::query()->where('user_id', $user->uid)->count());
        $this->assertSame(1, NotificationSubscription::query()->where('user_id', $user->uid)->count());
        $this->assertSame($first->id, $this->consents()->activeConsent($user)?->id);
    }

    #[Test]
    public function opt_in_refused_when_gate_fails(): void
    {
        $user = $this->eligibleUser();
        $user->setMeta('player-exchange', null);

        try {
            $this->consents()->optIn($user, NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');
            $this->fail('ConsentRefused expected');
        } catch (ConsentRefused $e) {
            $this->assertSame('exchange_off', $e->reason);
        }

        $this->assertSame(0, NotificationConsent::query()->count());
        $this->assertSame(0, NotificationSubscription::query()->count());
        $this->assertNull($this->consents()->activeConsent($user));
    }

    #[Test]
    public function revoke_sets_marker_null_and_allows_new_opt_in(): void
    {
        $user = $this->optedInUser();

        $this->assertSame(1, $this->consents()->revoke($user, 'account'));

        $old = NotificationConsent::query()->where('user_id', $user->uid)->sole();
        $this->assertNull($old->active_marker);
        $this->assertNotNull($old->revoked_at);
        $this->assertSame('account', $old->revoked_via);
        $this->assertNull($this->consents()->activeConsent($user));
        $this->assertSame(0, $this->consents()->revoke($user, 'account'));

        $new = $this->consents()->optIn($user, NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame(2, NotificationConsent::query()->where('user_id', $user->uid)->count());
        $this->assertSame(1, $this->activeConsentCount($user));
    }

    #[Test]
    public function opt_in_creates_the_subscription_and_revoke_deletes_it(): void
    {
        $user = $this->optedInUser();

        $subscription = NotificationSubscription::query()->where('user_id', $user->uid)->sole();
        $this->assertSame('player_search.opened', $subscription->type);
        $this->assertSame('whatsapp', $subscription->channel);
        $this->assertNull($subscription->filters);

        $this->consents()->revoke($user, 'admin', NotificationConsent::PURPOSE_PLAYER_SEARCH);

        $this->assertSame(0, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function revoke_by_phone_hash_hits_all_users_with_that_number(): void
    {
        $a = $this->optedInUser('+43 660 0000042');
        $b = $this->optedInUser('0660 0000042');
        $other = $this->optedInUser('+43 660 0000043');

        $count = $this->consents()->revokeByPhoneHash($this->consents()->phoneHash('+436600000042'), 'stop');

        $this->assertSame(2, $count);
        $this->assertSame(0, $this->activeConsentCount($a));
        $this->assertSame(0, $this->activeConsentCount($b));
        $this->assertSame('stop', $this->lastRevokedVia($a));
        $this->assertSame(1, $this->activeConsentCount($other));
        $this->assertSame(1, NotificationSubscription::query()->count());
    }

    #[Test]
    public function matches_current_phone_compares_the_normalised_hash(): void
    {
        $user = $this->optedInUser('+43 660 0000042');
        $consent = $this->consents()->activeConsent($user);
        $this->assertNotNull($consent);

        $this->assertTrue($this->consents()->matchesCurrentPhone($consent, $user));

        // Mass update without events: the dispatcher still sees the mismatch.
        $user->meta()->where('key', 'phone')->update(['value' => '+43 660 0000099']);
        $this->assertFalse($this->consents()->matchesCurrentPhone($consent, $user->fresh()));

        $user->meta()->where('key', 'phone')->delete();
        $this->assertFalse($this->consents()->matchesCurrentPhone($consent, $user->fresh()));
    }
}
