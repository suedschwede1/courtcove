<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications\Concerns;

use App\Models\NotificationConsent;
use App\Models\Option;
use App\Models\User;
use App\Modules\Members\Models\Member;
use App\Services\Notifications\ConsentService;

/**
 * Fixtures for the consent tests: a user that passes the whole gate (main switch on, enabled account,
 * Austrian mobile number, in the player exchange). The gate looks at the booking account only, so the
 * user has no member record; linkMember() adds one where a test needs it. Numbers are fictitious.
 */
trait BuildsConsentFixtures
{
    protected function enableWhatsApp(): void
    {
        Option::query()->where('key', 'whatsapp.enabled')->delete();
        Option::create(['key' => 'whatsapp.enabled', 'value' => '1', 'locale' => null]);
    }

    protected function eligibleUser(string $phone = '+43 660 0000000'): User
    {
        $this->enableWhatsApp();
        $user = User::factory()->create(['status' => 'enabled']);
        $user->setMeta('phone', $phone);
        $user->setMeta('player-exchange', '1');

        return $user->fresh();
    }

    /** @param  array<string, mixed>  $attributes */
    protected function linkMember(User $user, array $attributes = []): Member
    {
        return Member::factory()->create(array_merge(
            ['user_id' => $user->uid, 'status' => 'aktiv', 'left_at' => null, 'anonymized_at' => null],
            $attributes,
        ));
    }

    protected function consents(): ConsentService
    {
        return app(ConsentService::class);
    }

    protected function optedInUser(string $phone = '+43 660 0000000'): User
    {
        $user = $this->eligibleUser($phone);
        $this->consents()->optIn($user, NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');

        return $user;
    }

    protected function activeConsentCount(User $user): int
    {
        return NotificationConsent::query()->where('user_id', $user->uid)->whereNull('revoked_at')->count();
    }

    protected function lastRevokedVia(User $user): ?string
    {
        return NotificationConsent::query()->where('user_id', $user->uid)->whereNotNull('revoked_at')->latest('id')->value('revoked_via');
    }
}
