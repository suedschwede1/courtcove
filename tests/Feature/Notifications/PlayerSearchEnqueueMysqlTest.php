<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Events\PlayerSearchClosed;
use App\Events\PlayerSearchStarted;
use App\Models\NotificationLogEntry;
use App\Models\Option;
use App\Models\PlayerSearch;
use App\Notifications\Types\NotificationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsPlayerSearchFixtures;
use Tests\MysqlTestCase;

/**
 * Enqueueing a search on real MySQL: the unique dedupe key keeps one row per member and search however often
 * the events fire, and the follow-up is enqueued once. No wrapping transaction: the tables are emptied.
 */
#[Group('mysql')]
class PlayerSearchEnqueueMysqlTest extends MysqlTestCase
{
    use BuildsPlayerSearchFixtures;

    private static bool $migrated = false;

    private const TABLES = [
        'notification_log', 'notification_usage_monthly', 'notification_subscriptions', 'notification_consents',
        'player_exchange_signups', 'player_exchange_searches', 'bs_bookings_meta', 'bs_reservations',
        'bs_bookings', 'bs_users_meta', 'bs_users', 'bs_squares_meta', 'bs_squares', 'members',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        $this->clean();
        Mail::fake();
        $this->startClock();
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    private function clean(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        Option::where('key', 'like', 'whatsapp.%')->delete();
    }

    #[Test]
    public function events_fired_repeatedly_keep_one_row_per_member_and_search(): void
    {
        $this->setOption('whatsapp.followup_closed', '1');
        $anna = $this->optedInUser('+43 660 0000001');
        $this->optedInUser('+43 660 0000002');
        $owner = $this->searcher();
        $search = $this->searchOf($this->openSearch($owner));

        event(new PlayerSearchStarted($search->id));
        event(new PlayerSearchStarted($search->id));
        $this->assertSame(2, $this->openedRows($search->id)->count());

        NotificationLogEntry::query()->where('user_id', $anna->uid)->update(['status' => NotificationLogEntry::SENT, 'sent_at' => now()]);
        PlayerSearch::query()->whereKey($search->id)->update(['status' => PlayerSearch::ENDED]);
        event(new PlayerSearchClosed($search->id, 'ended'));
        event(new PlayerSearchClosed($search->id, 'ended'));

        $this->assertSame([$anna->uid], $this->closedRows()->pluck('user_id')->all());
        $this->assertSame(1, NotificationLogEntry::query()
            ->where('type', NotificationType::PlayerSearchOpened->value)
            ->where('status', NotificationLogEntry::SKIPPED)
            ->count());
    }
}
