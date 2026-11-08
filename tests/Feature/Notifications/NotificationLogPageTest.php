<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationLogEntry;
use App\Models\User;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Types\NotificationTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Admin page "WhatsApp send log" (admin/whatsapp/protokoll): rows without numbers or texts. */
class NotificationLogPageTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function row(array $attributes = []): NotificationLogEntry
    {
        $type = $attributes['type'] ?? NotificationTypes::PLAYER_SEARCH_OPENED;
        unset($attributes['type']);
        $created = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $row = NotificationLogEntry::create(array_merge([
            'type' => $type,
            'channel' => 'whatsapp',
            'user_id' => null,
            'subject_type' => 'player_search',
            'subject_id' => 7,
            'dedupe_key' => 'log-test:'.(++$this->seq),
            'status' => NotificationLogEntry::SENT,
            'priority' => (int) app(NotificationRegistry::class)->type($type)?->priority,
            'subject_payload' => ['search_id' => 7],
            'attempts' => 1,
            'not_before' => Carbon::now(),
        ], $attributes));

        if ($created !== null) {
            $row->forceFill(['created_at' => $created])->save();
        }

        return $row;
    }

    private function open(string $query = ''): TestResponse
    {
        return $this->actingAsWithoutAdminMode($this->admin())->get('/admin/whatsapp/protokoll'.$query);
    }

    #[Test]
    public function list_needs_admin_config(): void
    {
        $this->actingAsWithoutAdminMode(User::factory()->create(['status' => 'enabled']))
            ->get('/admin/whatsapp/protokoll')->assertForbidden();
        $this->open()->assertOk();
    }

    #[Test]
    public function list_shows_status_reason_and_cost_but_no_number_or_text(): void
    {
        $user = User::factory()->create(['alias' => 'Logalias']);
        $this->row(['user_id' => $user->uid, 'phone_last2' => '00', 'status' => NotificationLogEntry::DELIVERED, 'cost_estimate_micros' => 50_000, 'provider_sid' => 'SMsid-test-1']);
        $this->row(['status' => NotificationLogEntry::SKIPPED, 'skip_reason' => NotificationLogEntry::TEMPLATE_MISSING]);
        $this->row(['status' => NotificationLogEntry::FAILED, 'error_code' => 'E-test-77']);

        $html = $this->open()->assertOk()->getContent();

        $this->assertStringContainsString('Logalias', $html);
        $this->assertStringContainsString(route('admin.users.edit', $user), $html);
        $this->assertStringContainsString('… 00', $html);
        $this->assertStringContainsString(__('booking.admin.whatsapp.log.status.delivered'), $html);
        $this->assertStringContainsString(__('booking.admin.whatsapp.skip_reasons.template_missing'), $html);
        $this->assertStringContainsString('E-test-77', $html);
        $this->assertStringContainsString('≈ 0,05', $html);
        $this->assertStringNotContainsString('SMsid-test-1', $html);
        $this->assertDoesNotMatchRegularExpression('/\+?\d{9,}/', strip_tags($html));
    }

    #[Test]
    public function unknown_values_are_shown_raw_not_as_language_keys(): void
    {
        $this->row(['status' => 'queued', 'skip_reason' => 'odd_reason_test']);

        $html = $this->open()->assertOk()->getContent();

        $this->assertStringContainsString('odd_reason_test', $html);
        $this->assertStringContainsString('queued', $html);
        $this->assertStringNotContainsString('booking.admin.whatsapp.', $html);
    }

    #[Test]
    public function filters_by_status_type_and_month(): void
    {
        $this->row(['status' => NotificationLogEntry::FAILED, 'error_code' => 'E-failed-1']);
        $this->row(['status' => NotificationLogEntry::DELIVERED, 'error_code' => 'E-delivered-1']);
        $this->row(['type' => NotificationTypes::PLAYER_SEARCH_CLOSED, 'error_code' => 'E-closed-1']);
        $this->row(['error_code' => 'E-old-1', 'created_at' => Carbon::now()->subMonths(2)->startOfMonth()->addDay()]);

        $byStatus = $this->open('?status=failed')->assertOk()->getContent();
        $this->assertStringContainsString('E-failed-1', $byStatus);
        $this->assertStringNotContainsString('E-delivered-1', $byStatus);

        $byType = $this->open('?type=player_search.closed')->assertOk()->getContent();
        $this->assertStringContainsString('E-closed-1', $byType);
        $this->assertStringNotContainsString('E-failed-1', $byType);

        $month = Carbon::now()->subMonths(2)->format('Y-m');
        $byMonth = $this->open('?month='.$month)->assertOk()->getContent();
        $this->assertStringContainsString('E-old-1', $byMonth);
        $this->assertStringNotContainsString('E-failed-1', $byMonth);

        $this->open('?status=nonsense&type=x&month=zz')->assertOk();
    }

    #[Test]
    public function paginates(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->row(['error_code' => sprintf('E-page-%02d', $i), 'created_at' => Carbon::now()->subMinutes(100 - $i)]);
        }

        $first = $this->open()->assertOk()->getContent();
        $this->assertStringContainsString('E-page-30', $first);
        $this->assertStringNotContainsString('E-page-01', $first);
        $this->assertStringContainsString('page=2', $first);

        $this->assertStringContainsString('E-page-01', $this->open('?page=2')->assertOk()->getContent());
    }

    #[Test]
    public function deleted_user_row_renders_without_user(): void
    {
        $this->row(['user_id' => null, 'phone_last2' => null, 'error_code' => 'E-nouser-1']);

        $html = $this->open()->assertOk()->getContent();

        $this->assertStringContainsString('E-nouser-1', $html);
        $this->assertStringNotContainsString('/admin/users/', $html);
    }

    #[Test]
    public function admin_page_links_to_the_log(): void
    {
        $html = $this->actingAsWithoutAdminMode($this->admin())->get('/admin/whatsapp')->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.whatsapp.log'), $html);
    }
}
