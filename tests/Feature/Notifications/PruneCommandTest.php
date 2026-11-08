<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSubscription;
use App\Models\NotificationUsageMonthly;
use App\Models\User;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PruneCommandTest extends TestCase
{
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

    private function logRow(string $key, string $createdAt, ?int $userId = null): NotificationLogEntry
    {
        $row = NotificationLogEntry::create([
            'type' => 'test', 'channel' => 'whatsapp', 'user_id' => $userId, 'subject_type' => 'player_search', 'subject_id' => 1, 'dedupe_key' => $key,
            'status' => NotificationLogEntry::SENT, 'priority' => 5, 'attempts' => 1, 'not_before' => Carbon::now(),
        ]);
        DB::table('notification_log')->where('id', $row->id)->update(['created_at' => $createdAt]);

        return $row;
    }

    private function consent(int $userId, string $marker, ?string $revokedAt): NotificationConsent
    {
        return NotificationConsent::create([
            'user_id' => $userId, 'channel' => 'whatsapp', 'purpose' => 'player_search'.$marker, 'phone_hash' => str_repeat('a', 64),
            'phone_last2' => '00', 'locale' => 'de', 'consent_text_version' => '1', 'consented_at' => '2020-01-01 00:00:00',
            'revoked_at' => $revokedAt, 'revoked_via' => $revokedAt === null ? null : 'user', 'active_marker' => $revokedAt === null ? 1 : null,
        ]);
    }

    #[Test]
    public function prune_removes_only_old_rows(): void
    {
        $user = User::factory()->create(['status' => 'enabled']);
        $this->logRow('old', '2025-11-10 11:59:59', $user->uid);
        $this->logRow('edge-kept', '2025-11-10 12:00:01', $user->uid);
        $this->logRow('recent', '2026-11-01 00:00:00', $user->uid);

        $this->consent($user->uid, 'a', '2023-11-10 11:59:59');
        $this->consent($user->uid, 'b', '2023-11-10 12:00:01');
        $this->consent($user->uid, 'c', null);
        NotificationUsageMonthly::query()->create(['month' => '2020-01', 'channel' => 'whatsapp', 'messages_sent' => 1, 'messages_reserved' => 0, 'estimated_micros' => 1, 'reported_micros' => 0]);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertSame(['edge-kept', 'recent'], NotificationLogEntry::query()->orderBy('id')->pluck('dedupe_key')->all());
        $this->assertSame(['player_searchb', 'player_searchc'], NotificationConsent::query()->orderBy('id')->pluck('purpose')->all());
        $this->assertSame(1, NotificationUsageMonthly::query()->count());
    }

    #[Test]
    public function prune_drops_subscriptions_and_log_user_ids_of_deleted_or_anonymised_users(): void
    {
        $kept = User::factory()->create(['status' => 'enabled']);
        $deleted = User::factory()->create(['status' => 'deleted']);
        $anonymised = User::factory()->create(['status' => 'enabled']);
        Member::factory()->create(['user_id' => $anonymised->uid, 'anonymized_at' => Carbon::now()]);
        $gone = 987654;

        foreach ([$kept, $deleted, $anonymised] as $user) {
            NotificationSubscription::create(['user_id' => $user->uid, 'type' => 'player_search_opened', 'channel' => 'whatsapp', 'filters' => null]);
            $this->logRow('log-'.$user->uid, '2026-11-01 00:00:00', $user->uid);
        }
        NotificationSubscription::create(['user_id' => $gone, 'type' => 'player_search_opened', 'channel' => 'whatsapp', 'filters' => null]);
        $this->logRow('log-gone', '2026-11-01 00:00:00', $gone);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertSame([$kept->uid], NotificationSubscription::query()->pluck('user_id')->all());
        $ids = NotificationLogEntry::query()->pluck('user_id', 'dedupe_key')->all();
        $this->assertSame($kept->uid, $ids['log-'.$kept->uid]);
        $this->assertNull($ids['log-'.$deleted->uid]);
        $this->assertNull($ids['log-'.$anonymised->uid]);
        $this->assertNull($ids['log-gone']);
    }
}
