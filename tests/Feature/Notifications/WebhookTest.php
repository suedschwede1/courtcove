<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    private const HEADER = 'X-Test-Signature';

    private const TOKEN = 'token-test';

    private const BASE = 'https://hooks.example.test';

    private const STATUS = '/webhooks/whatsapp/status';

    private const INBOUND = '/webhooks/whatsapp/inbound';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'whatsapp.signature_header' => self::HEADER,
            'whatsapp.auth_token' => self::TOKEN,
            'whatsapp.webhook_base_url' => self::BASE,
        ]);
        Cache::flush();
    }

    /** @param array<string, string> $params */
    private function sign(string $path, array $params, string $base = self::BASE, string $token = self::TOKEN): string
    {
        ksort($params);
        $data = $base.$path;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, $token, true));
    }

    /**
     * @param  array<string, string>  $params
     * @param  array<string, string>  $server
     */
    private function hook(string $path, array $params, ?string $signature = null, array $server = []): TestResponse
    {
        $server += ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_'.strtoupper(str_replace('-', '_', self::HEADER)) => $signature ?? $this->sign($path, $params)];

        // the raw body is what the signature middleware reads (like a real provider request)
        return $this->call('POST', $path, $params, [], [], $server, http_build_query($params));
    }

    /** @param array<string, mixed> $overrides */
    private function entry(array $overrides = []): NotificationLogEntry
    {
        return NotificationLogEntry::create($overrides + [
            'type' => 'player_search', 'channel' => 'whatsapp', 'user_id' => null,
            'subject_type' => 'booking', 'subject_id' => 1, 'dedupe_key' => 'k'.uniqid(),
            'status' => NotificationLogEntry::SENT, 'priority' => 0, 'attempts' => 1,
            'not_before' => now(), 'provider_sid' => 'SMtest1',
        ]);
    }

    private function messages(string $body): int
    {
        return substr_count($body, '<Message>');
    }

    #[Test]
    public function valid_signature_returns_200(): void
    {
        $this->hook(self::STATUS, ['MessageSid' => 'SMnone', 'MessageStatus' => 'sent'])->assertOk();
    }

    #[Test]
    public function tampered_param_returns_403(): void
    {
        $signature = $this->sign(self::STATUS, ['MessageSid' => 'SMtest1', 'MessageStatus' => 'sent']);
        $this->entry();

        $this->hook(self::STATUS, ['MessageSid' => 'SMtest1', 'MessageStatus' => 'delivered'], $signature)->assertForbidden();
        $this->assertSame(NotificationLogEntry::SENT, NotificationLogEntry::query()->first()->status);
    }

    #[Test]
    public function missing_header_returns_403_and_logs_no_payload(): void
    {
        Log::spy();
        $params = ['From' => 'whatsapp:+436600000000', 'Body' => 'geheim'];

        $this->hook(self::INBOUND, $params, 'bad')->assertForbidden();
        $this->call('POST', self::INBOUND, $params)->assertForbidden();

        Log::shouldHaveReceived('warning')->atLeast()->once();
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []): bool {
            $flat = $message.json_encode($context);

            return ! str_contains($flat, 'geheim') && ! str_contains($flat, '6600000000');
        });
    }

    #[Test]
    public function no_token_configured_returns_403(): void
    {
        config(['whatsapp.auth_token' => null]);
        $params = ['MessageSid' => 'SMtest1', 'MessageStatus' => 'sent'];

        $this->hook(self::STATUS, $params, $this->sign(self::STATUS, $params, token: ''))->assertForbidden();
    }

    #[Test]
    public function no_header_name_configured_returns_403(): void
    {
        config(['whatsapp.signature_header' => null]);

        $this->hook(self::STATUS, ['MessageSid' => 'SMtest1', 'MessageStatus' => 'sent'])->assertForbidden();
    }

    #[Test]
    public function signature_uses_configured_base_url_not_host_header(): void
    {
        $params = ['MessageSid' => 'SMnone', 'MessageStatus' => 'sent'];

        // signed against the request host instead of the configured base: rejected
        $this->hook(self::STATUS, $params, $this->sign(self::STATUS, $params, 'http://evil.example.test'), ['HTTP_HOST' => 'evil.example.test'])->assertForbidden();
        // signed against the configured base while the Host header differs: accepted
        $this->hook(self::STATUS, $params, null, ['HTTP_HOST' => 'evil.example.test'])->assertOk();
    }

    #[Test]
    public function base_url_falls_back_to_app_url(): void
    {
        config(['whatsapp.webhook_base_url' => null, 'app.url' => 'https://app.example.test']);
        $params = ['MessageSid' => 'SMnone', 'MessageStatus' => 'sent'];

        $this->hook(self::STATUS, $params, $this->sign(self::STATUS, $params, 'https://app.example.test'))->assertOk();
    }

    #[Test]
    public function status_updates_row_by_sid(): void
    {
        Carbon::setTestNow('2026-11-08 12:00:00');
        $row = $this->entry();

        $this->hook(self::STATUS, ['MessageSid' => 'SMtest1', 'MessageStatus' => 'delivered'])->assertOk();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::DELIVERED, $row->status);
        $this->assertSame('2026-11-08 12:00:00', $row->delivered_at->format('Y-m-d H:i:s'));

        $failed = $this->entry(['provider_sid' => 'SMtest2']);
        $this->hook(self::STATUS, ['MessageSid' => 'SMtest2', 'MessageStatus' => 'undelivered', 'ErrorCode' => '63016'])->assertOk();
        $failed->refresh();
        $this->assertSame(NotificationLogEntry::UNDELIVERED, $failed->status);
        $this->assertSame('63016', $failed->error_code);
    }

    #[Test]
    public function unknown_sid_is_ignored(): void
    {
        $row = $this->entry();

        $this->hook(self::STATUS, ['MessageSid' => 'SMother', 'MessageStatus' => 'delivered'])->assertOk();

        $this->assertSame(NotificationLogEntry::SENT, $row->fresh()->status);
    }

    #[Test]
    public function status_never_moves_backwards(): void
    {
        $row = $this->entry(['status' => NotificationLogEntry::READ, 'delivered_at' => now()]);

        foreach (['sent', 'delivered', 'failed', 'undelivered'] as $status) {
            $this->hook(self::STATUS, ['MessageSid' => 'SMtest1', 'MessageStatus' => $status, 'ErrorCode' => '1'])->assertOk();
            $this->assertSame(NotificationLogEntry::READ, $row->fresh()->status, $status);
        }

        $delivered = $this->entry(['provider_sid' => 'SMtest3', 'status' => NotificationLogEntry::DELIVERED]);
        $this->hook(self::STATUS, ['MessageSid' => 'SMtest3', 'MessageStatus' => 'sent'])->assertOk();
        $this->assertSame(NotificationLogEntry::DELIVERED, $delivered->fresh()->status);

        $this->hook(self::STATUS, ['MessageSid' => 'SMtest3', 'MessageStatus' => 'read'])->assertOk();
        $this->assertSame(NotificationLogEntry::READ, $delivered->fresh()->status);
    }

    #[Test]
    public function status_resolves_sending_rows_and_stale_failures(): void
    {
        $sending = $this->entry(['status' => NotificationLogEntry::SENDING]);
        $this->hook(self::STATUS, ['MessageSid' => 'SMtest1', 'MessageStatus' => 'delivered'])->assertOk();
        $this->assertSame(NotificationLogEntry::DELIVERED, $sending->fresh()->status);

        $stale = $this->entry(['provider_sid' => 'SMtest4', 'status' => NotificationLogEntry::FAILED, 'error_code' => 'stale']);
        $this->hook(self::STATUS, ['MessageSid' => 'SMtest4', 'MessageStatus' => 'delivered'])->assertOk();
        $stale->refresh();
        $this->assertSame(NotificationLogEntry::DELIVERED, $stale->status);
        $this->assertNull($stale->error_code);

        $failed = $this->entry(['provider_sid' => 'SMtest5', 'status' => NotificationLogEntry::FAILED, 'error_code' => '63016']);
        $this->hook(self::STATUS, ['MessageSid' => 'SMtest5', 'MessageStatus' => 'delivered'])->assertOk();
        $this->assertSame(NotificationLogEntry::FAILED, $failed->fresh()->status);
    }

    #[Test]
    public function stop_revokes_all_active_consents_of_that_number_and_replies_once(): void
    {
        $a = $this->optedInUser('+43 660 0000000');
        $other = $this->optedInUser('+43 660 0000001');

        $response = $this->hook(self::INBOUND, ['From' => 'whatsapp:+436600000000', 'Body' => '  stopp ']);

        $response->assertOk();
        $this->assertSame(0, $this->activeConsentCount($a));
        $this->assertSame('stop', $this->lastRevokedVia($a));
        $this->assertSame(1, $this->activeConsentCount($other));
        $this->assertSame(1, $this->messages($response->getContent()));
        $this->assertStringContainsString(__('booking.notifications.webhook.stop_confirmed', ['url' => route('account.edit')], 'de'), html_entity_decode($response->getContent()));
    }

    #[Test]
    public function all_stop_keywords_work_case_insensitively(): void
    {
        foreach (['STOP', 'Stopp', 'abmelden', 'UnSubscribe'] as $word) {
            $user = $this->optedInUser();
            $this->hook(self::INBOUND, ['From' => 'whatsapp:+436600000000', 'Body' => $word])->assertOk();
            $this->assertSame(0, $this->activeConsentCount($user), $word);
        }
    }

    #[Test]
    public function stop_of_unknown_number_is_ok_and_replies_nothing_personal(): void
    {
        $user = $this->optedInUser('+43 660 0000000');

        $response = $this->hook(self::INBOUND, ['From' => 'whatsapp:+436600000099', 'Body' => 'STOP'])->assertOk();

        $this->assertSame(1, $this->activeConsentCount($user));
        $this->assertStringNotContainsString('6600000099', $response->getContent());
        $this->assertStringNotContainsString((string) $user->alias, $response->getContent());
    }

    #[Test]
    public function start_does_not_create_consent(): void
    {
        $user = $this->eligibleUser();

        $response = $this->hook(self::INBOUND, ['From' => 'whatsapp:+436600000000', 'Body' => 'start'])->assertOk();

        $this->assertSame(0, NotificationConsent::query()->count());
        $this->assertStringContainsString(route('account.edit'), html_entity_decode($response->getContent()));
        $this->assertSame(0, $this->activeConsentCount($user));
    }

    #[Test]
    public function help_reply_limited_to_one_per_day(): void
    {
        Carbon::setTestNow('2026-11-08 09:00:00');
        $params = ['From' => 'whatsapp:+436600000000', 'Body' => 'hallo'];

        $first = $this->hook(self::INBOUND, $params)->assertOk();
        $second = $this->hook(self::INBOUND, $params)->assertOk();

        $this->assertSame(1, $this->messages($first->getContent()));
        $this->assertStringContainsString(route('account.edit'), html_entity_decode($first->getContent()));
        $this->assertSame(0, $this->messages($second->getContent()));

        Carbon::setTestNow('2026-11-09 09:00:00');
        $third = $this->hook(self::INBOUND, $params)->assertOk();
        $this->assertSame(1, $this->messages($third->getContent()));
    }

    #[Test]
    public function reply_texts_exist_in_both_languages(): void
    {
        foreach (['stop_confirmed', 'start', 'help'] as $key) {
            foreach (['de', 'en'] as $locale) {
                $this->assertNotSame('booking.notifications.webhook.'.$key, trans('booking.notifications.webhook.'.$key, [], $locale), "$key $locale");
            }
        }
    }

    #[Test]
    public function webhook_routes_have_no_csrf_error_but_other_posts_still_do(): void
    {
        // webhook routes: no session and no CSRF check (the signed requests above pass without a token)
        $this->hook(self::STATUS, ['MessageSid' => 'SMnone', 'MessageStatus' => 'sent'])->assertOk();

        // a normal POST route without token is still rejected (Laravel skips the CSRF check while the environment is `testing`)
        $this->app['env'] = 'local';
        $this->post('/login', ['alias' => 'x', 'password' => 'y'])->assertStatus(419);
    }

    #[Test]
    public function reply_is_in_response_body_and_does_not_touch_budget(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        $this->optedInUser();
        $usageBefore = DB::table('notification_usage_monthly')->count();
        $logBefore = NotificationLogEntry::query()->count();

        $response = $this->hook(self::INBOUND, ['From' => 'whatsapp:+436600000000', 'Body' => 'STOP'])->assertOk();

        $this->assertStringContainsString('<Response>', $response->getContent());
        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));
        Http::assertNothingSent();
        $this->assertSame($usageBefore, DB::table('notification_usage_monthly')->count());
        $this->assertSame($logBefore, NotificationLogEntry::query()->count());
    }

    #[Test]
    public function sender_with_whatsapp_prefix_matches_hash(): void
    {
        $user = $this->optedInUser('+43 660 0000000');

        $this->hook(self::INBOUND, ['From' => 'whatsapp:+43 660 0000000', 'Body' => 'STOP'])->assertOk();

        $this->assertSame(0, $this->activeConsentCount($user));
    }

    #[Test]
    public function unparsable_sender_returns_200_without_effect(): void
    {
        $user = $this->optedInUser();

        foreach (['', 'whatsapp:', 'whatsapp:abc', 'whatsapp:+43'] as $from) {
            $response = $this->hook(self::INBOUND, ['From' => $from, 'Body' => 'STOP'])->assertOk();
            $this->assertSame(0, $this->messages($response->getContent()));
        }
        $this->assertSame(1, $this->activeConsentCount($user));
    }
}
