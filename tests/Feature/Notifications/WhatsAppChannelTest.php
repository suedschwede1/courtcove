<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationLogEntry;
use App\Notifications\AdminTestNotification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\Transport\FakeTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class WhatsAppChannelTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

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

    private function notification(string $dedupe, array $variables = []): Notification
    {
        return new class($dedupe, $variables) extends Notification
        {
            public function __construct(private string $dedupe, private array $variables) {}

            public function via(object $notifiable): array
            {
                return [WhatsAppChannel::class];
            }

            public function toWhatsApp(object $notifiable): WhatsAppMessage
            {
                return new WhatsAppMessage(
                    type: NotificationTypes::PLAYER_SEARCH_OPENED,
                    variables: $this->variables,
                    buttonSuffix: 'search/7',
                    dedupeKey: $this->dedupe,
                    subjectType: 'player_search',
                    subjectId: 7,
                    notBefore: Carbon::now()->addMinutes(2),
                    subjectPayload: ['search_id' => 7, 'sender_id' => 3],
                );
            }
        };
    }

    #[Test]
    public function channel_writes_one_pending_row_without_calling_the_transport(): void
    {
        $user = $this->optedInUser();

        app(WhatsAppChannel::class)->send($user, $this->notification('player_search.opened:7:'.$user->uid));

        $row = NotificationLogEntry::sole();
        $this->assertSame(NotificationLogEntry::PENDING, $row->status);
        $this->assertSame('player_search.opened', $row->type);
        $this->assertSame('whatsapp', $row->channel);
        $this->assertSame((int) $user->uid, $row->user_id);
        $this->assertSame('player_search', $row->subject_type);
        $this->assertSame(7, $row->subject_id);
        $this->assertSame(app(NotificationRegistry::class)->type(NotificationTypes::PLAYER_SEARCH_OPENED)?->priority, $row->priority);
        $this->assertSame('00', $row->phone_last2);
        $this->assertSame(0, $row->attempts);
        $this->assertSame('2026-11-10 12:02:00', $row->not_before->format('Y-m-d H:i:s'));
        $this->assertSame(['search_id' => 7, 'sender_id' => 3], $row->subject_payload);
        $this->assertSame([], app(FakeTransport::class)->sent());
    }

    #[Test]
    public function same_dedupe_key_twice_creates_one_row(): void
    {
        $user = $this->optedInUser();
        $channel = app(WhatsAppChannel::class);

        $channel->send($user, $this->notification('player_search.opened:7:'.$user->uid));
        $channel->send($user, $this->notification('player_search.opened:7:'.$user->uid));

        $this->assertSame(1, NotificationLogEntry::count());
    }

    #[Test]
    public function row_contains_no_number_and_no_text(): void
    {
        $user = $this->optedInUser('+43 660 1234567');

        app(WhatsAppChannel::class)->send($user, $this->notification('k1', ['1' => 'Erika Musterfrau', '2' => 'Sa 14.11. 10:00']));

        $dump = json_encode(DB::table('notification_log')->get()->all(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('1234567', (string) $dump);
        $this->assertStringNotContainsString('4366012', (string) $dump);
        $this->assertStringNotContainsString('Erika', (string) $dump);
        $this->assertStringNotContainsString('14.11.', (string) $dump);
        $this->assertSame('67', NotificationLogEntry::sole()->phone_last2);
    }

    #[Test]
    public function channel_never_throws(): void
    {
        $user = $this->optedInUser();
        $broken = new class extends Notification
        {
            public function toWhatsApp(object $notifiable): WhatsAppMessage
            {
                throw new \RuntimeException('boom');
            }
        };

        app(WhatsAppChannel::class)->send($user, $broken);

        $this->assertSame(0, NotificationLogEntry::count());
    }

    #[Test]
    public function admin_test_notification_is_due_at_once_with_top_priority(): void
    {
        $user = $this->optedInUser();

        app(WhatsAppChannel::class)->send($user, new AdminTestNotification);

        $row = NotificationLogEntry::sole();
        $this->assertSame(NotificationTypes::ADMIN_TEST, $row->type);
        $this->assertSame('2026-11-10 12:00:00', $row->not_before->format('Y-m-d H:i:s'));
        $this->assertLessThan(app(NotificationRegistry::class)->type(NotificationTypes::PLAYER_SEARCH_OPENED)?->priority, $row->priority);
        $this->assertLessThan(app(NotificationRegistry::class)->type(NotificationTypes::PLAYER_SEARCH_CLOSED)?->priority, app(NotificationRegistry::class)->type(NotificationTypes::PLAYER_SEARCH_OPENED)?->priority);
    }
}
