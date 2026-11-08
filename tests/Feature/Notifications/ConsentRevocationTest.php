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

    /**
     * The gate looks at the booking account only: leaving, unlinking, deleting or anonymising the member
     * record ends no consent and does not pause the sending (the account lives on).
     */
    #[Test]
    public function member_lifecycle_neither_revokes_nor_pauses(): void
    {
        // Member leaves (setLeftAt sets status ausgetreten).
        $leaving = $this->optedInUser();
        $this->linkMember($leaving)->setLeftAt('2026-11-01');

        // Member record anonymised in the same update that clears the link (as the anonymise action does).
        $anonymized = $this->optedInUser();
        $this->linkMember($anonymized)->update(['user_id' => null, 'anonymized_at' => now()]);

        // Link to the booking account removed.
        $unlinked = $this->optedInUser();
        $this->linkMember($unlinked)->update(['user_id' => null]);

        // Member record deleted.
        $deleted = $this->optedInUser();
        $this->linkMember($deleted)->delete();

        // Dormant.
        $dormant = $this->optedInUser();
        $this->linkMember($dormant)->setStatus('ruhend');

        foreach (['leaving' => $leaving, 'anonymized' => $anonymized, 'unlinked' => $unlinked, 'deleted' => $deleted, 'dormant' => $dormant] as $case => $user) {
            $this->assertSame(1, $this->activeConsentCount($user), $case);
            $this->assertNull($this->lastRevokedVia($user), $case);
            $this->assertTrue($this->consents()->gate($user->fresh())->passed, $case);
        }
    }

    /**
     * Decision 2026-11-08 (replaces the earlier rule that anonymising leaves the account alone): the admin action
     * "anonymise member" first blocks the still-enabled linked account (see MemberDepartureAccounts), so the
     * consent ends with it, and only then removes the link.
     */
    #[Test]
    public function anonymising_a_member_blocks_the_account_and_revokes_its_consent_before_unlinking(): void
    {
        $user = $this->optedInUser();
        $member = $this->linkMember($user, ['status' => 'ausgetreten', 'left_at' => now()->subYears(5)->toDateString()]);

        $this->actingAs($this->admin())->post("/members/{$member->id}/anonymize", ['confirmation' => 'ANONYMISIEREN'])
            ->assertRedirect(route('members.edit', $member));

        $this->assertNotNull($member->fresh()->anonymized_at);
        $this->assertNull($member->fresh()->user_id);
        $this->assertSame('blocked', $user->fresh()->status);
        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame('user_status', $this->lastRevokedVia($user));
        $this->assertFalse($this->consents()->gate($user->fresh())->passed);
    }

    #[Test]
    public function blocked_deleted_disabled_and_destroyed_accounts_revoke(): void
    {
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
    public function exchange_off_pauses_without_revoking(): void
    {
        $user = $this->optedInUser();
        $member = $this->linkMember($user);

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
