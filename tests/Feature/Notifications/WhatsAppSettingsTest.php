<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Option;
use App\Services\Notifications\SecretOptions;
use App\Services\Notifications\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): WhatsAppSettings
    {
        return new WhatsAppSettings(new SecretOptions);
    }

    private function opt(string $key, string $value): void
    {
        Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
    }

    #[Test]
    public function env_overrides_admin_option_which_overrides_default(): void
    {
        $s = $this->settings();

        // plain fields
        foreach ([
            'driver' => ['fake', 'live', 'log'],
            'sender' => ['+43 660 0000001', '+43 660 0000002', null],
            'sandbox_sender' => ['+43 660 0000003', '+43 660 0000004', null],
            'webhook_base_url' => ['https://env.example.test', 'https://admin.example.test', null],
            'api_base' => ['https://api.example.test', 'https://api-admin.example.test', null],
            'signature_header' => ['X-Env-Signature', 'X-Admin-Signature', null],
        ] as $field => [$envValue, $adminValue, $default]) {
            config(["whatsapp.$field" => null]);
            $this->assertSame('default', $s->sourceOf($field), $field);

            $this->opt("whatsapp.$field", $adminValue);
            $this->assertSame('admin', $s->sourceOf($field), $field);
            $this->assertSame($adminValue, $s->value($field), $field);

            config(["whatsapp.$field" => $envValue]);
            $this->assertSame('env', $s->sourceOf($field), $field);
            $this->assertSame($envValue, $s->value($field), $field);

            config(["whatsapp.$field" => '']); // an empty env value counts as not set
            $this->assertSame('admin', $s->sourceOf($field), $field);

            Option::where('key', "whatsapp.$field")->delete();
            $this->assertSame($default, $s->value($field), $field);
        }

        // secrets
        $secrets = new SecretOptions;
        foreach (['account_sid' => 'ACtest0001', 'auth_token' => 'token-test-1', 'messaging_service_sid' => 'MStest0001'] as $field => $plain) {
            config(["whatsapp.$field" => null]);
            $this->assertSame('default', $s->sourceOf($field), $field);
            $secrets->set("whatsapp.$field", $plain);
            $this->assertSame('admin', $s->sourceOf($field), $field);
            $this->assertSame($plain, $this->getter($s, $field), $field);
            config(["whatsapp.$field" => 'env-'.$plain]);
            $this->assertSame('env', $s->sourceOf($field), $field);
            $this->assertSame('env-'.$plain, $this->getter($s, $field), $field);
        }

        // sandbox flag
        config(['whatsapp.sandbox' => null]);
        $this->assertFalse($s->sandbox());
        $this->opt('whatsapp.sandbox', '1');
        $this->assertTrue($s->sandbox());
        config(['whatsapp.sandbox' => '0']);
        $this->assertFalse($s->sandbox());
        $this->assertSame('env', $s->sourceOf('sandbox'));
    }

    private function getter(WhatsAppSettings $s, string $field): ?string
    {
        return match ($field) {
            'account_sid' => $s->accountSid(),
            'auth_token' => $s->authToken(),
            'messaging_service_sid' => $s->messagingServiceSid(),
        };
    }

    #[Test]
    public function driver_falls_back_to_log_for_unknown_values(): void
    {
        $this->assertSame('log', $this->settings()->driver());
        config(['whatsapp.driver' => 'bogus']);
        $this->assertSame('log', $this->settings()->driver());
        config(['whatsapp.driver' => 'live']);
        $this->assertSame('live', $this->settings()->driver());
        $this->assertSame('live', $this->settings()->effectiveDriver());
    }

    #[Test]
    public function secret_is_stored_encrypted_not_plain(): void
    {
        (new SecretOptions)->set('whatsapp.account_sid', 'ACtest0001');

        $raw = (string) DB::table('bs_options')->where('key', 'whatsapp.account_sid')->value('value');
        $this->assertStringStartsWith('enc:', $raw);
        $this->assertStringNotContainsString('ACtest', $raw);
        $this->assertStringNotContainsString('0001', $raw);
    }

    #[Test]
    public function secret_roundtrip(): void
    {
        $secrets = new SecretOptions;
        $this->assertFalse($secrets->isSet('whatsapp.auth_token'));
        $this->assertNull($secrets->get('whatsapp.auth_token'));

        $secrets->set('whatsapp.auth_token', 'token-test-1');
        $this->assertTrue($secrets->isSet('whatsapp.auth_token'));
        $this->assertSame('token-test-1', $secrets->get('whatsapp.auth_token'));
        $this->assertFalse($secrets->isUnreadable('whatsapp.auth_token'));

        $secrets->set('whatsapp.auth_token', 'token-test-2'); // overwrite keeps one row
        $this->assertSame('token-test-2', $secrets->get('whatsapp.auth_token'));
        $this->assertSame(1, Option::where('key', 'whatsapp.auth_token')->count());

        $secrets->set('whatsapp.auth_token', null);
        $this->assertFalse($secrets->isSet('whatsapp.auth_token'));
        $this->assertSame(0, Option::where('key', 'whatsapp.auth_token')->count());
    }

    #[Test]
    public function changed_app_key_makes_secret_unreadable_without_exception(): void
    {
        $secrets = new SecretOptions;
        $secrets->set('whatsapp.account_sid', 'ACtest0001');

        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
        app()->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');

        $this->assertNull($secrets->get('whatsapp.account_sid'));
        $this->assertTrue($secrets->isUnreadable('whatsapp.account_sid'));
        $this->assertNull($this->settings()->accountSid());
    }

    #[Test]
    public function mask_shows_last_four_only(): void
    {
        $this->assertSame('****', SecretOptions::mask(null));
        $this->assertSame('****', SecretOptions::mask(''));
        $this->assertSame('****', SecretOptions::mask('abcd'));
        $this->assertSame('****', SecretOptions::mask('abc'));
        $this->assertSame('****6789', SecretOptions::mask('ACtest123456789'));
    }

    #[Test]
    public function ready_to_send_names_the_missing_prerequisite(): void
    {
        $s = $this->settings();
        $secrets = new SecretOptions;

        $this->assertSame('disabled', $s->readyToSend());

        $this->opt('whatsapp.enabled', '1');
        $this->assertSame('budget_missing', $s->readyToSend());

        $this->opt('whatsapp.budget_monthly', '0');
        $this->assertSame('budget_missing', $s->readyToSend());
        Option::where('key', 'whatsapp.budget_monthly')->update(['value' => '5']);
        $this->assertSame('price_missing', $s->readyToSend());

        $this->opt('whatsapp.price_estimate', '0.05');
        $this->assertSame(5_000_000, $s->budgetMonthlyMicros());
        $this->assertSame(50_000, $s->priceEstimateMicros());

        // driver log needs neither credentials nor provider config
        $this->assertNull($s->readyToSend());

        config(['whatsapp.driver' => 'live']);
        $this->assertSame('credentials_missing', $s->readyToSend());

        $secrets->set('whatsapp.account_sid', 'ACtest0001');
        $secrets->set('whatsapp.auth_token', 'token-test-1');
        $this->assertSame('credentials_missing', $s->readyToSend()); // no sender or messaging service yet

        $this->opt('whatsapp.sender', '+43 660 0000000');
        $this->assertSame('config_missing', $s->readyToSend());

        config([
            'whatsapp.api_base' => 'https://api.example.test',
            'whatsapp.api_messages_path' => '/m',
            'whatsapp.api_account_path' => '/a',
            'whatsapp.api_service_path' => '/s',
        ]);
        $this->assertSame('config_missing', $s->readyToSend()); // signature header still missing

        config(['whatsapp.signature_header' => 'X-Test-Signature']);
        $this->assertNull($s->readyToSend());

        // sandbox mode needs the sandbox sender, a normal sender does not help
        config(['whatsapp.sandbox' => '1']);
        $this->assertSame('credentials_missing', $s->readyToSend());
        $this->opt('whatsapp.sandbox_sender', '+43 660 0000009');
        $this->assertNull($s->readyToSend());
        config(['whatsapp.sandbox' => '0']);

        // fake needs nothing either
        config(['whatsapp.driver' => 'fake', 'whatsapp.api_base' => null]);
        $this->assertNull($s->readyToSend());
    }

    #[Test]
    public function defaults_for_limits_and_flags(): void
    {
        $s = $this->settings();
        $this->assertSame(3, $s->dailyLimitPerUser());
        $this->assertSame(30, $s->maxPerRun());
        $this->assertFalse($s->typeEnabled('player_search.closed'));
        $this->assertSame('de', $s->defaultLocale());
        $this->assertSame('43', $s->defaultCountry());
        $this->assertNull($s->budgetAlertEmail());

        $this->opt('whatsapp.daily_limit_per_user', '5');
        $this->opt('whatsapp.max_per_run', '10');
        $this->opt('whatsapp.followup_closed', '1');
        $this->opt('whatsapp.budget_alert_email', 'club@example.test');
        $this->assertSame(5, $s->dailyLimitPerUser());
        $this->assertSame(10, $s->maxPerRun());
        $this->assertTrue($s->typeEnabled('player_search.closed'));
        $this->assertSame('club@example.test', $s->budgetAlertEmail());
    }

    #[Test]
    public function pause_and_resume_store_the_reason(): void
    {
        $s = $this->settings();
        $this->assertNull($s->pausedReason());
        $s->pause('budget_reached');
        $this->assertSame('budget_reached', $s->pausedReason());
        $s->pause('auth_failed');
        $this->assertSame('auth_failed', $s->pausedReason());
        $this->assertSame(1, Option::where('key', 'whatsapp.paused_reason')->count());
        $s->resume();
        $this->assertNull($s->pausedReason());
    }

    #[Test]
    public function template_falls_back_to_default_locale(): void
    {
        $s = $this->settings();
        $this->assertNull($s->templateId('search_candidate', 'en'));

        $this->opt('whatsapp.template.search_candidate.de', 'HXtest-de');
        $this->assertSame('HXtest-de', $s->templateId('search_candidate', 'de'));
        $this->assertSame('HXtest-de', $s->templateId('search_candidate', 'en'));

        $this->opt('whatsapp.template.search_candidate.en', 'HXtest-en');
        $this->assertSame('HXtest-en', $s->templateId('search_candidate', 'en'));
        $this->assertSame('HXtest-de', $s->templateId('search_candidate', 'fr'));
    }

    #[Test]
    public function missing_options_table_does_not_throw(): void
    {
        Schema::drop('bs_options');
        $s = $this->settings();
        $secrets = new SecretOptions;

        $this->assertFalse($s->enabled());
        $this->assertSame('log', $s->driver());
        $this->assertNull($s->accountSid());
        $this->assertNull($s->templateId('search_candidate', 'de'));
        $this->assertNull($s->pausedReason());
        $this->assertSame(3, $s->dailyLimitPerUser());
        $this->assertSame('disabled', $s->readyToSend());
        $this->assertFalse($secrets->isSet('whatsapp.auth_token'));
        $this->assertFalse($secrets->isUnreadable('whatsapp.auth_token'));
        $this->assertNull($secrets->get('whatsapp.auth_token'));
    }
}
