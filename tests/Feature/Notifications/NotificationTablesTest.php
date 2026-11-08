<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Services\DatabaseSchemaChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationTablesTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = ['notification_consents', 'notification_subscriptions', 'notification_log', 'notification_usage_monthly'];

    private function migration(): object
    {
        return require database_path('migrations/2026_11_08_100001_create_notification_tables.php');
    }

    #[Test]
    public function migration_creates_four_tables_and_is_repeatable(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->migration()->up();
        $this->migration()->up();

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }

    #[Test]
    public function down_drops_only_notification_tables(): void
    {
        $this->migration()->down();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertTrue(Schema::hasTable('bs_users'));
        $this->assertTrue(Schema::hasTable('player_exchange_searches'));
    }

    #[Test]
    public function schema_checker_manifest_knows_the_tables(): void
    {
        $checker = app(DatabaseSchemaChecker::class);

        foreach (self::TABLES as $table) {
            $this->assertTrue($checker->knowsTable($table), $table);
        }
        $this->assertSame([], array_values(array_intersect(self::TABLES, $checker->untrackedTables())));

        foreach ($checker->checkTables() as $row) {
            if (in_array($row['table'], self::TABLES, true)) {
                $this->assertSame([], $row['missing_columns'], $row['table']);
            }
        }

        $drift = collect($checker->driftReport())->whereIn('table', self::TABLES)->values()->all();
        $this->assertSame([], $drift);
    }

    #[Test]
    public function notification_log_table_has_no_body_or_number_columns(): void
    {
        $columns = Schema::getColumnListing('notification_log');

        $this->assertContains('dedupe_key', $columns);
        foreach ($columns as $column) {
            $this->assertNotContains($column, ['body', 'to', 'message', 'text', 'name']);
            $this->assertStringNotContainsString('phone', str_replace('phone_last2', '', $column));
        }
    }

    #[Test]
    public function revoking_a_consent_clears_the_active_marker(): void
    {
        $consent = NotificationConsent::query()->create([
            'user_id' => 5, 'channel' => 'whatsapp', 'purpose' => NotificationConsent::PURPOSE_PLAYER_SEARCH,
            'phone_hash' => str_repeat('a', 64), 'phone_last2' => '00', 'locale' => 'de',
            'consent_text_version' => 'v1', 'consented_at' => now(), 'active_marker' => 1,
        ]);
        $this->assertSame(1, NotificationConsent::active()->count());

        $consent->revoke('stop');

        $fresh = $consent->fresh();
        $this->assertNotNull($fresh?->revoked_at);
        $this->assertSame('stop', $fresh->revoked_via);
        $this->assertNull($fresh->active_marker);
        $this->assertSame(0, NotificationConsent::active()->count());
    }
}
