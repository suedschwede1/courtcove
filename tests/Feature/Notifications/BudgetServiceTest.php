<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Mail\WhatsAppBudgetAlert;
use App\Models\NotificationUsageMonthly;
use App\Models\Option;
use App\Services\Notifications\BudgetService;
use App\Support\Mail\SystemMailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PRICE = 50_000; // 0.05 per message

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

    private function opt(string $key, string $value): void
    {
        Option::updateOrCreate(['key' => $key], ['value' => $value, 'locale' => null]);
    }

    private function budgetOf(int $messages): void
    {
        $this->opt('whatsapp.budget_monthly', number_format($messages * self::PRICE / 1_000_000, 2, '.', ''));
        $this->opt('whatsapp.price_estimate', number_format(self::PRICE / 1_000_000, 2, '.', ''));
    }

    private function service(): BudgetService
    {
        return app(BudgetService::class);
    }

    private function mailOn(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test']);
    }

    #[Test]
    public function budget_zero_reserves_nothing(): void
    {
        $this->assertNull($this->service()->reserve(self::PRICE));
        $this->assertSame(0, NotificationUsageMonthly::count());
    }

    #[Test]
    public function reserve_blocks_at_budget_exactly(): void
    {
        $this->budgetOf(3);

        for ($i = 0; $i < 3; $i++) {
            $this->assertNotNull($this->service()->reserve(self::PRICE), "reservation $i");
        }
        $this->assertNull($this->service()->reserve(self::PRICE));

        $row = NotificationUsageMonthly::firstOrFail();
        $this->assertSame('2026-11', $row->month);
        $this->assertSame(3, $row->messages_reserved);
        $this->assertSame(3 * self::PRICE, $row->estimated_micros);
    }

    #[Test]
    public function accepted_send_moves_reserved_to_sent_and_keeps_the_cost(): void
    {
        $this->budgetOf(3);
        $r = $this->service()->reserve(self::PRICE);

        $this->service()->settle($r, true);

        $row = NotificationUsageMonthly::firstOrFail();
        $this->assertSame(1, $row->messages_sent);
        $this->assertSame(0, $row->messages_reserved);
        $this->assertSame(self::PRICE, $row->estimated_micros);
    }

    #[Test]
    public function failed_send_without_sid_counts_zero(): void
    {
        $this->budgetOf(3);
        $r = $this->service()->reserve(self::PRICE);

        $this->service()->settle($r, false);

        $row = NotificationUsageMonthly::firstOrFail();
        $this->assertSame(0, $row->messages_sent);
        $this->assertSame(0, $row->messages_reserved);
        $this->assertSame(0, $row->estimated_micros);
        $this->assertNotNull($this->service()->reserve(self::PRICE));
    }

    #[Test]
    public function month_rollover_creates_new_row(): void
    {
        $this->budgetOf(1);

        Carbon::setTestNow(Carbon::parse('2026-11-30 23:59:00', 'Europe/Vienna'));
        $r = $this->service()->reserve(self::PRICE);
        $this->assertNotNull($r);
        $this->service()->settle($r, true);
        $this->assertNull($this->service()->reserve(self::PRICE));

        Carbon::setTestNow(Carbon::parse('2026-12-01 00:01:00', 'Europe/Vienna'));
        $this->assertNotNull($this->service()->reserve(self::PRICE));

        $this->assertSame(['2026-11', '2026-12'], NotificationUsageMonthly::orderBy('month')->pluck('month')->all());
    }

    #[Test]
    public function settle_uses_the_month_of_the_reservation(): void
    {
        $this->budgetOf(2);
        Carbon::setTestNow(Carbon::parse('2026-11-30 23:59:00', 'Europe/Vienna'));
        $r = $this->service()->reserve(self::PRICE);

        Carbon::setTestNow(Carbon::parse('2026-12-01 00:01:00', 'Europe/Vienna'));
        $this->service()->settle($r, true);

        $this->assertSame(1, NotificationUsageMonthly::where('month', '2026-11')->firstOrFail()->messages_sent);
        $this->assertSame(0, NotificationUsageMonthly::where('month', '2026-12')->count());
    }

    #[Test]
    public function current_reports_usage_percent_and_previous_month(): void
    {
        $this->budgetOf(4);
        NotificationUsageMonthly::create([
            'month' => '2026-10', 'channel' => 'whatsapp', 'messages_sent' => 7, 'estimated_micros' => 350_000,
        ]);
        $r = $this->service()->reserve(self::PRICE);
        $this->service()->settle($r, true);

        $snap = $this->service()->current();

        $this->assertSame(1, $snap->messages);
        $this->assertSame(self::PRICE, $snap->estimatedMicros);
        $this->assertSame(4 * self::PRICE, $snap->budgetMicros);
        $this->assertSame(25, $snap->percent);
        $this->assertSame(['messages' => 7, 'estimatedMicros' => 350_000], $snap->previousMonth);
    }

    #[Test]
    public function threshold_state_follows_usage(): void
    {
        $this->budgetOf(5);
        $this->assertSame('ok', $this->service()->thresholdState());

        for ($i = 0; $i < 4; $i++) {
            $this->service()->reserve(self::PRICE);
        }
        $this->assertSame('warn', $this->service()->thresholdState());

        $this->service()->reserve(self::PRICE);
        $this->assertSame('stopped', $this->service()->thresholdState());
    }

    #[Test]
    public function threshold_state_is_stopped_when_the_next_message_no_longer_fits(): void
    {
        $this->opt('whatsapp.budget_monthly', '0.12');
        $this->opt('whatsapp.price_estimate', '0.05');
        $this->service()->reserve(self::PRICE);
        $this->service()->reserve(self::PRICE);

        $this->assertSame('stopped', $this->service()->thresholdState());
    }

    #[Test]
    public function alert_mails_once_at_80_and_100(): void
    {
        Mail::fake();
        $this->mailOn();
        $this->opt('whatsapp.budget_alert_email', 'owner@example.test');
        $this->budgetOf(5);

        $this->service()->sendAlertsIfNeeded();
        Mail::assertNothingSent();

        for ($i = 0; $i < 4; $i++) {
            $this->service()->reserve(self::PRICE);
        }
        $this->service()->sendAlertsIfNeeded();
        $this->service()->sendAlertsIfNeeded();
        Mail::assertSent(WhatsAppBudgetAlert::class, 1);
        Mail::assertSent(WhatsAppBudgetAlert::class, fn (WhatsAppBudgetAlert $m) => $m->hasTo('owner@example.test') && $m->level === 80);

        $this->service()->reserve(self::PRICE);
        $this->service()->sendAlertsIfNeeded();
        $this->service()->sendAlertsIfNeeded();
        Mail::assertSent(WhatsAppBudgetAlert::class, 2);
        Mail::assertSent(WhatsAppBudgetAlert::class, fn (WhatsAppBudgetAlert $m) => $m->level === 100);

        $row = NotificationUsageMonthly::firstOrFail();
        $this->assertNotNull($row->warned_80_at);
        $this->assertNotNull($row->warned_100_at);
    }

    #[Test]
    public function jumping_straight_to_100_sends_only_the_stop_mail(): void
    {
        Mail::fake();
        $this->mailOn();
        $this->opt('whatsapp.budget_alert_email', 'owner@example.test');
        $this->budgetOf(1);
        $this->service()->reserve(self::PRICE);

        $this->service()->sendAlertsIfNeeded();

        Mail::assertSent(WhatsAppBudgetAlert::class, 1);
        Mail::assertSent(WhatsAppBudgetAlert::class, fn (WhatsAppBudgetAlert $m) => $m->level === 100);
    }

    #[Test]
    public function alert_falls_back_to_the_booking_reply_to_address(): void
    {
        Mail::fake();
        $this->mailOn();
        $this->opt(SystemMailSender::OPTION_BOOKING_REPLY_TO_ADDRESS, 'club@example.test');
        $this->budgetOf(1);
        $this->service()->reserve(self::PRICE);

        $this->service()->sendAlertsIfNeeded();

        Mail::assertSent(WhatsAppBudgetAlert::class, fn (WhatsAppBudgetAlert $m) => $m->hasTo('club@example.test'));
    }

    #[Test]
    public function no_alert_when_no_recipient_resolvable_or_mail_not_configured(): void
    {
        Mail::fake();
        $this->budgetOf(1);
        $this->service()->reserve(self::PRICE);

        // mail not configured (test default mailer is array)
        $this->opt('whatsapp.budget_alert_email', 'owner@example.test');
        $this->service()->sendAlertsIfNeeded();
        Mail::assertNothingSent();

        // mail configured, but no recipient
        $this->mailOn();
        Option::where('key', 'whatsapp.budget_alert_email')->delete();
        $this->service()->sendAlertsIfNeeded();
        Mail::assertNothingSent();

        // nothing was claimed, so a later fix still alerts
        $this->assertNull(NotificationUsageMonthly::firstOrFail()->warned_100_at);
        $this->opt('whatsapp.budget_alert_email', 'owner@example.test');
        $this->service()->sendAlertsIfNeeded();
        Mail::assertSent(WhatsAppBudgetAlert::class, 1);
    }
}
