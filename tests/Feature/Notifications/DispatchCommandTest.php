<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Console\Scheduling\ScheduleDefinition;
use App\Console\Scheduling\ScheduleDescriber;
use App\Models\NotificationLogEntry;
use App\Models\Option;
use App\Notifications\AdminTestNotification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationDrain;
use App\Services\Notifications\Transport\FakeTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class DispatchCommandTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Europe/Vienna'));
        Cache::flush();
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

    #[Test]
    public function schedule_registers_dispatch_every_minute_and_prune_daily_without_overlapping(): void
    {
        $dispatch = ScheduleDefinition::find('notifications-dispatch');
        $prune = ScheduleDefinition::find('notifications-prune');

        $this->assertNotNull($dispatch);
        $this->assertNotNull($prune);
        $this->assertStringContainsString('notifications:dispatch', (string) $dispatch->command);
        $this->assertStringContainsString('notifications:prune', (string) $prune->command);
        $this->assertSame('* * * * *', $dispatch->getExpression());
        $this->assertSame('50 3 * * *', $prune->getExpression());
        $this->assertTrue($dispatch->withoutOverlapping);
        $this->assertTrue($prune->withoutOverlapping);
    }

    #[Test]
    public function scheduler_page_describes_both_tasks(): void
    {
        foreach (['notifications-dispatch', 'notifications-prune'] as $name) {
            foreach (['title', 'description'] as $part) {
                foreach (['de', 'en'] as $locale) {
                    $this->assertTrue(Lang::has('booking.admin.scheduler.tasks.'.$name.'.'.$part, $locale), $locale.' '.$name.' '.$part);
                }
            }
            $event = ScheduleDefinition::find($name);
            $this->assertNotSame('', (new ScheduleDescriber)->describe($event->getExpression()));
        }
    }

    #[Test]
    public function dispatch_command_sends_due_rows_with_fake_driver(): void
    {
        $this->opt('whatsapp.enabled', '1');
        $this->opt('whatsapp.driver', 'fake');
        $this->opt('whatsapp.budget_monthly', '1.00');
        $this->opt('whatsapp.price_estimate', '0.05');
        $this->opt('whatsapp.template.test.de', 'HXtest-test');
        $user = $this->optedInUser();
        app(WhatsAppChannel::class)->send($user, new AdminTestNotification);

        $this->artisan('notifications:dispatch', ['--max' => 5])
            ->expectsOutputToContain('sent=1')
            ->assertSuccessful();

        $this->assertSame(NotificationLogEntry::SENT, NotificationLogEntry::sole()->status);
        $this->assertCount(1, app(FakeTransport::class)->sent());
    }

    #[Test]
    public function dispatch_command_exits_zero_on_an_empty_run(): void
    {
        $this->artisan('notifications:dispatch')->expectsOutputToContain('sent=0')->assertSuccessful();
    }

    #[Test]
    public function lazy_drain_runs_at_most_once_a_minute_and_never_breaks_the_calendar(): void
    {
        Cache::put('player-exchange.lapse', 1, 600);
        $calls = 0;
        // The dispatcher is final: a binding that throws on resolution stands in for a failing drain.
        app()->bind(NotificationDispatcher::class, function () use (&$calls): never {
            $calls++;
            throw new \RuntimeException('provider down');
        });

        $this->get('/calendar')->assertOk();
        $this->get('/calendar')->assertOk();

        $this->assertSame(1, $calls);
    }

    #[Test]
    public function lazy_drain_runs_again_after_the_minute_is_over(): void
    {
        $calls = 0;
        app()->bind(NotificationDispatcher::class, function () use (&$calls): never {
            $calls++;
            throw new \RuntimeException('provider down');
        });

        NotificationDrain::lazy();
        NotificationDrain::lazy();
        $this->assertSame(1, $calls);

        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        NotificationDrain::lazy();
        $this->assertSame(2, $calls);
    }
}
