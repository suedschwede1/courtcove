<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationLogEntry;
use App\Models\Option;
use App\Notifications\AdminTestNotification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Transport\FakeTransport;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\MysqlTestCase;

/** Claim of the outbox on real MySQL: a row locked by another worker is skipped, never sent twice. */
#[Group('mysql')]
class NotificationDispatcherMysqlTest extends MysqlTestCase
{
    use BuildsConsentFixtures;

    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        $this->clean();
        foreach ([
            'whatsapp.enabled' => '1',
            'whatsapp.driver' => 'fake',
            'whatsapp.budget_monthly' => '1.00',
            'whatsapp.price_estimate' => '0.05',
            'whatsapp.template.test.de' => 'HXtest-test',
        ] as $key => $value) {
            Option::create(['key' => $key, 'value' => $value, 'locale' => null]);
        }
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    private function clean(): void
    {
        DB::table('notification_log')->delete();
        DB::table('notification_usage_monthly')->delete();
        DB::table('notification_subscriptions')->delete();
        DB::table('notification_consents')->delete();
        Option::where('key', 'like', 'whatsapp.%')->delete();
    }

    #[Test]
    public function two_parallel_runs_send_a_row_once(): void
    {
        $user = $this->optedInUser();
        app(WhatsAppChannel::class)->send($user, new AdminTestNotification);
        $row = NotificationLogEntry::sole();

        $config = config('database.connections.mysql');
        $other = new \PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
            $config['username'],
            $config['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );

        // The other worker has claimed the row but not committed yet.
        $other->beginTransaction();
        $other->query('SELECT id FROM notification_log WHERE id = '.(int) $row->id.' FOR UPDATE')->fetchAll();

        $report = app(NotificationDispatcher::class)->run(null, 5);
        $this->assertSame(0, $report->sent);
        $this->assertSame([], app(FakeTransport::class)->sent());

        // The other worker finishes its claim; this worker must not take the row any more.
        $other->exec("UPDATE notification_log SET status = 'sending', updated_at = NOW() WHERE id = ".(int) $row->id);
        $other->commit();

        app(NotificationDispatcher::class)->run(null, 5);
        $this->assertSame([], app(FakeTransport::class)->sent());
        $this->assertSame(NotificationLogEntry::SENDING, $row->refresh()->status);

        // Without the competitor, a fresh row is claimed and sent exactly once.
        DB::table('notification_log')->delete();
        app(WhatsAppChannel::class)->send($user, new AdminTestNotification);
        app(NotificationDispatcher::class)->run(null, 5);
        app(NotificationDispatcher::class)->run(null, 5);
        $this->assertCount(1, app(FakeTransport::class)->sent());
    }

    #[Test]
    public function double_enqueue_yields_one_row(): void
    {
        $user = $this->optedInUser();
        $notification = new class extends Notification
        {
            public function toWhatsApp(object $notifiable): WhatsAppMessage
            {
                return new WhatsAppMessage(
                    type: NotificationTypes::PLAYER_SEARCH_OPENED,
                    variables: [],
                    buttonSuffix: null,
                    dedupeKey: 'player_search.opened:7:1',
                    subjectType: 'player_search',
                    subjectId: 7,
                    notBefore: Carbon::now(),
                );
            }
        };

        app(WhatsAppChannel::class)->send($user, $notification);
        app(WhatsAppChannel::class)->send($user, $notification);

        $this->assertSame(1, NotificationLogEntry::count());
    }
}
