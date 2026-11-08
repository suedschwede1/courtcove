<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationLogEntry;
use App\Models\Option;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\NotificationStatistics;
use App\Services\Notifications\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Per-type settings (switch, price) and the per-type statistics of the outbox. */
class NotificationTypeSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): WhatsAppSettings
    {
        return app(WhatsAppSettings::class);
    }

    private function opt(string $key, string $value): void
    {
        Option::updateOrCreate(['key' => $key], ['value' => $value, 'locale' => null]);
    }

    #[Test]
    public function type_switch_defaults_to_the_definition_and_follows_its_option(): void
    {
        $s = $this->settings();

        $this->assertTrue($s->typeEnabled(NotificationTypes::PLAYER_SEARCH_OPENED));
        $this->assertTrue($s->typeEnabled(NotificationTypes::ADMIN_TEST));
        $this->assertFalse($s->typeEnabled(NotificationTypes::PLAYER_SEARCH_CLOSED));
        $this->assertFalse($s->typeEnabled('unknown.type'));

        $this->opt(WhatsAppSettings::typeEnabledOption(NotificationTypes::PLAYER_SEARCH_OPENED), '0');
        $this->assertSame('whatsapp.type.player_search.opened.enabled', WhatsAppSettings::typeEnabledOption(NotificationTypes::PLAYER_SEARCH_OPENED));
        $this->assertFalse($s->typeEnabled(NotificationTypes::PLAYER_SEARCH_OPENED));
        $this->opt(WhatsAppSettings::typeEnabledOption(NotificationTypes::PLAYER_SEARCH_OPENED), '1');
        $this->assertTrue($s->typeEnabled(NotificationTypes::PLAYER_SEARCH_OPENED));
    }

    #[Test]
    public function the_closed_followup_keeps_honouring_the_old_option(): void
    {
        $s = $this->settings();

        $this->opt('whatsapp.followup_closed', '1');
        $this->assertTrue($s->typeEnabled(NotificationTypes::PLAYER_SEARCH_CLOSED));
        $this->assertTrue($s->followupClosed());

        // The per-type option wins over the old one.
        $this->opt(WhatsAppSettings::typeEnabledOption(NotificationTypes::PLAYER_SEARCH_CLOSED), '0');
        $this->assertFalse($s->typeEnabled(NotificationTypes::PLAYER_SEARCH_CLOSED));
        $this->assertFalse($s->followupClosed());
    }

    #[Test]
    public function price_per_type_falls_back_to_the_global_estimate(): void
    {
        $s = $this->settings();
        $this->opt('whatsapp.price_estimate', '0.05');

        $this->assertSame(50_000, $s->priceEstimateMicros());
        $this->assertSame(50_000, $s->priceEstimateMicros(NotificationTypes::PLAYER_SEARCH_OPENED));

        $this->opt(WhatsAppSettings::typePriceOption(NotificationTypes::PLAYER_SEARCH_OPENED), '0,08');
        $this->assertSame('whatsapp.price.player_search.opened', WhatsAppSettings::typePriceOption(NotificationTypes::PLAYER_SEARCH_OPENED));
        $this->assertSame(80_000, $s->priceEstimateMicros(NotificationTypes::PLAYER_SEARCH_OPENED));
        $this->assertSame(50_000, $s->priceEstimateMicros(NotificationTypes::PLAYER_SEARCH_CLOSED));
        $this->assertSame(50_000, $s->priceEstimateMicros());

        // The admin test always books the global estimate.
        $this->opt(WhatsAppSettings::typePriceOption(NotificationTypes::ADMIN_TEST), '0.50');
        $this->assertSame(50_000, $s->priceEstimateMicros(NotificationTypes::ADMIN_TEST));

        // An unusable per-type value falls back as well.
        $this->opt(WhatsAppSettings::typePriceOption(NotificationTypes::PLAYER_SEARCH_OPENED), 'abc');
        $this->assertSame(50_000, $s->priceEstimateMicros(NotificationTypes::PLAYER_SEARCH_OPENED));
    }

    #[Test]
    public function statistics_count_sent_delivered_and_read_per_type(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Europe/Vienna'));
        try {
            $opened = NotificationTypes::PLAYER_SEARCH_OPENED;
            $this->row($opened, NotificationLogEntry::SENT, '2026-11-09 10:00');
            $this->row($opened, NotificationLogEntry::DELIVERED, '2026-11-01 00:30'); // this month (Vienna)
            $this->row($opened, NotificationLogEntry::READ, '2026-10-31 23:30');      // last month, within 30 days
            $this->row($opened, NotificationLogEntry::READ, '2026-10-05 10:00');      // older than 30 days
            $this->row($opened, NotificationLogEntry::FAILED, '2026-11-09 10:00');    // not counted
            $this->row($opened, NotificationLogEntry::SKIPPED, null);                 // not counted
            $this->row(NotificationTypes::ADMIN_TEST, NotificationLogEntry::SENT, '2026-11-09 11:00');

            $stats = app(NotificationStatistics::class);
            $all = $stats->forTypes();

            $this->assertSame(array_keys(app(NotificationRegistry::class)->types()), array_keys($all));
            $this->assertSame(['sent' => 1, 'delivered' => 1, 'read' => 0, 'total' => 2], $all[$opened]['month']);
            $this->assertSame(['sent' => 1, 'delivered' => 1, 'read' => 1, 'total' => 3], $all[$opened]['last30']);
            $this->assertSame('2026-11-09 10:00', $all[$opened]['last_sent_at']?->setTimezone('Europe/Vienna')->format('Y-m-d H:i'));
            $this->assertSame(1, $all[NotificationTypes::ADMIN_TEST]['month']['total']);
            $this->assertSame(0, $all[NotificationTypes::PLAYER_SEARCH_CLOSED]['last30']['total']);
            $this->assertNull($all[NotificationTypes::PLAYER_SEARCH_CLOSED]['last_sent_at']);
            $this->assertEquals($all[$opened], $stats->forType($opened));
        } finally {
            Carbon::setTestNow();
        }
    }

    private function row(string $type, string $status, ?string $sentAt): void
    {
        NotificationLogEntry::create([
            'type' => $type, 'channel' => 'whatsapp', 'user_id' => null,
            'subject_type' => 'player_search', 'subject_id' => 1, 'dedupe_key' => 'stat:'.Str::uuid()->toString(),
            'status' => $status, 'priority' => 20, 'attempts' => 0, 'not_before' => Carbon::now(),
            'sent_at' => $sentAt === null ? null : Carbon::parse($sentAt, 'Europe/Vienna'),
        ]);
    }
}
