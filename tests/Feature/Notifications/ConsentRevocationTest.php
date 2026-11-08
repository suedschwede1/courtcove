<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class ConsentRevocationTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->status('admin')->create();
    }

    /** @return array<string, mixed> */
    private function adminPayload(User $user, ?string $phone): array
    {
        return [
            'alias' => $user->alias,
            'email' => $user->email,
            'status' => 'enabled',
            'role' => 'member',
            'firstname' => 'Erika',
            'lastname' => 'Muster',
            'phone' => $phone,
        ];
    }

    #[Test]
    public function phone_change_revokes_immediately_via_account_update(): void
    {
        $user = $this->optedInUser();

        $this->actingAs($user)->put(route('account.update'), [
            'alias' => $user->alias,
            'email' => $user->email,
            'phone' => '+43 660 0000099',
            'player_exchange' => '1',
        ])->assertRedirect(route('account.edit'));

        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame('phone_changed', $this->lastRevokedVia($user));
    }

    #[Test]
    public function phone_change_revokes_immediately_via_admin_user_update(): void
    {
        $user = $this->optedInUser();

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->adminPayload($user, '+43 660 0000099'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame('phone_changed', $this->lastRevokedVia($user));
    }

    #[Test]
    public function phone_change_revokes_immediately_via_direct_set_meta(): void
    {
        $user = $this->optedInUser();

        $user->setMeta('phone', '+43 660 0000099');

        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame('phone_changed', $this->lastRevokedVia($user));

        // No restore when the old number comes back: a new conscious opt-in is needed.
        $user->setMeta('phone', '+43 660 0000000');
        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertNull($this->consents()->activeConsent($user));
    }

    /** @return array<string, array{?string}> */
    public static function emptyPhones(): array
    {
        return ['null' => [null], 'empty string' => ['']];
    }

    #[Test]
    #[DataProvider('emptyPhones')]
    public function removing_the_phone_revokes(?string $empty): void
    {
        // Direct setMeta
        $user = $this->optedInUser();
        $user->setMeta('phone', $empty);
        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame('phone_changed', $this->lastRevokedVia($user));

        // Admin user form
        $other = $this->optedInUser();
        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $other), $this->adminPayload($other, $empty))
            ->assertRedirect(route('admin.users.index'));
        $this->assertSame(0, $this->activeConsentCount($other));
        $this->assertSame('phone_changed', $this->lastRevokedVia($other));

        // Account form
        $third = $this->optedInUser();
        $this->actingAs($third)->put(route('account.update'), [
            'alias' => $third->alias,
            'email' => $third->email,
            'phone' => $empty,
            'player_exchange' => '1',
        ])->assertRedirect(route('account.edit'));
        $this->assertSame(0, $this->activeConsentCount($third));
        $this->assertSame('phone_changed', $this->lastRevokedVia($third));
    }

    #[Test]
    public function same_number_in_other_notation_does_not_revoke(): void
    {
        $user = $this->optedInUser('0660 0000000');

        $user->setMeta('phone', '+43 660 0000000');
        $user->setMeta('phone', '0043 660 000 00 00');

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), $this->adminPayload($user, '+43 (660) 0000000'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame(1, $this->activeConsentCount($user));
    }

    #[Test]
    public function member_exit_anonymize_unlink_and_user_blocked_revoke_with_matching_reason(): void
    {
        // Member leaves (setLeftAt sets status ausgetreten).
        $leaving = $this->optedInUser();
        Member::query()->where('user_id', $leaving->uid)->sole()->setLeftAt('2026-11-01');
        $this->assertSame(0, $this->activeConsentCount($leaving));
        $this->assertSame('member_exit', $this->lastRevokedVia($leaving));

        // Anonymisation clears user_id in the same update: reason anonymized, not member_exit.
        $anonymized = $this->optedInUser();
        Member::query()->where('user_id', $anonymized->uid)->sole()->update(['user_id' => null, 'anonymized_at' => now()]);
        $this->assertSame(0, $this->activeConsentCount($anonymized));
        $this->assertSame('anonymized', $this->lastRevokedVia($anonymized));

        // Link to the booking account removed.
        $unlinked = $this->optedInUser();
        Member::query()->where('user_id', $unlinked->uid)->sole()->update(['user_id' => null]);
        $this->assertSame(0, $this->activeConsentCount($unlinked));
        $this->assertSame('member_exit', $this->lastRevokedVia($unlinked));

        // Member record deleted.
        $deleted = $this->optedInUser();
        Member::query()->where('user_id', $deleted->uid)->sole()->delete();
        $this->assertSame(0, $this->activeConsentCount($deleted));
        $this->assertSame('member_exit', $this->lastRevokedVia($deleted));

        // Account status blocked, deleted, disabled.
        foreach (['blocked', 'deleted', 'disabled'] as $status) {
            $user = $this->optedInUser();
            $user->update(['status' => $status]);
            $this->assertSame(0, $this->activeConsentCount($user), $status);
            $this->assertSame('user_status', $this->lastRevokedVia($user), $status);
        }

        // Admin delete action of the user list.
        $destroyed = $this->optedInUser();
        $this->actingAs($this->admin())->delete(route('admin.users.destroy', $destroyed));
        $this->assertSame(0, $this->activeConsentCount($destroyed));
        $this->assertSame('user_status', $this->lastRevokedVia($destroyed));
    }

    #[Test]
    public function ruhend_and_exchange_off_pause_without_revoking(): void
    {
        $user = $this->optedInUser();
        $member = Member::query()->where('user_id', $user->uid)->sole();

        $member->setStatus('ruhend');
        $this->assertSame(1, $this->activeConsentCount($user));
        $this->assertSame('member_inactive', $this->consents()->gate($user)->reason);

        $member->setStatus('aktiv');
        $user->setMeta('player-exchange', null);
        $this->assertSame(1, $this->activeConsentCount($user));
        $this->assertSame('exchange_off', $this->consents()->gate($user)->reason);

        // Unrelated profile and account changes do not revoke either.
        $user->setMeta('firstname', 'Erika');
        $user->update(['alias' => 'Neuer Name']);
        $member->update(['notiz' => 'irgendwas']);
        $this->assertSame(1, $this->activeConsentCount($user));

        $user->setMeta('player-exchange', '1');
        $this->assertTrue($this->consents()->gate($user)->passed);
    }

    #[Test]
    public function hooks_tolerate_missing_notification_tables(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $member = Member::factory()->create(['user_id' => $user->uid, 'status' => 'aktiv']);
        Schema::drop('notification_subscriptions');
        Schema::drop('notification_consents');

        $user->setMeta('phone', '+43 660 0000099');
        $user->setMeta('phone', null);
        $user->update(['status' => 'blocked']);
        $member->setLeftAt('2026-11-01');
        $member->delete();

        $this->assertNull($user->fresh()?->getMeta('phone'));
    }
}
