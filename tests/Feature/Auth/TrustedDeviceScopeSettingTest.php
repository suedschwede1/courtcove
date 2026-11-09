<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\LoginVerificationCode;
use App\Models\LoginHistory;
use App\Models\Option;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use App\Services\TrustedDeviceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Trusted-device scope as an admin setting (.env > admin option > default off): service, page, command. */
class TrustedDeviceScopeSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase pins the scope to off through the config (= .env); these tests start without a .env value.
        config(['booking.security.trusted_device_scope' => null]);
    }

    private function user(string $status, array $extra = []): User
    {
        return User::factory()->create(array_merge(['status' => $status, 'pw' => bcrypt('secret123')], $extra));
    }

    private function settings(): TrustedDeviceSettings
    {
        return app(TrustedDeviceSettings::class);
    }

    private function setOption(string $value): void
    {
        Option::create(['key' => TrustedDeviceSettings::OPTION, 'value' => $value, 'locale' => null]);
    }

    private function stored(): ?string
    {
        $value = Option::getValue(TrustedDeviceSettings::OPTION);

        return $value === null ? null : (string) $value;
    }

    /** Cookie of a valid device for the user. */
    private function deviceCookie(User $user): array
    {
        $token = bin2hex(random_bytes(32));
        TrustedDevice::create([
            'user_id' => $user->uid,
            'token_hash' => hash('sha256', $token),
            'label' => 'Firefox auf Windows',
            'created_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
            'expires_at' => now()->addDays(20),
        ]);

        return [app(TrustedDeviceService::class)->cookieName((int) $user->uid) => $token];
    }

    // ---- resolution -------------------------------------------------------------------------------------

    #[Test]
    public function without_env_and_admin_value_the_scope_is_off(): void
    {
        $this->assertSame('off', $this->settings()->scope());
        $this->assertSame('default', $this->settings()->source());
        $this->assertFalse(app(TrustedDeviceService::class)->isRequiredFor($this->user('admin')));
    }

    #[Test]
    public function the_admin_value_applies_and_the_env_value_wins(): void
    {
        $this->setOption('all');
        $this->assertSame('all', $this->settings()->scope());
        $this->assertSame('admin', $this->settings()->source());

        config(['booking.security.trusted_device_scope' => ' Privileged ']);
        $this->assertSame('privileged', $this->settings()->scope());
        $this->assertSame('env', $this->settings()->source());
    }

    #[Test]
    public function an_unknown_value_is_ignored(): void
    {
        $this->setOption('nonsense');
        config(['booking.security.trusted_device_scope' => 'bogus']);

        $this->assertSame('off', $this->settings()->scope());
        $this->assertSame('default', $this->settings()->source());
    }

    #[Test]
    public function each_level_covers_exactly_its_accounts(): void
    {
        $admin = $this->user('admin');
        $assistant = $this->user('assist');
        $member = $this->user('enabled');
        $matrix = [
            'off' => [false, false, false],
            'admins' => [true, false, false],
            'privileged' => [true, true, false],
            'all' => [true, true, true],
        ];
        $service = app(TrustedDeviceService::class);

        foreach ($matrix as $scope => [$forAdmin, $forAssistant, $forMember]) {
            config(['booking.security.trusted_device_scope' => $scope]);
            $this->assertSame($forAdmin, $service->isRequiredFor($admin), "admin at $scope");
            $this->assertSame($forAssistant, $service->isRequiredFor($assistant), "assistant at $scope");
            $this->assertSame($forMember, $service->isRequiredFor($member), "member at $scope");
        }
    }

    #[Test]
    public function the_all_level_makes_a_plain_member_confirm_the_device_at_sign_in(): void
    {
        Mail::fake();
        $this->setOption('all');
        $member = $this->user('enabled', ['email' => 'member@example.com']);

        $this->post('/login', ['email' => $member->email, 'password' => 'secret123'])->assertRedirect(route('login.verify.show'));

        $this->assertGuest();
        Mail::assertQueued(LoginVerificationCode::class, fn (LoginVerificationCode $mail): bool => $mail->newDevice);
    }

    // ---- page -------------------------------------------------------------------------------------------

    #[Test]
    public function the_page_needs_the_config_right(): void
    {
        $this->get(route('admin.security.edit'))->assertRedirect();
        $this->actingAs($this->user('enabled'))->get(route('admin.security.edit'))->assertForbidden();
        $this->actingAs($this->user('assist'))->get(route('admin.security.edit'))->assertForbidden();
    }

    #[Test]
    public function the_page_works_without_admin_mode_and_offers_four_radios(): void
    {
        $this->withoutAdminMode();
        $response = $this->actingAs($this->user('admin'))->get(route('admin.security.edit'))->assertOk();

        foreach (TrustedDeviceSettings::SCOPES as $scope) {
            $response->assertSee('name="scope" value="'.$scope.'"', false);
        }
        $response->assertSee('<fieldset', false)->assertSee('<legend', false);
        $this->assertMatchesRegularExpression('/value="off"[^>]*checked/', $response->getContent());
        $response->assertDontSee(__('booking.admin.security.from_env'));
    }

    #[Test]
    public function an_env_value_locks_the_page_and_ignores_a_save(): void
    {
        config(['booking.security.trusted_device_scope' => 'privileged']);
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->get(route('admin.security.edit'))->assertOk()->assertSee(__('booking.admin.security.from_env'));
        $this->assertMatchesRegularExpression('/value="privileged"[^>]*checked/', $response->getContent());
        $this->assertMatchesRegularExpression('/value="all"[^>]*disabled/', $response->getContent());

        $this->actingAs($admin)->put(route('admin.security.update'), ['scope' => 'off'])->assertSessionHasErrors('scope');
        $this->assertNull($this->stored());
        $this->assertSame('privileged', $this->settings()->scope());
    }

    #[Test]
    public function an_admin_with_a_confirmed_device_saves_the_scope_and_it_is_logged(): void
    {
        Log::spy();
        $admin = $this->user('admin');
        $cookies = $this->deviceCookie($admin);

        $this->actingAs($admin)->withCookies($cookies)
            ->put(route('admin.security.update'), ['scope' => 'privileged'])
            ->assertRedirect(route('admin.security.edit'))
            ->assertSessionHas('success')
            ->assertSessionHas('info');

        $this->assertSame('privileged', $this->stored());
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'admin.security.config_changed' && $context['wer'] === (int) $admin->uid)->once();
    }

    #[Test]
    public function narrowing_gives_no_widening_hint(): void
    {
        $admin = $this->user('admin');
        $this->setOption('all');
        $cookies = $this->deviceCookie($admin);

        $this->actingAs($admin)->withCookies($cookies)
            ->put(route('admin.security.update'), ['scope' => 'admins'])
            ->assertSessionHas('success')->assertSessionMissing('info');
        $this->assertSame('admins', $this->stored());
    }

    #[Test]
    public function an_unknown_scope_is_rejected(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->put(route('admin.security.update'), ['scope' => 'everyone'])->assertSessionHasErrors('scope');
        $this->assertNull($this->stored());
    }

    #[Test]
    public function without_a_confirmed_device_the_admin_cannot_switch_it_on_for_themselves(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->put(route('admin.security.update'), ['scope' => 'admins'])
            ->assertSessionHasErrors('scope')->assertSessionHas('needs_device', true);
        $this->assertNull($this->stored());

        // A device of someone else does not count either.
        $other = $this->user('admin');
        $this->deviceCookie($other);
        $this->actingAs($admin)->put(route('admin.security.update'), ['scope' => 'all'])->assertSessionHasErrors('scope');
        $this->assertNull($this->stored());
    }

    #[Test]
    public function switching_off_or_a_level_that_does_not_cover_the_admin_needs_no_device(): void
    {
        $admin = $this->user('admin');
        $this->setOption('all');

        $this->actingAs($admin)->put(route('admin.security.update'), ['scope' => 'off'])->assertSessionHas('success');
        $this->assertSame('off', $this->stored());

        // An assistant with the config right is not covered by "admins only".
        $assistant = $this->user('assist');
        $assistant->setMeta('allow.admin.config', 'true');
        $this->actingAs($assistant->fresh())->put(route('admin.security.update'), ['scope' => 'admins'])->assertSessionHas('success');
        $this->assertSame('admins', $this->stored());
    }

    #[Test]
    public function the_page_offers_to_confirm_this_device_after_a_refusal(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->put(route('admin.security.update'), ['scope' => 'all']);
        $this->actingAs($admin)->followingRedirects()->get(route('admin.security.edit'))
            ->assertSee(route('admin.security.device'), false);
    }

    #[Test]
    public function confirming_this_device_goes_through_the_code_and_trusts_the_browser_even_while_off(): void
    {
        Mail::fake();
        $admin = $this->user('admin', ['email' => 'admin@example.com']);

        $this->actingAs($admin)->post(route('admin.security.device'))->assertRedirect(route('login.verify.show'));
        $code = null;
        Mail::assertQueued(LoginVerificationCode::class, function (LoginVerificationCode $mail) use (&$code): bool {
            $code = $mail->code;

            return $mail->newDevice && $mail->hasTo('admin@example.com');
        });

        $this->post('/login/verify', ['code' => $code])->assertRedirect(route('admin.security.edit'))
            ->assertCookie(app(TrustedDeviceService::class)->cookieName((int) $admin->uid));
        $this->assertSame(1, TrustedDevice::query()->where('user_id', $admin->uid)->count());
    }

    #[Test]
    public function confirming_this_device_writes_no_second_login_record(): void
    {
        Mail::fake();
        $admin = $this->user('admin', ['email' => 'admin@example.com']);
        $before = LoginHistory::query()->count();

        $this->actingAs($admin)->post(route('admin.security.device'));
        $code = null;
        Mail::assertQueued(LoginVerificationCode::class, function (LoginVerificationCode $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $this->post('/login/verify', ['code' => $code])->assertRedirect(route('admin.security.edit'));

        $this->assertSame($before, LoginHistory::query()->count());
    }

    #[Test]
    public function the_force_trust_flag_does_not_leak_into_a_later_normal_sign_in(): void
    {
        Mail::fake();
        $admin = $this->user('admin', ['email' => 'admin@example.com']);

        $this->actingAs($admin)->post(route('admin.security.device'));
        $this->post('/logout');
        $this->flushSession();

        // Scope is off: a normal sign-in signs in at once and issues no device.
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret123'])->assertRedirect();
        $this->assertSame(0, TrustedDevice::query()->count());
    }

    #[Test]
    public function writing_routes_need_the_config_right_too(): void
    {
        $assistant = $this->user('assist');

        $this->actingAs($assistant)->put(route('admin.security.update'), ['scope' => 'off'])->assertForbidden();
        $this->actingAs($assistant)->post(route('admin.security.device'))->assertForbidden();
    }

    #[Test]
    public function the_command_warns_when_env_wins(): void
    {
        config(['booking.security.trusted_device_scope' => 'all']);

        $this->artisan('security:set-device-scope', ['scope' => 'off'])->expectsOutputToContain('wins')->assertSuccessful();
        $this->assertSame('all', $this->settings()->scope());
    }

    // ---- command ----------------------------------------------------------------------------------------

    #[Test]
    public function the_command_sets_the_admin_value_idempotently(): void
    {
        $this->artisan('security:set-device-scope', ['scope' => 'privileged'])->assertSuccessful();
        $this->assertSame('privileged', $this->stored());
        $this->artisan('security:set-device-scope', ['scope' => 'privileged'])->assertSuccessful();
        $this->assertSame(1, Option::where('key', TrustedDeviceSettings::OPTION)->count());
        $this->assertSame('admin', $this->settings()->source());
    }

    #[Test]
    public function the_command_refuses_an_unknown_scope(): void
    {
        $this->artisan('security:set-device-scope', ['scope' => 'maybe'])->assertFailed();
        $this->assertNull($this->stored());
    }
}
