<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSubscription;
use App\Models\NotificationUsageMonthly;
use App\Models\Option;
use App\Models\User;
use App\Notifications\Types\NotificationType;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\SecretOptions;
use App\Services\Notifications\WhatsAppConfigScrub;
use App\Services\Notifications\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class WhatsAppConfigScrubTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    private const SECRET_VALUES = ['ACtest0000secret', 'token-test-secret', 'MGtest0000secret'];

    private function seedConfiguration(): void
    {
        $secrets = app(SecretOptions::class);
        $secrets->set('whatsapp.account_sid', self::SECRET_VALUES[0]);
        $secrets->set('whatsapp.auth_token', self::SECRET_VALUES[1]);
        $secrets->set('whatsapp.messaging_service_sid', self::SECRET_VALUES[2]);
        foreach ([
            'whatsapp.sender' => '+43 660 0000001',
            'whatsapp.sandbox_sender' => '+43 660 0000002',
            'whatsapp.budget_alert_email' => 'admin@example.test',
            'whatsapp.paused_reason' => 'budget_exhausted',
            'whatsapp.sandbox' => '1',
            'whatsapp.webhook_base_url' => 'https://booking.example.test',
            'whatsapp.api_base' => 'https://api.example.test',
            'whatsapp.api_messages_path' => '/m',
            'whatsapp.api_account_path' => '/a',
            'whatsapp.api_service_path' => '/s',
            'whatsapp.signature_header' => 'X-Test-Signature',
            'whatsapp.template.test.de' => 'HXtest-keep',
            'whatsapp.driver' => 'live',
            'whatsapp.enabled' => '1',
            'whatsapp.budget_monthly' => '5.00',
            'whatsapp.price_estimate' => '0.05',
            'service.name' => 'Keep me',
        ] as $key => $value) {
            Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
        }
    }

    private function seedPersonalData(): User
    {
        $user = $this->eligibleUser();
        $this->consents()->optIn($user, NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');
        NotificationSubscription::create(['user_id' => $user->uid, 'type' => 'player_search_opened', 'channel' => 'whatsapp']);
        NotificationUsageMonthly::create(['month' => '2026-11', 'channel' => 'whatsapp', 'messages_sent' => 3]);
        NotificationLogEntry::create([
            'type' => NotificationType::PlayerSearchOpened->value,
            'channel' => 'whatsapp',
            'user_id' => $user->uid,
            'subject_type' => 'player_search',
            'subject_id' => 7,
            'dedupe_key' => 'scrub:1',
            'status' => NotificationLogEntry::SENT,
            'priority' => 1,
            'subject_payload' => ['search_id' => 7],
            'attempts' => 1,
            'not_before' => now(),
        ]);

        return $user;
    }

    #[Test]
    public function scrub_removes_credentials_sets_driver_log_and_switch_off(): void
    {
        $this->seedConfiguration();

        app(WhatsAppConfigScrub::class)->scrub();

        foreach (['account_sid', 'auth_token', 'messaging_service_sid', 'sender', 'sandbox_sender', 'budget_alert_email', 'paused_reason', 'sandbox', 'webhook_base_url', 'api_base', 'api_messages_path', 'api_account_path', 'api_service_path', 'signature_header'] as $field) {
            $this->assertFalse(Option::where('key', 'whatsapp.'.$field)->exists(), $field);
        }
        $this->assertSame('log', Option::getValue('whatsapp.driver'));
        $this->assertSame('0', Option::getValue('whatsapp.enabled'));
        $settings = app(WhatsAppSettings::class);
        $this->assertSame('log', $settings->driver());
        $this->assertFalse($settings->enabled());
        $this->assertFalse($settings->sandbox());
        $this->assertNull($settings->apiBase());
        $this->assertNull($settings->webhookBaseUrl());
        // Unrelated options and the budget survive.
        $this->assertSame('Keep me', Option::getValue('service.name'));
        $this->assertSame('5.00', Option::getValue('whatsapp.budget_monthly'));
        $this->assertSame('0.05', Option::getValue('whatsapp.price_estimate'));
        $this->assertSame('HXtest-keep', Option::getValue('whatsapp.template.test.de'));
    }

    #[Test]
    public function after_scrub_no_credential_option_is_readable_and_no_real_send_is_possible(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->seedConfiguration();
        $user = $this->seedPersonalData();

        app(WhatsAppConfigScrub::class)->scrub();

        $secrets = app(SecretOptions::class);
        foreach (['whatsapp.account_sid', 'whatsapp.auth_token', 'whatsapp.messaging_service_sid'] as $key) {
            $this->assertNull($secrets->get($key), $key);
        }

        // Someone switches the live driver and the main switch on again, without .env credentials.
        foreach (['whatsapp.driver' => 'live', 'whatsapp.enabled' => '1'] as $key => $value) {
            Option::where('key', $key)->delete();
            Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
        }
        config(['whatsapp.account_sid' => null, 'whatsapp.auth_token' => null, 'whatsapp.messaging_service_sid' => null, 'whatsapp.sender' => null]);

        $this->assertNotNull(app(WhatsAppSettings::class)->readyToSend());

        NotificationLogEntry::create([
            'type' => NotificationType::PlayerSearchOpened->value,
            'channel' => 'whatsapp',
            'user_id' => $user->uid,
            'subject_type' => 'player_search',
            'subject_id' => 8,
            'dedupe_key' => 'scrub:2',
            'status' => NotificationLogEntry::PENDING,
            'priority' => 1,
            'subject_payload' => ['search_id' => 8],
            'attempts' => 0,
            'not_before' => now()->subMinute(),
        ]);

        app(NotificationDispatcher::class)->run(10, 5);

        Http::assertNothingSent();
        $this->assertSame(0, NotificationLogEntry::where('status', NotificationLogEntry::SENT)->count());
    }

    #[Test]
    public function after_scrub_no_consent_or_subscription_remains(): void
    {
        $this->seedPersonalData();
        $this->assertGreaterThan(0, NotificationConsent::count());

        $names = app(WhatsAppConfigScrub::class)->scrub();

        foreach (['notification_consents', 'notification_subscriptions', 'notification_log', 'notification_usage_monthly'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
            $this->assertContains($table, $names);
        }
    }

    #[Test]
    public function scrub_tolerates_missing_notification_tables(): void
    {
        Schema::dropIfExists('notification_usage_monthly');
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('notification_subscriptions');
        Schema::dropIfExists('notification_consents');

        $names = app(WhatsAppConfigScrub::class)->scrub();

        $this->assertContains('whatsapp.driver', $names);
        $this->assertNotContains('notification_log', $names);
        $this->assertSame('0', Option::getValue('whatsapp.enabled'));
    }

    #[Test]
    public function scrub_output_lists_names_not_values(): void
    {
        $this->seedConfiguration();
        $this->seedPersonalData();

        $names = app(WhatsAppConfigScrub::class)->scrub();

        $this->assertContains('whatsapp.account_sid', $names);
        $this->assertContains('whatsapp.auth_token', $names);
        $this->assertContains('whatsapp.messaging_service_sid', $names);
        $this->assertContains('whatsapp.budget_alert_email', $names);
        $this->assertContains('whatsapp.paused_reason', $names);
        $joined = implode(' ', $names);
        foreach ([...self::SECRET_VALUES, 'admin@example.test', 'budget_exhausted', '+43 660 0000001', '+43 660 0000002', 'enc:'] as $value) {
            $this->assertStringNotContainsString($value, $joined);
        }
        foreach ($names as $name) {
            $this->assertMatchesRegularExpression('/^[a-z_.0-9]+$/', $name);
        }
    }
}
