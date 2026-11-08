<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use App\Models\NotificationUsageMonthly;
use App\Models\Option;
use App\Models\User;
use App\Notifications\Types\NotificationType;
use App\Services\Notifications\ConsentService;
use App\Services\Notifications\SecretOptions;
use App\Services\Notifications\Transport\FakeTransport;
use App\Services\Notifications\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Admin page "WhatsApp": connection test, test message, budget block, dashboard hint, resume. */
class AdminWhatsAppActionsTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+43 660 0000042';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Europe/Vienna'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function opt(string $key, ?string $value): void
    {
        if ($value === null) {
            Option::where('key', $key)->delete();

            return;
        }
        Option::updateOrCreate(['key' => $key], ['value' => $value, 'locale' => null]);
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function as(User $user): self
    {
        $this->actingAsWithoutAdminMode($user);

        return $this;
    }

    private function liveConfig(): void
    {
        config([
            'whatsapp.api_base' => 'https://api.example.test',
            'whatsapp.api_messages_path' => '/m',
            'whatsapp.api_account_path' => '/a',
            'whatsapp.api_service_path' => '/s',
            'whatsapp.signature_header' => 'X-Test-Signature',
        ]);
        $this->opt('whatsapp.driver', 'live');
        $secrets = new SecretOptions;
        $secrets->set('whatsapp.account_sid', 'ACtest00000000000000000000000001');
        $secrets->set('whatsapp.auth_token', 'token-test-0123456789abcdef');
    }

    private function readyFake(): void
    {
        $this->opt('whatsapp.enabled', '1');
        $this->opt('whatsapp.driver', 'fake');
        $this->opt('whatsapp.budget_monthly', '1.00');
        $this->opt('whatsapp.price_estimate', '0.05');
        $this->opt('whatsapp.template.test.de', 'HXtest-test');
    }

    private function adminWithConsent(bool $consent = true): User
    {
        $admin = $this->admin();
        $admin->setMeta('phone', self::PHONE);
        if ($consent) {
            NotificationConsent::create([
                'user_id' => $admin->uid,
                'channel' => 'whatsapp',
                'purpose' => NotificationConsent::PURPOSE_PLAYER_SEARCH,
                'phone_hash' => app(ConsentService::class)->phoneHash('+436600000042'),
                'phone_last2' => '42',
                'locale' => 'de',
                'consent_text_version' => 'test',
                'consented_at' => now(),
                'active_marker' => NotificationConsent::ACTIVE_MARKER,
            ]);
        }

        return $admin->fresh();
    }

    private function usage(int $messages, int $micros, string $month = '2026-11'): void
    {
        NotificationUsageMonthly::create([
            'month' => $month,
            'channel' => 'whatsapp',
            'messages_sent' => $messages,
            'estimated_micros' => $micros,
        ]);
    }

    #[Test]
    public function probe_shows_ok_or_error_code_only(): void
    {
        $this->liveConfig();
        Http::preventStrayRequests();
        Http::fake(['api.example.test/a' => Http::sequence()
            ->push(['secretfield' => 'Fantasiewert-xyz', 'message' => 'Fantasietext'], 401)
            ->push(['x' => 1], 200)]);

        $html = $this->as($this->admin())->followingRedirects()->post('/admin/whatsapp/probe')->assertOk()->getContent();

        $this->assertStringContainsString('http_401', $html);
        $this->assertStringNotContainsString('Fantasiewert-xyz', $html);
        $this->assertStringNotContainsString('Fantasietext', $html);

        $html = $this->as($this->admin())->followingRedirects()->post('/admin/whatsapp/probe')->getContent();
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.probe_ok')), $html);
    }

    #[Test]
    public function probe_does_not_store_response(): void
    {
        $this->liveConfig();
        Http::fake(['api.example.test/*' => Http::response(['secretfield' => 'Fantasiewert-xyz'], 200)]);
        $before = [Option::count(), NotificationLogEntry::count()];

        $this->as($this->admin())->post('/admin/whatsapp/probe')->assertRedirect(route('admin.whatsapp.edit'));

        $this->assertSame($before, [Option::count(), NotificationLogEntry::count()]);
        $this->assertSame(0, Option::where('value', 'like', '%Fantasiewert%')->count());
    }

    #[Test]
    public function probe_is_throttled_after_five(): void
    {
        $this->opt('whatsapp.driver', 'log');
        $admin = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->as($admin)->post('/admin/whatsapp/probe')->assertRedirect();
        }
        $this->as($admin)->post('/admin/whatsapp/probe')->assertStatus(429);
    }

    #[Test]
    public function probe_without_credentials_says_so_without_calling_the_api(): void
    {
        $this->liveConfig();
        Option::where('key', 'whatsapp.auth_token')->delete();
        Http::preventStrayRequests();
        Http::fake();

        $html = $this->as($this->admin())->followingRedirects()->post('/admin/whatsapp/probe')->getContent();

        Http::assertNothingSent();
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.probe_no_credentials')), $html);
    }

    #[Test]
    public function probe_with_log_driver_only_gives_a_hint(): void
    {
        $this->opt('whatsapp.driver', 'log');
        Http::fake();

        $html = $this->as($this->admin())->followingRedirects()->post('/admin/whatsapp/probe')->getContent();

        Http::assertNothingSent();
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.probe_log_driver')), $html);
    }

    #[Test]
    public function actions_require_admin_config(): void
    {
        $member = User::factory()->create(['status' => 'enabled']);

        $this->actingAsAdminMode($member)->post('/admin/whatsapp/probe')->assertForbidden();
        $this->actingAsAdminMode($member)->post('/admin/whatsapp/test')->assertForbidden();
        $this->actingAsAdminMode($member)->post('/admin/whatsapp/resume')->assertForbidden();
    }

    #[Test]
    public function test_message_requires_active_consent_and_gate(): void
    {
        $this->readyFake();

        $admin = $this->adminWithConsent(consent: false);
        $html = $this->as($admin)->followingRedirects()->post('/admin/whatsapp/test')->getContent();
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.test_refused.no_consent')), $html);
        $this->assertSame(0, NotificationLogEntry::count());

        $admin = $this->adminWithConsent();
        $this->opt('whatsapp.enabled', null);
        $html = $this->as($admin)->followingRedirects()->post('/admin/whatsapp/test')->getContent();
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.test_refused.disabled')), $html);
        $this->assertSame(0, NotificationLogEntry::count());

        $this->opt('whatsapp.enabled', '1');
        $admin->meta()->where('key', 'phone')->delete();
        $html = $this->as($admin->fresh())->followingRedirects()->post('/admin/whatsapp/test')->getContent();
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.test_refused.no_phone')), $html);
        $this->assertSame(0, NotificationLogEntry::count());
        $this->assertSame([], app(FakeTransport::class)->sent());
    }

    #[Test]
    public function test_message_has_no_free_number_field_and_ignores_a_posted_number(): void
    {
        $this->readyFake();
        $admin = $this->adminWithConsent();

        $html = $this->as($admin)->get('/admin/whatsapp')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<form[^>]*action="[^"]*admin\/whatsapp\/test"[^>]*>(.*?)<\/form>/s', $html, $m));
        $this->assertDoesNotMatchRegularExpression('/<input(?![^>]*type="hidden")/', $m[1]);

        $this->as($admin)->post('/admin/whatsapp/test', ['phone' => '+436601111111', 'to' => '+436601111111'])->assertRedirect();

        $sent = app(FakeTransport::class)->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('+436600000042', $sent[0]['to']);
    }

    #[Test]
    public function test_message_goes_through_fake_transport_once(): void
    {
        $this->readyFake();
        $admin = $this->adminWithConsent();

        $this->as($admin)->post('/admin/whatsapp/test')->assertRedirect(route('admin.whatsapp.edit'))->assertSessionHas('success');

        $this->assertCount(1, app(FakeTransport::class)->sent());
        $row = NotificationLogEntry::firstOrFail();
        $this->assertSame(NotificationType::AdminTest->value, $row->type);
        $this->assertSame(NotificationLogEntry::SENT, $row->status);
        $this->assertSame(1, NotificationLogEntry::count());
    }

    #[Test]
    public function budget_block_shows_percent_and_notices_at_80_and_100(): void
    {
        $this->opt('whatsapp.budget_monthly', '10.00');
        $this->opt('whatsapp.price_estimate', '0.05');
        $this->usage(12, 2000000, '2026-10');
        $this->usage(25, 3000000);

        $html = $this->as($this->admin())->get('/admin/whatsapp')->assertOk()->getContent();
        $this->assertStringContainsString('25 '.__('booking.admin.whatsapp.budget_messages'), $html);
        $this->assertStringContainsString('30 %', $html);
        $this->assertStringContainsString('<progress', $html);
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.budget_previous', ['messages' => 12, 'amount' => '2,00'])), $html);
        $this->assertStringNotContainsString('data-whatsapp-budget-notice', $html);

        NotificationUsageMonthly::where('month', '2026-11')->update(['estimated_micros' => 8500000]);
        $html = $this->as($this->admin())->get('/admin/whatsapp')->getContent();
        $this->assertStringContainsString('data-whatsapp-budget-notice="warn"', $html);

        NotificationUsageMonthly::where('month', '2026-11')->update(['estimated_micros' => 10000000]);
        $html = $this->as($this->admin())->get('/admin/whatsapp')->getContent();
        $this->assertStringContainsString('data-whatsapp-budget-notice="stopped"', $html);
        $this->assertStringContainsString('01.12.2026', $html);
    }

    #[Test]
    public function dashboard_shows_budget_and_paused_notices(): void
    {
        $this->readyFake();
        $this->usage(90, 950000);
        $this->opt('whatsapp.paused_reason', 'auth_failed');

        $html = $this->as($this->admin())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('data-whatsapp-budget-notice="warn"', $html);
        $this->assertStringContainsString('data-whatsapp-paused', $html);
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.paused')), $html);

        $this->opt('whatsapp.enabled', null);
        $html = $this->as($this->admin())->get('/admin')->getContent();
        $this->assertStringNotContainsString('data-whatsapp-budget-notice', $html);
    }

    #[Test]
    public function resume_clears_paused_reason(): void
    {
        $this->readyFake();
        $this->opt('whatsapp.paused_reason', 'auth_failed');

        $html = $this->as($this->admin())->get('/admin/whatsapp')->getContent();
        $this->assertStringContainsString(e(__('booking.admin.whatsapp.resume')), $html);

        $this->as($this->admin())->post('/admin/whatsapp/resume')->assertRedirect(route('admin.whatsapp.edit'));

        $this->assertNull(app(WhatsAppSettings::class)->pausedReason());
    }
}
