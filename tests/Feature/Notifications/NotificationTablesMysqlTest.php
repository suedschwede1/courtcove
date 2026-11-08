<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/** Unique keys of the notification tables on real MySQL (NULL handling of the active marker). */
#[Group('mysql')]
class NotificationTablesMysqlTest extends MysqlTestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        DB::table('notification_log')->delete();
        DB::table('notification_consents')->delete();
    }

    /** @param array<string, mixed> $overrides */
    private function consent(array $overrides = []): void
    {
        DB::table('notification_consents')->insert(array_merge([
            'user_id' => 7, 'channel' => 'whatsapp', 'purpose' => 'player_search',
            'phone_hash' => str_repeat('b', 64), 'phone_last2' => '00', 'locale' => 'de',
            'consent_text_version' => 'v1', 'consented_at' => now(), 'active_marker' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function logRow(array $overrides = []): void
    {
        DB::table('notification_log')->insert(array_merge([
            'type' => 'player_search', 'channel' => 'whatsapp', 'subject_type' => 'search', 'subject_id' => 1,
            'dedupe_key' => 'k1', 'status' => 'pending', 'priority' => 1, 'not_before' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    #[Test]
    public function second_active_consent_for_same_user_channel_purpose_is_rejected(): void
    {
        $this->consent();

        $this->expectException(QueryException::class);
        $this->consent();
    }

    #[Test]
    public function revoked_consent_allows_a_new_active_one(): void
    {
        $this->consent(['active_marker' => null, 'revoked_at' => now(), 'revoked_via' => 'account']);
        $this->consent(['active_marker' => null, 'revoked_at' => now(), 'revoked_via' => 'stop']);
        $this->consent();

        $this->assertSame(3, DB::table('notification_consents')->count());
    }

    #[Test]
    public function duplicate_dedupe_key_is_rejected(): void
    {
        $this->logRow();

        $this->expectException(QueryException::class);
        $this->logRow(['subject_id' => 2]);
    }

    #[Test]
    public function duplicate_provider_sid_is_rejected(): void
    {
        $this->logRow(['dedupe_key' => 'a', 'provider_sid' => 'SMtest1']);
        $this->logRow(['dedupe_key' => 'b', 'provider_sid' => null]);
        $this->logRow(['dedupe_key' => 'c', 'provider_sid' => null]);

        $this->expectException(QueryException::class);
        $this->logRow(['dedupe_key' => 'd', 'provider_sid' => 'SMtest1']);
    }
}
