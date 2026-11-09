<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\LoginVerificationCode;
use App\Mail\TrustedDeviceAdded;
use App\Models\LoginHistory;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustedDeviceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['booking.security.trusted_device_scope' => 'privileged']);
    }

    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'admin@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'admin',
        ], $overrides));
    }

    private function member(): User
    {
        return User::factory()->create([
            'email' => 'member@example.com',
            'pw' => bcrypt('secret123'),
            'status' => 'enabled',
        ]);
    }

    private function signIn(User $user, array $cookies = [])
    {
        return $this->withCookies($cookies)->post('/login', ['email' => $user->email, 'password' => 'secret123']);
    }

    /** @return array{0: string, 1: TrustedDevice} cookie token and its row */
    private function deviceFor(User $user, array $overrides = []): array
    {
        $token = bin2hex(random_bytes(32));
        $device = TrustedDevice::create(array_merge([
            'user_id' => $user->uid,
            'token_hash' => hash('sha256', $token),
            'label' => 'Firefox auf Windows',
            'created_at' => now()->subDays(2),
            'last_seen_at' => now()->subDay(),
            'expires_at' => now()->addDays(28),
        ], $overrides));

        return [$token, $device];
    }

    private function cookieName(User $user): string
    {
        return app(TrustedDeviceService::class)->cookieName((int) $user->uid);
    }

    private function pendingCode(): string
    {
        $code = null;
        Mail::assertQueued(LoginVerificationCode::class, function (LoginVerificationCode $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    #[Test]
    public function an_admin_without_a_device_cookie_must_confirm_the_device_by_code(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->signIn($admin)->assertRedirect(route('login.verify.show'));

        $this->assertGuest();
        Mail::assertQueued(LoginVerificationCode::class, fn (LoginVerificationCode $mail): bool => $mail->newDevice && $mail->hasTo('admin@example.com'));
        $this->assertSame(0, TrustedDevice::query()->count());
    }

    #[Test]
    public function the_right_code_signs_in_trusts_the_device_and_sets_a_hardened_cookie(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->signIn($admin);

        $response = $this->post('/login/verify', ['code' => $this->pendingCode()]);

        $response->assertRedirect(route('calendar.index'));
        $this->assertAuthenticatedAs($admin);

        $device = TrustedDevice::query()->sole();
        $this->assertSame((int) $admin->uid, $device->user_id);
        $this->assertSame(64, strlen($device->token_hash));
        $this->assertEqualsWithDelta(30, now()->diffInDays($device->expires_at, true), 1);

        $cookie = $response->getCookie($this->cookieName($admin));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        // 256-bit token; the database holds only its hash.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $cookie->getValue());
        $this->assertSame(hash('sha256', (string) $cookie->getValue()), $device->token_hash);
        $this->assertNotSame($cookie->getValue(), $device->token_hash);

        Mail::assertQueued(TrustedDeviceAdded::class, fn (TrustedDeviceAdded $mail): bool => $mail->hasTo('admin@example.com'));
        $this->assertSame('new_device', LoginHistory::query()->where('user_id', $admin->uid)->where('status', 'success')->value('risk_flags'));
    }

    #[Test]
    public function the_cookie_is_secure_over_https(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->signIn($admin);

        $response = $this->post('https://localhost/login/verify', ['code' => $this->pendingCode()]);

        $this->assertTrue($response->getCookie($this->cookieName($admin))->isSecure());
    }

    #[Test]
    public function a_valid_device_cookie_signs_in_without_a_code(): void
    {
        Mail::fake();
        $admin = $this->admin();
        [$token, $device] = $this->deviceFor($admin);

        $this->signIn($admin, [$this->cookieName($admin) => $token])->assertRedirect(route('calendar.index'));

        $this->assertAuthenticatedAs($admin);
        Mail::assertNothingQueued();
        $this->assertTrue($device->fresh()->last_seen_at->isToday());
        // Validity is fixed: using the device does not extend it.
        $this->assertTrue($device->fresh()->expires_at->equalTo($device->expires_at));
    }

    #[Test]
    public function unusable_cookies_require_a_new_confirmation(): void
    {
        $admin = $this->admin();
        $other = $this->admin(['email' => 'other@example.com']);

        [$expiredToken] = $this->deviceFor($admin, ['expires_at' => now()->subMinute()]);
        [$revokedToken] = $this->deviceFor($admin, ['revoked_at' => now()->subHour()]);
        [$foreignToken] = $this->deviceFor($other);

        foreach ([$expiredToken, $revokedToken, $foreignToken, 'garbage', str_repeat('a', 64)] as $token) {
            Mail::fake();
            RateLimiter::clear('login-verify-send:'.$admin->uid);

            $this->signIn($admin, [$this->cookieName($admin) => $token])->assertRedirect(route('login.verify.show'));
            $this->assertGuest();
            Mail::assertQueued(LoginVerificationCode::class);
            $this->flushSession();
        }
    }

    #[Test]
    public function a_risk_flag_asks_for_a_code_even_on_a_trusted_device(): void
    {
        Mail::fake();
        $admin = $this->admin();
        [$token] = $this->deviceFor($admin);
        LoginHistory::record((int) $admin->uid, '9.9.9.9', 'AT', 'failed');
        LoginHistory::record((int) $admin->uid, '9.9.9.9', 'AT', 'failed');

        $this->signIn($admin, [$this->cookieName($admin) => $token])->assertRedirect(route('login.verify.show'));
        $this->assertGuest();
        Mail::assertQueued(LoginVerificationCode::class, fn (LoginVerificationCode $mail): bool => ! $mail->newDevice);

        $response = $this->post('/login/verify', ['code' => $this->pendingCode()]);

        $this->assertAuthenticatedAs($admin);
        $this->assertSame(1, TrustedDevice::query()->count(), 'no second device for an already trusted browser');
        $this->assertNull($response->getCookie($this->cookieName($admin)));
        Mail::assertNotQueued(TrustedDeviceAdded::class);
    }

    #[Test]
    public function an_assistant_needs_a_device_too_and_a_plain_member_does_not(): void
    {
        Mail::fake();
        $assistant = $this->admin(['email' => 'assist@example.com', 'status' => 'assist']);
        $this->signIn($assistant)->assertRedirect(route('login.verify.show'));
        $this->flushSession();

        $member = $this->member();
        $this->signIn($member)->assertRedirect(route('calendar.index'));
        $this->assertAuthenticatedAs($member);
    }

    #[Test]
    public function the_scope_switch_extends_the_check_to_members_or_turns_it_off(): void
    {
        Mail::fake();
        $member = $this->member();

        config(['booking.security.trusted_device_scope' => 'all']);
        $this->signIn($member)->assertRedirect(route('login.verify.show'));
        $this->flushSession();

        config(['booking.security.trusted_device_scope' => 'off']);
        $admin = $this->admin();
        $this->signIn($admin)->assertRedirect(route('calendar.index'));
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function code_sending_is_capped_per_hour_with_a_wait_message_not_a_lock(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $max = (int) config('booking.login_verification.max_sends_per_hour');

        for ($i = 0; $i < $max; $i++) {
            $this->signIn($admin)->assertRedirect(route('login.verify.show'));
            $this->flushSession();
        }

        Mail::assertQueued(LoginVerificationCode::class, $max);

        $this->signIn($admin)->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertStringContainsString('Minuten', session('errors')->first('email'));
        Mail::assertQueued(LoginVerificationCode::class, $max);

        // Only waiting: after the hour a code is possible again.
        $this->travel(61)->minutes();
        $this->flushSession();
        $this->signIn($admin)->assertRedirect(route('login.verify.show'));
    }

    #[Test]
    public function an_attacker_with_the_password_cannot_use_up_the_real_admins_code_sending(): void
    {
        Mail::fake();
        $admin = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->signIn($admin);
            $this->flushSession();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->signIn($admin)->assertRedirect('/login');
        $this->flushSession();

        // The real admin comes from another address: their own cap is untouched.
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->signIn($admin)->assertRedirect(route('login.verify.show'));
    }

    #[Test]
    public function an_expired_device_is_confirmed_again_with_a_new_code_and_gets_a_new_cookie(): void
    {
        Mail::fake();
        $admin = $this->admin();
        [$oldToken, $old] = $this->deviceFor($admin, ['expires_at' => now()->subDay()]);

        $this->signIn($admin, [$this->cookieName($admin) => $oldToken])->assertRedirect(route('login.verify.show'));
        $response = $this->post('/login/verify', ['code' => $this->pendingCode()]);

        $response->assertRedirect(route('calendar.index'));
        $newToken = (string) $response->getCookie($this->cookieName($admin))->getValue();
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(2, TrustedDevice::query()->where('user_id', $admin->uid)->count());

        // The new cookie works without a code.
        $this->post('/logout');
        $this->signIn($admin, [$this->cookieName($admin) => $newToken])->assertRedirect(route('calendar.index'));
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function a_new_code_can_always_be_requested_after_a_wrong_expired_or_used_up_code(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->signIn($admin);

        // Wrong tries to the limit, then an expired code: every time a new code is possible after the pause.
        for ($i = 0; $i < 7; $i++) {
            $this->post('/login/verify', ['code' => '000000']);
        }
        $pending = session('login.pending_verification');
        session(['login.pending_verification' => array_merge($pending, ['expires_at' => now()->subMinute()->toIso8601String()])]);

        $this->post('/login/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/login/verify/resend')->assertSessionHasErrors('code'); // within the pause: a wait message
        $this->travel(31)->seconds();

        $this->post('/login/verify/resend')->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->post('/login/verify', ['code' => $this->pendingCode()])->assertRedirect(route('calendar.index'));
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function the_code_check_is_counted_server_side_per_account(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->signIn($admin);
        $good = $this->pendingCode();

        // The session counter plays no role: whatever the session says, the sixth try is not even checked.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/verify', ['code' => '000000']);
            $pending = session('login.pending_verification');
            $pending['attempts'] = 0;
            session(['login.pending_verification' => $pending]);
        }

        $this->post('/login/verify', ['code' => $good])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    #[Test]
    public function a_password_reset_confirms_this_device_in_the_same_flow(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->deviceFor($admin);
        $token = Password::broker()->createToken($admin);

        $this->post('/password/reset', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'newsecret456',
            'password_confirmation' => 'newsecret456',
        ])->assertRedirect(route('login.verify.show'));

        $this->assertGuest();
        $this->assertSame(0, TrustedDevice::query()->valid()->count(), 'old devices are revoked');

        $response = $this->post('/login/verify', ['code' => $this->pendingCode()]);

        $response->assertRedirect(route('calendar.index'));
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(Hash::check('newsecret456', (string) $admin->fresh()->pw));
        $this->assertNotNull($response->getCookie($this->cookieName($admin)));
        $this->assertSame(1, TrustedDevice::query()->valid()->count());
    }

    #[Test]
    public function a_password_reset_voids_open_codes_and_restarts_the_counters(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->signIn($admin);
        $oldCode = $this->pendingCode();
        $session = session()->all();
        RateLimiter::hit('login-verify-fail:'.$admin->uid);

        $token = Password::broker()->createToken($admin);
        $this->flushSession();
        $this->post('/password/reset', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'newsecret456',
            'password_confirmation' => 'newsecret456',
        ]);
        $this->assertSame(0, RateLimiter::attempts('login-verify-fail:'.$admin->uid));

        // The session that held the old code (another browser) can no longer use it.
        $this->flushSession();
        session($session);
        $this->post('/login/verify', ['code' => $oldCode])->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function the_verify_step_rechecks_the_account(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->signIn($admin);
        $code = $this->pendingCode();

        $admin->update(['status' => 'blocked']);

        $this->post('/login/verify', ['code' => $code])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame(0, TrustedDevice::query()->count());
    }

    #[Test]
    public function a_rights_change_between_password_and_code_voids_the_pending_code(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->signIn($admin);
        $code = $this->pendingCode();

        usleep(2000);
        app(TrustedDeviceService::class)->revokeAll($admin);

        $this->post('/login/verify', ['code' => $code])->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function a_user_with_only_the_members_right_needs_a_device_and_a_plain_member_does_not(): void
    {
        $assistant = $this->admin(['email' => 'kassier@example.com', 'status' => 'assist']);
        $assistant->syncPrivileges(['admin.members']);
        $this->assertTrue($assistant->fresh()->requiresTrustedDevice());
        $this->assertTrue($this->admin(['email' => 'a2@example.com'])->requiresTrustedDevice());
        $this->assertFalse($this->member()->requiresTrustedDevice());

        Mail::fake();
        $this->signIn($assistant->fresh())->assertRedirect(route('login.verify.show'));
    }

    #[Test]
    public function changing_the_email_address_revokes_the_devices(): void
    {
        $admin = $this->admin();
        $this->deviceFor($admin);

        $this->actingAs($admin)->put('/my-account', ['alias' => $admin->alias, 'email' => 'new@example.com'])->assertRedirect(route('account.edit'));

        $this->assertSame(0, TrustedDevice::query()->valid()->count());
    }

    #[Test]
    public function a_password_change_ends_the_other_sessions_of_the_account(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $other = $this->admin(['email' => 'other@example.com']);
        foreach (['mine-other-browser' => $admin->uid, 'foreign' => $other->uid] as $id => $uid) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $uid, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }

        $this->actingAs($admin)->put('/my-account/password', [
            'current_password' => 'secret123',
            'password' => 'newsecret456',
            'password_confirmation' => 'newsecret456',
        ]);

        $this->assertDatabaseMissing('sessions', ['id' => 'mine-other-browser']);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign']);
    }

    #[Test]
    public function a_password_change_removes_all_devices(): void
    {
        $admin = $this->admin();
        $this->deviceFor($admin);
        $this->deviceFor($admin);

        $this->actingAs($admin)->put('/my-account/password', [
            'current_password' => 'secret123',
            'password' => 'newsecret456',
            'password_confirmation' => 'newsecret456',
        ])->assertRedirect();

        $this->assertSame(0, TrustedDevice::query()->valid()->count());
    }

    #[Test]
    public function an_admin_password_reset_and_a_rights_change_remove_the_devices(): void
    {
        $root = $this->admin(['email' => 'root@example.com']);
        $target = $this->admin(['email' => 'target@example.com', 'status' => 'assist']);
        $this->deviceFor($target);

        $this->actingAs($root)->post('/admin/users/'.$target->uid.'/password', ['password' => 'brandnew1'])->assertRedirect();
        $this->assertSame(0, TrustedDevice::query()->valid()->where('user_id', $target->uid)->count());

        $this->deviceFor($target);
        $this->actingAs($root)->put('/admin/users/'.$target->uid, [
            'alias' => $target->alias,
            'email' => 'target@example.com',
            'status' => 'enabled',
            'role' => 'admin',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertSame(0, TrustedDevice::query()->valid()->where('user_id', $target->uid)->count(), 'role change');
    }

    #[Test]
    public function granting_or_removing_a_single_right_of_an_assistant_removes_the_devices(): void
    {
        $root = $this->admin(['email' => 'root@example.com']);
        $target = $this->admin(['email' => 'target@example.com', 'status' => 'assist']);
        $this->deviceFor($target);

        $payload = ['alias' => $target->alias, 'email' => 'target@example.com', 'status' => 'enabled', 'role' => 'assist'];

        $this->actingAs($root)->put('/admin/users/'.$target->uid, $payload + ['privileges' => [User::CORE_PRIVILEGES[0]]])->assertRedirect(route('admin.users.index'));
        $this->assertSame(0, TrustedDevice::query()->valid()->where('user_id', $target->uid)->count(), 'right granted');

        $this->deviceFor($target);
        $this->actingAs($root)->put('/admin/users/'.$target->uid, $payload + ['privileges' => [User::CORE_PRIVILEGES[0]]])->assertRedirect(route('admin.users.index'));
        $this->assertSame(1, TrustedDevice::query()->valid()->where('user_id', $target->uid)->count(), 'unchanged rights keep the device');

        $this->actingAs($root)->put('/admin/users/'.$target->uid, $payload)->assertRedirect(route('admin.users.index'));
        $this->assertSame(0, TrustedDevice::query()->valid()->where('user_id', $target->uid)->count(), 'right removed');
    }

    #[Test]
    public function saving_a_user_without_changing_rights_keeps_the_devices(): void
    {
        $root = $this->admin(['email' => 'root@example.com']);
        $target = $this->admin(['email' => 'target@example.com']);
        $this->deviceFor($target);

        $this->actingAs($root)->put('/admin/users/'.$target->uid, [
            'alias' => 'New alias',
            'email' => 'target@example.com',
            'status' => 'enabled',
            'role' => 'admin',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame(1, TrustedDevice::query()->valid()->where('user_id', $target->uid)->count());
    }

    #[Test]
    public function my_devices_lists_marks_the_current_one_and_removes_single_or_all(): void
    {
        $admin = $this->admin();
        [$token, $current] = $this->deviceFor($admin, ['label' => 'Firefox auf Windows']);
        [, $other] = $this->deviceFor($admin, ['label' => 'Safari auf iOS']);

        $page = $this->actingAs($admin)->withCookies([$this->cookieName($admin) => $token])->get('/my-account');
        $page->assertOk()->assertSee('Meine Geräte')->assertSee('Firefox auf Windows')->assertSee('Safari auf iOS')->assertSee('Dieses Gerät');
        $this->assertSame(1, preg_match_all('/>\s*Dieses Gerät\s*</', $page->getContent()));

        $this->delete('/my-account/devices/'.$other->id)->assertRedirect(route('account.edit'));
        $this->assertNotNull($other->fresh()->revoked_at);
        $this->assertNull($current->fresh()->revoked_at);

        $this->delete('/my-account/devices')->assertRedirect(route('account.edit'));
        $this->assertNotNull($current->fresh()->revoked_at);
    }

    #[Test]
    public function devices_of_other_accounts_cannot_be_removed(): void
    {
        $admin = $this->admin();
        $other = $this->admin(['email' => 'other@example.com']);
        [, $foreign] = $this->deviceFor($other);

        $this->actingAs($admin)->delete('/my-account/devices/'.$foreign->id)->assertNotFound();
        $this->assertNull($foreign->fresh()->revoked_at);
    }

    #[Test]
    public function the_devices_card_is_hidden_for_members_without_devices(): void
    {
        $this->actingAs($this->member())->get('/my-account')->assertOk()->assertDontSee('Meine Geräte');
    }

    #[Test]
    public function the_reset_command_revokes_every_device_of_the_account(): void
    {
        $admin = $this->admin();
        $this->deviceFor($admin);
        $this->deviceFor($admin);
        $other = $this->admin(['email' => 'other@example.com']);
        $this->deviceFor($other);

        $this->artisan('security:reset-devices', ['user' => 'admin@example.com'])->assertSuccessful();

        $this->assertSame(0, TrustedDevice::query()->valid()->where('user_id', $admin->uid)->count());
        $this->assertSame(1, TrustedDevice::query()->valid()->where('user_id', $other->uid)->count());

        $this->artisan('security:reset-devices', ['user' => 'nobody@example.com'])->assertFailed();
    }

    #[Test]
    public function the_device_confirmation_is_not_a_risk_signal_in_the_score(): void
    {
        $admin = $this->admin();
        LoginHistory::record((int) $admin->uid, '1.1.1.1', 'AT', 'success', ['new_device']);
        LoginHistory::record((int) $admin->uid, '1.1.1.1', 'AT', 'success', ['new_device', 'unusual_country']);

        $this->assertSame(1, LoginHistory::riskScore((int) $admin->uid));
    }

    #[Test]
    public function labels_come_from_the_user_agent_and_fall_back_for_unknown_ones(): void
    {
        $this->assertSame('Firefox auf Windows', TrustedDeviceService::label('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0'));
        $this->assertSame('Safari auf iOS', TrustedDeviceService::label('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Unbekanntes Gerät', TrustedDeviceService::label(''));
    }
}
