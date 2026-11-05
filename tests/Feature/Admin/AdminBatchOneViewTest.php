<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\LoginHistory;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the wiring of the batch 1 admin pages (login history, API tokens, test mail, booking detail).
 */
final class AdminBatchOneViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function login_history_status_badges_use_the_status_tones(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $member = User::factory()->create();
        foreach (['success', 'failed', 'throttled'] as $status) {
            LoginHistory::record((int) $member->uid, '203.0.113.5', 'AT', $status);
        }

        $html = $this->actingAs($admin)->get(route('admin.login-history.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/ds-badge--good[^>]*>\s*'.preg_quote(__('booking.admin.users.login_history_status_success'), '/').'</', $html);
        $this->assertMatchesRegularExpression('/ds-badge--problem[^>]*>\s*'.preg_quote(__('booking.admin.users.login_history_status_failed'), '/').'</', $html);
        $this->assertMatchesRegularExpression('/ds-badge--attention[^>]*>\s*'.preg_quote(__('booking.admin.users.login_history_status_throttled'), '/').'</', $html);
        $this->assertStringContainsString('data-label="'.__('booking.admin.users.login_history_ip').'"', $html);
    }

    #[Test]
    public function login_history_filter_keeps_its_field_names(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $html = $this->actingAs($admin)->get(route('admin.login-history.index', ['country' => 'AT']))->assertOk()->getContent();

        foreach (['user', 'status', 'country', 'date_from', 'date_to'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html);
        }
        $this->assertStringContainsString('maxlength="2"', $html);
    }

    #[Test]
    public function api_token_revoke_keeps_its_confirm_and_method(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $member = User::factory()->create();
        $member->createToken('Example phone');

        $html = $this->actingAs($admin)->get(route('admin.api-tokens.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('onsubmit="return confirm(', $html);
        $this->assertStringContainsString('name="device_name"', $html);
        $this->assertStringContainsString('name="uid"', $html);
        $this->assertStringContainsString('Example phone', $html);
    }

    #[Test]
    public function booking_detail_keeps_cancel_and_delete_forms_with_confirms(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create(['name' => 'Court A']);
        $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'single']);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => '2026-07-13', 'time_start' => '10:00:00', 'time_end' => '11:00:00']);

        $html = $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('admin.bookings.cancel', $booking).'"', $html);
        $this->assertStringContainsString('action="'.route('admin.bookings.destroy', $booking).'"', $html);
        $this->assertSame(2, substr_count($html, 'onsubmit="return confirm('));
        $this->assertStringContainsString('ds-table--kv', $html);
        $this->assertStringContainsString('<th scope="row">', $html);
        $this->assertStringContainsString('ds-btn--danger', $html);
    }

    #[Test]
    public function cancelled_booking_detail_offers_no_cancel_form(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $square = Square::factory()->create(['name' => 'Court A']);
        $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'cancelled']);

        $html = $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk()->getContent();

        $this->assertStringNotContainsString('action="'.route('admin.bookings.cancel', $booking).'"', $html);
        $this->assertStringContainsString('action="'.route('admin.bookings.destroy', $booking).'"', $html);
    }

    #[Test]
    public function test_mail_form_keeps_its_recipient_field(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        $html = $this->actingAs($admin)->get(route('admin.testmail.index'))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('admin.testmail.send').'"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="email"[^>]*name="email"|<input[^>]*name="email"[^>]*type="email"/', $html);
        $this->assertStringContainsString('required', $html);
    }
}
