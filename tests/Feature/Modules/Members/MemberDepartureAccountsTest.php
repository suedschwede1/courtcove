<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Console\Scheduling\ScheduleDefinition;
use App\Models\NotificationConsent;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Support\MemberDepartureAccounts;
use App\Services\Notifications\ConsentService;
use App\Services\PlayerExchangeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

/** A departing member loses the linked booking account (status blocked), with guards for admins and a one-click way back. */
class MemberDepartureAccountsTest extends TestCase
{
    use BuildsConsentFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function service(): MemberDepartureAccounts
    {
        return app(MemberDepartureAccounts::class);
    }

    private function accountMember(string $status = 'enabled', string $memberStatus = 'aktiv', ?string $leftAt = null): Member
    {
        $user = User::factory()->create(['status' => $status]);

        return Member::factory()->create(['user_id' => $user->uid, 'status' => $memberStatus, 'left_at' => $leftAt]);
    }

    private function depart(Member $member, string $date): void
    {
        $this->actingAs($this->admin())->patchJson("/members/{$member->id}/field", ['field' => 'left_at', 'value' => $date])->assertOk();
    }

    private function accountStatus(Member $member): string
    {
        return (string) User::query()->find($member->user_id)?->status;
    }

    private function offCount(?Member $member = null): int
    {
        return MemberChange::query()
            ->where('action', MemberDepartureAccounts::ACTION_OFF)
            ->when($member, fn ($q) => $q->where('member_id', $member->id))
            ->count();
    }

    #[Test]
    public function the_action_names_fit_the_protocol_column(): void
    {
        $this->assertLessThanOrEqual(16, strlen(MemberDepartureAccounts::ACTION_OFF));
        $this->assertLessThanOrEqual(16, strlen(MemberDepartureAccounts::ACTION_ON));
    }

    #[Test]
    public function a_departure_today_blocks_the_account_at_once_and_logs_it(): void
    {
        $member = $this->accountMember();

        $this->depart($member, '2026-11-08');

        $this->assertSame('blocked', $this->accountStatus($member));
        $this->assertSame(1, $this->offCount($member));
        $this->assertFalse($this->service()->isAutoDeactivated($member->fresh()), 'a departed member is not reactivatable');
    }

    #[Test]
    public function a_departure_in_the_past_through_the_edit_form_blocks_the_account(): void
    {
        $member = $this->accountMember();

        $this->actingAs($this->admin())->put("/members/{$member->id}", [
            'member_number' => $member->member_number,
            'firstname' => $member->firstname,
            'lastname' => $member->lastname,
            'joined_at' => $member->joined_at->format('Y-m-d'),
            'left_at' => '2026-10-01',
        ])->assertRedirect();

        $this->assertSame('blocked', $this->accountStatus($member));
    }

    #[Test]
    public function a_future_departure_keeps_the_account_until_the_date_then_the_scheduler_run_blocks_it(): void
    {
        $member = $this->accountMember();

        $this->depart($member, '2026-11-20');
        $this->assertSame('enabled', $this->accountStatus($member));

        $this->artisan('members:deactivate-left-accounts', ['--since' => 14])->assertSuccessful();
        $this->assertSame('enabled', $this->accountStatus($member), 'not yet due');

        Carbon::setTestNow(Carbon::parse('2026-11-20 03:00:00'));
        $this->artisan('members:deactivate-left-accounts', ['--since' => 14])->assertSuccessful();
        $this->assertSame('blocked', $this->accountStatus($member));
    }

    #[Test]
    public function the_scheduler_entry_runs_the_command_daily_with_a_recent_window(): void
    {
        $event = ScheduleDefinition::find('members-deactivate-left');

        $this->assertNotNull($event);
        $this->assertStringContainsString('members:deactivate-left-accounts', (string) $event->command);
        $this->assertStringContainsString('--since=14', (string) $event->command);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('0 4 * * *', $event->getExpression());
    }

    #[Test]
    public function the_since_window_leaves_old_departures_to_the_manual_run(): void
    {
        $old = $this->accountMember('enabled', 'ausgetreten', '2026-01-10');
        $recent = $this->accountMember('enabled', 'ausgetreten', '2026-11-01');

        $this->artisan('members:deactivate-left-accounts', ['--since' => 14])->assertSuccessful();

        $this->assertSame('enabled', $this->accountStatus($old));
        $this->assertSame('blocked', $this->accountStatus($recent));

        $this->artisan('members:deactivate-left-accounts')->assertSuccessful();
        $this->assertSame('blocked', $this->accountStatus($old));
    }

    #[Test]
    public function the_dry_run_changes_nothing_and_prints_only_ids_and_counts(): void
    {
        $member = $this->accountMember('enabled', 'ausgetreten', '2026-10-01');
        $adminMember = Member::factory()->create(['user_id' => $this->admin()->uid, 'status' => 'ausgetreten', 'left_at' => '2026-10-01']);
        $email = (string) User::query()->find($member->user_id)->email;

        $this->artisan('members:deactivate-left-accounts', ['--dry-run' => true])
            ->doesntExpectOutputToContain($email)
            ->assertSuccessful();

        $this->assertSame('enabled', $this->accountStatus($member));
        $this->assertSame(0, $this->offCount());
        $report = $this->service()->run(true, null);
        $this->assertSame([$member->id], $report['would']);
        $this->assertSame([$adminMember->id], $report['protected']);
    }

    #[Test]
    public function running_twice_is_idempotent(): void
    {
        $member = $this->accountMember();
        $this->depart($member, '2026-11-08');
        $this->artisan('members:deactivate-left-accounts')->assertSuccessful();
        $this->artisan('members:deactivate-left-accounts')->assertSuccessful();

        $this->assertSame(1, $this->offCount());
        $this->assertSame(MemberDepartureAccounts::RESULT_INACTIVE, $this->service()->handle($member->fresh()));
    }

    #[Test]
    public function a_member_without_an_account_is_left_alone(): void
    {
        $member = Member::factory()->create(['user_id' => null]);

        $this->depart($member, '2026-11-08');

        $this->assertSame(MemberDepartureAccounts::RESULT_NO_ACCOUNT, $this->service()->handle($member->fresh()));
        $this->assertSame(0, $this->offCount());
    }

    /** @return array<string, array{0: string, 1: array<string, string>}> */
    public static function protectedAccounts(): array
    {
        return [
            'admin' => ['admin', []],
            'assistant' => ['assist', []],
            'assistant with the members right' => ['assist', ['allow.admin.members' => 'true']],
        ];
    }

    /** @param  array<string, string>  $meta */
    #[DataProvider('protectedAccounts')]
    #[Test]
    public function admin_assistant_and_members_right_accounts_are_never_blocked(string $status, array $meta): void
    {
        $member = $this->accountMember($status);
        foreach ($meta as $key => $value) {
            User::query()->find($member->user_id)->setMeta($key, $value);
        }

        $this->depart($member, '2026-11-08');

        $this->assertSame($status, $this->accountStatus($member), 'the account keeps its role and stays enabled');
        $this->assertSame(MemberDepartureAccounts::RESULT_PROTECTED, $this->service()->handle($member->fresh()));
        $this->assertSame([$member->id], $this->service()->overview()['protected']->pluck('id')->all());
    }

    #[Test]
    public function with_split_role_storage_an_enabled_admin_row_is_protected_too(): void
    {
        config(['users.split_role' => true]);
        $user = User::factory()->create(['status' => 'enabled', 'role' => 'admin']);
        $member = Member::factory()->create(['user_id' => $user->uid, 'status' => 'ausgetreten', 'left_at' => '2026-10-01']);

        $this->assertSame(MemberDepartureAccounts::RESULT_PROTECTED, $this->service()->handle($member));
        $this->assertSame('enabled', $user->fresh()->status);

        $plain = User::factory()->create(['status' => 'enabled', 'role' => 'member']);
        $other = Member::factory()->create(['user_id' => $plain->uid, 'status' => 'ausgetreten', 'left_at' => '2026-10-01']);
        $this->assertSame(MemberDepartureAccounts::RESULT_DEACTIVATED, $this->service()->handle($other));
        $this->assertSame('blocked', $plain->fresh()->status);
        $this->assertSame('member', $plain->fresh()->role);
    }

    #[Test]
    public function the_only_active_admin_is_never_locked_out_by_the_batch(): void
    {
        $onlyAdmin = $this->admin();
        $member = Member::factory()->create(['user_id' => $onlyAdmin->uid, 'status' => 'ausgetreten', 'left_at' => '2020-01-01']);

        $this->artisan('members:deactivate-left-accounts')->assertSuccessful();

        $this->assertSame('admin', $onlyAdmin->fresh()->status);
        $this->assertSame(1, User::query()->active()->count());
        $this->assertSame(0, $this->offCount($member));
    }

    #[Test]
    public function accounts_that_are_not_enabled_are_not_touched(): void
    {
        foreach (['blocked', 'disabled', 'deleted', 'placeholder'] as $status) {
            $member = $this->accountMember($status, 'ausgetreten', '2026-10-01');
            $this->assertSame(MemberDepartureAccounts::RESULT_INACTIVE, $this->service()->handle($member));
            $this->assertSame($status, $this->accountStatus($member));
            $this->assertFalse($this->service()->isAutoDeactivated($member));
        }
    }

    #[Test]
    public function the_block_revokes_whatsapp_consent_drops_the_exchange_mail_and_ends_devices_and_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->optedInUser('+43 660 0000011');
        $member = $this->linkMember($user);
        $this->assertCount(1, app(PlayerExchangeService::class)->recipients());
        $this->assertSame(1, $this->activeConsentCount($user));
        TrustedDevice::create(['user_id' => $user->uid, 'token_hash' => hash('sha256', 't'), 'label' => 'x', 'created_at' => now(), 'last_seen_at' => now(), 'expires_at' => now()->addDays(20)]);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->uid, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

        $this->depart($member, '2026-11-08');

        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertFalse(app(ConsentService::class)->gate($user->fresh())->passed);
        $this->assertCount(0, app(PlayerExchangeService::class)->recipients());
        $this->assertSame(0, TrustedDevice::query()->where('user_id', $user->uid)->whereNull('revoked_at')->count());
        $this->assertDatabaseMissing('sessions', ['id' => 's1']);
        $this->assertSame(0, NotificationConsent::query()->where('user_id', $user->uid)->whereNull('revoked_at')->count());
    }

    #[Test]
    public function reentry_does_not_reactivate_but_offers_one_click_only_for_auto_blocked_accounts(): void
    {
        $member = $this->accountMember();
        $this->depart($member, '2026-11-08');
        $this->assertSame('blocked', $this->accountStatus($member));

        $this->actingAs($this->admin())->patchJson("/members/{$member->id}/field", ['field' => 'status', 'value' => 'aktiv'])->assertOk();

        $this->assertSame('blocked', $this->accountStatus($member), 'no automatic reactivation');
        $this->assertTrue($this->service()->isAutoDeactivated($member->fresh()));
        $this->assertSame([$member->id], $this->service()->overview()['reactivatable']->pluck('id')->all());

        $this->actingAs($this->admin())->get("/members/{$member->id}/edit")->assertOk()->assertSeeText('Konto wieder aktivieren');

        $this->actingAs($this->admin())->post("/members/{$member->id}/account/reactivate")->assertRedirect();

        $this->assertSame('enabled', $this->accountStatus($member));
        $this->assertSame(1, MemberChange::query()->where('member_id', $member->id)->where('action', MemberDepartureAccounts::ACTION_ON)->count());
        $this->assertFalse($this->service()->isAutoDeactivated($member->fresh()), 'the marker is gone');
    }

    #[Test]
    public function a_manually_blocked_account_is_never_offered_or_reactivated(): void
    {
        $member = $this->accountMember('blocked', 'aktiv');

        $this->assertFalse($this->service()->isAutoDeactivated($member));
        $this->assertSame([], $this->service()->overview()['reactivatable']->all());

        $this->actingAs($this->admin())->get("/members/{$member->id}/edit")->assertOk()->assertDontSeeText('Konto wieder aktivieren');
        $this->actingAs($this->admin())->post("/members/{$member->id}/account/reactivate")->assertRedirect()->assertSessionHasErrors('account');

        $this->assertSame('blocked', $this->accountStatus($member));
    }

    #[Test]
    public function a_stale_marker_does_not_make_a_later_manual_block_reactivatable(): void
    {
        $member = $this->accountMember();
        $this->depart($member, '2026-11-08');
        $user = User::query()->find($member->user_id);
        $member->setStatus('aktiv');

        // An admin enables the account by hand (user form), later blocks it by hand.
        $user->update(['status' => 'enabled']);
        $user->update(['status' => 'blocked']);

        $this->assertFalse($this->service()->isAutoDeactivated($member->fresh()));
    }

    #[Test]
    public function the_bulk_enable_of_the_user_admin_also_clears_the_marker(): void
    {
        $member = $this->accountMember();
        $this->depart($member, '2026-11-08');
        $member->setStatus('aktiv');

        $this->actingAs($this->admin())->post('/admin/users/bulk', ['uids' => [$member->user_id], 'action' => 'enabled'])->assertRedirect();
        $this->assertSame('enabled', $this->accountStatus($member));
        User::query()->whereKey($member->user_id)->update(['status' => 'blocked']);

        $this->assertFalse($this->service()->isAutoDeactivated($member->fresh()));
    }

    #[Test]
    public function the_marker_of_a_relinked_account_does_not_apply_to_another_member(): void
    {
        $member = $this->accountMember();
        $this->depart($member, '2026-11-08');
        $userId = $member->user_id;
        Member::query()->whereKey($member->id)->update(['user_id' => null]);
        $other = Member::factory()->create(['user_id' => $userId, 'status' => 'aktiv']);

        $this->assertFalse($this->service()->isAutoDeactivated($other->fresh()));
    }

    #[Test]
    public function anonymising_blocks_the_account_before_the_link_is_removed(): void
    {
        $member = $this->accountMember('enabled', 'ausgetreten', '2020-01-01');
        $userId = $member->user_id;

        $this->actingAs($this->admin())->post("/members/{$member->id}/anonymize", ['confirmation' => 'ANONYMISIEREN'])->assertRedirect();

        $this->assertNull($member->fresh()->user_id);
        $this->assertSame('blocked', User::query()->find($userId)->status);
        $actions = MemberChange::query()->where('member_id', $member->id)->orderBy('id')->pluck('action')->all();
        $this->assertSame([MemberDepartureAccounts::ACTION_OFF, 'anonymized'], $actions);
    }

    #[Test]
    public function anonymising_keeps_an_admin_account_enabled(): void
    {
        $adminUser = $this->admin();
        $member = Member::factory()->create(['user_id' => $adminUser->uid, 'status' => 'ausgetreten', 'left_at' => '2020-01-01']);

        $this->actingAs($this->admin())->post("/members/{$member->id}/anonymize", ['confirmation' => 'ANONYMISIEREN'])->assertRedirect();

        $this->assertSame('admin', $adminUser->fresh()->status);
    }

    #[Test]
    public function the_protocol_and_the_pages_show_the_new_entries_and_warnings(): void
    {
        $member = $this->accountMember();
        $this->depart($member, '2026-11-08');
        $protectedMember = Member::factory()->create(['user_id' => $this->admin()->uid, 'status' => 'ausgetreten', 'left_at' => '2026-10-01', 'lastname' => 'Schutzfall']);
        $viewer = $this->admin();

        $this->actingAs($viewer)->get("/members/{$member->id}/edit")->assertOk()->assertSeeText('Konto wegen Austritt deaktiviert');
        $this->actingAs($viewer)->get('/members/dashboard')->assertOk()
            ->assertSeeText('Konten ausgetretener Mitglieder')
            ->assertSeeText('Zuletzt: 1 Konto wegen Austritt deaktiviert')
            ->assertSeeText('Schutzfall');
        $this->actingAs($viewer)->get("/members/{$protectedMember->id}/edit")->assertOk()->assertSeeText('bleibt aktiv');
        $this->actingAs($viewer)->get('/members')->assertOk()->assertSeeText('Schutzfall');
        $this->actingAs($viewer)->get('/members/changes')->assertOk()->assertSeeText('Konto wegen Austritt deaktiviert');
    }

    #[Test]
    public function the_texts_exist_in_german_and_english(): void
    {
        foreach (['de', 'en'] as $locale) {
            foreach (['card_title', 'protected_short', 'protected_here', 'reactivate', 'reactivated', 'not_reactivatable', 'reentered_notice', 'reentered_short', 'deactivated_here', 'anonymized_protected', 'last', 'pending', 'history_off', 'history_on'] as $key) {
                $this->assertNotSame("members::admin.departure.{$key}", trans("members::admin.departure.{$key}", ['name' => 'x', 'count' => 2], $locale), "{$locale} {$key}");
            }
        }
    }

    #[Test]
    public function household_and_connection_logic_still_run_on_departure(): void
    {
        $vollzahler = $this->accountMember();
        $anschluss = Member::factory()->create();
        $anschluss->forceFill(['connected_to_member_id' => $vollzahler->id])->saveQuietly();

        $this->depart($vollzahler, '2026-11-08');

        $this->assertNull($anschluss->fresh()->connected_to_member_id, 'the Anschluss link ends as before');
        $this->assertSame('blocked', $this->accountStatus($vollzahler));
    }

    #[Test]
    public function an_account_an_admin_enabled_again_is_not_blocked_by_a_plain_save_or_the_daily_run(): void
    {
        $member = $this->accountMember();
        $this->depart($member, '2026-11-08');
        User::query()->find($member->user_id)->update(['status' => 'enabled']);

        $this->actingAs($this->admin())->put("/members/{$member->id}", [
            'member_number' => $member->member_number,
            'firstname' => $member->firstname,
            'lastname' => $member->lastname,
            'joined_at' => $member->joined_at->format('Y-m-d'),
            'left_at' => '2026-11-08',
            'notiz' => 'nur eine Notiz',
        ])->assertRedirect();
        $this->artisan('members:deactivate-left-accounts', ['--since' => 14])->assertSuccessful();

        $this->assertSame('enabled', $this->accountStatus($member));
        $this->assertSame(1, $this->offCount($member));

        // A new departure (re-entry, then leaving again) blocks it again.
        $member->fresh()->setStatus('aktiv');
        $this->depart($member->fresh(), '2026-11-08');
        $this->assertSame('blocked', $this->accountStatus($member));
    }

    #[Test]
    public function saving_a_blocked_status_in_the_user_form_drops_the_departure_marker(): void
    {
        $member = $this->accountMember();
        $this->depart($member, '2026-11-08');
        $member->setStatus('aktiv');
        $user = User::query()->find($member->user_id);

        $this->actingAs($this->admin())->put("/admin/users/{$user->uid}", [
            'alias' => $user->alias, 'email' => $user->email, 'status' => 'blocked', 'role' => 'member',
        ])->assertRedirect();

        $this->assertFalse($this->service()->isAutoDeactivated($member->fresh()));
    }

    #[Test]
    public function anonymising_a_protected_account_says_it_stays_active(): void
    {
        $adminUser = $this->admin();
        $member = Member::factory()->create(['user_id' => $adminUser->uid, 'status' => 'ausgetreten', 'left_at' => '2020-01-01']);

        $this->actingAs($this->admin())->post("/members/{$member->id}/anonymize", ['confirmation' => 'ANONYMISIEREN'])
            ->assertSessionHas('success', fn (string $m): bool => str_contains($m, 'Admin- oder Assistenzrechte'));
    }
}
