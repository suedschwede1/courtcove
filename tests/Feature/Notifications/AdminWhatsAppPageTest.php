<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationLogEntry;
use App\Models\Option;
use App\Models\User;
use App\Services\Notifications\SecretOptions;
use App\Services\Notifications\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Admin page "WhatsApp" (admin/whatsapp): access data, switches, templates, budget fields, change log. */
class AdminWhatsAppPageTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-test-0123456789abcdef';

    private const ACCOUNT = 'ACtest00000000000000000000000001';

    private const SERVICE = 'MGtest00000000000000000000000002';

    /** Form field of the "search closed" follow-up switch. */
    private const FOLLOWUP_FIELD = 'followup_closed';

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function opt(string $key): ?string
    {
        $value = Option::getValue($key);

        return $value === null ? null : (string) $value;
    }

    private function settings(): WhatsAppSettings
    {
        return new WhatsAppSettings(new SecretOptions);
    }

    private function page(?User $user = null): string
    {
        return $this->actingAsWithoutAdminMode($user ?? $this->admin())->get('/admin/whatsapp')->assertOk()->getContent();
    }

    #[Test]
    public function log_driver_hint_is_an_attention_notice_not_an_error(): void
    {
        $html = $this->page();

        $this->assertStringContainsString(__('booking.admin.whatsapp.driver_log_notice'), $html);
        $this->assertStringNotContainsString('ds-notice--problem', $html);
    }

    /** @param array<string, mixed> $data */
    private function save(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAsWithoutAdminMode($user ?? $this->admin())->put('/admin/whatsapp', $data);
    }

    private function providerConfig(): void
    {
        config([
            'whatsapp.api_base' => 'https://api.example.test',
            'whatsapp.api_messages_path' => '/m',
            'whatsapp.api_account_path' => '/a',
            'whatsapp.api_service_path' => '/s',
            'whatsapp.signature_header' => 'X-Test-Signature',
        ]);
    }

    #[Test]
    public function page_requires_admin_config_but_not_admin_mode(): void
    {
        $this->actingAsWithoutAdminMode($this->admin())->get('/admin/whatsapp')->assertOk();

        $member = User::factory()->create(['status' => 'enabled']);
        $this->actingAsAdminMode($member)->get('/admin/whatsapp')->assertForbidden();
        $this->actingAsAdminMode($member)->put('/admin/whatsapp', [])->assertForbidden();
    }

    #[Test]
    public function guests_are_redirected(): void
    {
        $this->get('/admin/whatsapp')->assertRedirect();
    }

    #[Test]
    public function secret_fields_are_never_prefilled_or_present_in_html(): void
    {
        $secrets = new SecretOptions;
        $secrets->set('whatsapp.auth_token', self::TOKEN);
        $secrets->set('whatsapp.account_sid', self::ACCOUNT);
        $secrets->set('whatsapp.messaging_service_sid', self::SERVICE);

        $html = $this->page();

        $this->assertStringNotContainsString(self::TOKEN, $html);
        $this->assertStringNotContainsString(self::ACCOUNT, $html);
        $this->assertStringNotContainsString(self::SERVICE, $html);
        $this->assertStringContainsString('****0001', $html);
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.token_set')), $html);
    }

    #[Test]
    public function empty_token_field_keeps_the_stored_token(): void
    {
        (new SecretOptions)->set('whatsapp.auth_token', self::TOKEN);

        $this->save(['auth_token' => '', 'driver' => 'log'])->assertRedirect(route('admin.whatsapp.edit'));

        $this->assertSame(self::TOKEN, $this->settings()->authToken());
    }

    #[Test]
    public function new_token_is_stored_encrypted(): void
    {
        $this->save(['auth_token' => self::TOKEN, 'account_sid' => self::ACCOUNT, 'driver' => 'log'])->assertSessionHasNoErrors();

        $raw = (string) $this->opt('whatsapp.auth_token');
        $this->assertStringStartsWith('enc:', $raw);
        $this->assertStringNotContainsString(self::TOKEN, $raw);
        $this->assertSame(self::TOKEN, Crypt::decryptString(substr($raw, 4)));
        $this->assertSame(self::ACCOUNT, $this->settings()->accountSid());
        $this->assertStringNotContainsString(self::ACCOUNT, (string) $this->opt('whatsapp.account_sid'));
    }

    #[Test]
    public function env_value_locks_the_field_and_ignores_input(): void
    {
        config(['whatsapp.sender' => 'whatsapp:+436600000000', 'whatsapp.auth_token' => 'env-token-test']);

        $html = $this->page();
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.from_env')), $html);
        $this->assertStringContainsString('whatsapp:+436600000000', $html);
        $this->assertStringNotContainsString('env-token-test', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="sender"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="auth_token"[^>]*disabled/', $html);

        $this->save(['sender' => 'whatsapp:+436600000066', 'auth_token' => 'other-token', 'driver' => 'log'])->assertSessionHasNoErrors();

        $this->assertNull($this->opt('whatsapp.sender'));
        $this->assertNull($this->opt('whatsapp.auth_token'));
    }

    #[Test]
    public function enable_refused_without_budget_and_price(): void
    {
        $this->save(['enabled' => '1', 'driver' => 'log'])->assertSessionHasErrors('enabled');
        $this->assertFalse($this->settings()->enabled());

        $this->save(['enabled' => '1', 'driver' => 'log', 'budget_monthly' => '20', 'price_estimate' => '0,05'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->settings()->enabled());
        $this->assertSame(50_000, $this->settings()->priceEstimateMicros());
    }

    #[Test]
    public function an_enabled_page_that_lost_its_budget_is_switched_off_again(): void
    {
        Option::create(['key' => 'whatsapp.enabled', 'value' => '1', 'locale' => null]);

        $this->save(['enabled' => '1', 'driver' => 'log'])->assertSessionHasErrors('enabled');
        $this->assertFalse($this->settings()->enabled());
    }

    #[Test]
    public function live_driver_requires_credentials_to_enable(): void
    {
        $this->providerConfig();
        $base = ['enabled' => '1', 'driver' => 'live', 'budget_monthly' => '20', 'price_estimate' => '0.05', 'sender' => 'whatsapp:+436600000000'];

        $this->save($base)->assertSessionHasErrors('enabled');
        $this->assertFalse($this->settings()->enabled());

        $this->save($base + ['account_sid' => self::ACCOUNT, 'auth_token' => self::TOKEN])->assertSessionHasNoErrors();
        $this->assertTrue($this->settings()->enabled());
        $this->assertNull($this->settings()->readyToSend());
    }

    #[Test]
    public function live_driver_without_provider_config_cannot_be_enabled_and_the_page_says_so(): void
    {
        $this->save([
            'enabled' => '1', 'driver' => 'live', 'budget_monthly' => '20', 'price_estimate' => '0.05',
            'sender' => 'whatsapp:+436600000000', 'account_sid' => self::ACCOUNT, 'auth_token' => self::TOKEN,
        ])->assertSessionHasErrors('enabled');
        $this->assertFalse($this->settings()->enabled());

        $this->assertStringContainsString(e(__('booking.admin.whatsapp.config_missing')), $this->page());
    }

    #[Test]
    public function audit_line_contains_field_names_but_no_values(): void
    {
        Log::spy();
        $admin = $this->admin();

        $this->save(['auth_token' => self::TOKEN, 'account_sid' => self::ACCOUNT, 'budget_monthly' => '77', 'driver' => 'log'], $admin)
            ->assertSessionHasNoErrors();

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []) use ($admin): bool {
            if ($message !== 'admin.whatsapp.config_changed') {
                return false;
            }
            $dump = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $this->assertStringContainsString('budget_monthly', $dump);
            $this->assertStringContainsString('Auth-Token geändert', $dump);
            $this->assertSame('whatsapp', $context['area']);
            $this->assertNotNull($admin->getKey());
            $this->assertSame((int) $admin->getKey(), $context['wer']);
            $this->assertArrayHasKey('wann', $context);
            foreach ([self::TOKEN, self::ACCOUNT, '77'] as $value) {
                $this->assertStringNotContainsString($value, $dump);
            }

            return true;
        })->once();
    }

    #[Test]
    public function an_unchanged_save_writes_no_audit_line(): void
    {
        $this->save(['driver' => 'log']);
        Log::spy();

        $this->save(['driver' => 'log'])->assertSessionHasNoErrors();

        Log::shouldNotHaveReceived('info');
    }

    #[Test]
    public function no_reserve_field_and_no_verification_template_field(): void
    {
        $html = strtolower($this->page());

        $this->assertStringNotContainsString('reserve', $html);
        $this->assertStringNotContainsString('verification', $html);
        foreach (['player_search_opened', 'player_search_closed', 'test'] as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertStringContainsString('name="tpl_'.$key.'_'.$locale.'"', $html);
            }
        }
    }

    #[Test]
    public function templates_are_stored_per_type_and_locale(): void
    {
        $this->save(['driver' => 'log', 'tpl_player_search_opened_de' => 'HXtest01', 'tpl_test_en' => 'HXtest02'])->assertSessionHasNoErrors();

        $this->assertSame('HXtest01', $this->settings()->templateId('player_search_opened', 'de'));
        $this->assertSame('HXtest02', $this->settings()->templateId('test', 'en'));
    }

    #[Test]
    public function unreadable_secret_shows_hint_not_500(): void
    {
        Option::create(['key' => 'whatsapp.auth_token', 'value' => 'enc:not-decryptable', 'locale' => null]);

        $html = $this->page();

        $this->assertStringContainsString(e(__('booking.admin.whatsapp.secret_unreadable')), $html);
        $this->assertStringNotContainsString('enc:not-decryptable', $html);
    }

    #[Test]
    public function validation_errors_do_not_echo_secrets(): void
    {
        $response = $this->save(['auth_token' => self::TOKEN, 'account_sid' => self::ACCOUNT, 'messaging_service_sid' => self::SERVICE, 'budget_monthly' => 'abc', 'driver' => 'log']);

        $response->assertSessionHasErrors('budget_monthly');
        $old = session()->getOldInput();
        $this->assertArrayNotHasKey('auth_token', $old);
        $this->assertArrayNotHasKey('account_sid', $old);
        $this->assertArrayNotHasKey('messaging_service_sid', $old);
        $this->assertSame('abc', $old['budget_monthly']);
        $this->assertNull($this->opt('whatsapp.auth_token'));

        $html = $this->followingRedirects()->actingAsWithoutAdminMode($this->admin())->get('/admin/whatsapp')->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $html);
        $this->assertStringNotContainsString(self::ACCOUNT, $html);
    }

    #[Test]
    public function warning_shown_for_recent_template_missing_or_failed(): void
    {
        $row = fn (string $status, ?string $reason, int $n, \DateTimeInterface $at) => (new NotificationLogEntry)->forceFill([
            'type' => 'whatsapp.test', 'channel' => 'whatsapp', 'user_id' => null, 'subject_type' => 'admin', 'subject_id' => $n,
            'dedupe_key' => "k$n", 'status' => $status, 'skip_reason' => $reason, 'priority' => 10, 'not_before' => $at, 'created_at' => $at,
        ])->save();
        $this->assertStringNotContainsString('data-whatsapp-warning', $this->page());

        $row('failed', null, 1, now()->subDay());
        $row('skipped', 'template_missing', 2, now()->subDays(2));
        $row('skipped', 'budget', 3, now()->subDay());
        $row('failed', null, 4, now()->subDays(9));

        $this->assertStringContainsString('data-whatsapp-warning="2"', $this->page());
    }

    #[Test]
    public function nav_entry_exists_in_settings_group(): void
    {
        $html = $this->actingAsWithoutAdminMode($this->admin())->get('/admin')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('admin.whatsapp.edit').'"', $html);

        $assist = User::factory()->create(['status' => 'assist']);
        $assist->syncPrivileges(['admin.see-menu', 'admin.booking']);
        $html = $this->actingAsWithoutAdminMode($assist)->get('/admin')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.route('admin.whatsapp.edit').'"', $html);
    }

    #[Test]
    public function de_en_parity_for_admin_whatsapp_keys(): void
    {
        $keys = static fn (string $locale): array => array_values(array_filter(
            array_keys(Arr::dot(require base_path("lang/$locale/booking/admin.php"))),
            static fn (string $k): bool => str_starts_with($k, 'admin.whatsapp.'),
        ));

        $this->assertNotEmpty($keys('de'));
        $this->assertEqualsCanonicalizing($keys('de'), $keys('en'));
    }

    #[Test]
    public function manipulated_fake_driver_is_rejected_unless_already_stored(): void
    {
        $this->save(['driver' => 'fake'])->assertSessionHasErrors('driver');
        $this->assertNull($this->opt('whatsapp.driver'));

        Option::create(['key' => 'whatsapp.driver', 'value' => 'fake', 'locale' => null]);
        $this->save(['driver' => 'fake'])->assertSessionHasNoErrors();
    }

    #[Test]
    public function audit_names_driver_switch_and_budget_changes_without_values(): void
    {
        Log::spy();

        $this->save(['driver' => 'live', 'enabled' => '1', 'budget_monthly' => '31', 'price_estimate' => '0.07', 'sender' => 'whatsapp:+436600000000']);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []): bool {
            if ($message !== 'admin.whatsapp.config_changed') {
                return false;
            }
            $this->assertContains('driver', $context['fields']);
            $this->assertContains('budget_monthly', $context['fields']);
            $this->assertNotContains('enabled', $context['fields']); // refused: live without credentials
            $dump = json_encode($context, JSON_THROW_ON_ERROR);
            foreach (['live', '31', '0.07', '+436600000000'] as $value) {
                $this->assertStringNotContainsString($value, $dump);
            }

            return true;
        })->once();

        Log::spy();
        $this->save(['driver' => 'log', 'enabled' => '1', 'budget_monthly' => '31', 'price_estimate' => '0.07']);
        Log::shouldHaveReceived('info')->withArgs(
            fn (string $m, array $c = []): bool => $m === 'admin.whatsapp.config_changed' && in_array('enabled', $c['fields'], true) && in_array('driver', $c['fields'], true),
        )->once();
    }

    #[Test]
    public function followup_switch_reads_and_writes_the_type_option_only(): void
    {
        $typeOption = 'whatsapp.type.player_search.closed.enabled';
        $checked = fn (string $html): bool => preg_match('/name="'.preg_quote(self::FOLLOWUP_FIELD, '/').'"[^>]*checked/', $html) === 1;

        // The old option is only a read fallback while the type option is unset.
        Option::create(['key' => 'whatsapp.followup_closed', 'value' => '1', 'locale' => null]);
        $this->assertTrue($checked($this->page()));

        // The type option wins over the old one.
        Option::create(['key' => $typeOption, 'value' => '0', 'locale' => null]);
        $this->assertFalse($checked($this->page()));

        // Saving writes the type option and drops the old one.
        $this->save([self::FOLLOWUP_FIELD => '1'])->assertRedirect();
        $this->assertSame('1', $this->opt($typeOption));
        $this->assertNull($this->opt('whatsapp.followup_closed'));
        $this->assertTrue($this->settings()->followupClosed());

        $this->save([])->assertRedirect();
        $this->assertSame('0', $this->opt($typeOption));
        $this->assertFalse($this->settings()->followupClosed());
    }
}
