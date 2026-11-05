<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function booking(User $user, string $status, int $dayOffset, array $square = []): Booking
    {
        $sq = Square::factory()->create($square);
        $booking = Booking::factory()->create(['uid' => $user->uid, 'sid' => $sq->sid, 'status' => $status]);
        Reservation::factory()->create([
            'bid' => $booking->bid,
            'date' => Carbon::today()->addDays($dayOffset)->toDateString(),
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
        ]);

        return $booking;
    }

    #[Test]
    public function bookings_page_has_header_filter_card_and_results_card(): void
    {
        app()->setLocale('de');
        $html = $this->actingAs(User::factory()->create())->get('/my-bookings')->assertOk()->getContent();

        foreach ([
            e(__('booking.account.eyebrow')), '<h1 class="font-display', 'class="ds-card',
            'for="booking-q"', 'id="booking-q"', 'id="booking-sid"', 'id="booking-status"',
            'id="booking-date-from"', 'id="booking-date-to"', 'name="searched" value="1"',
            e(__('booking.account.search_hint')),
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    #[Test]
    public function filter_values_survive_the_search(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get('/my-bookings?searched=1&q=Court&status=all&date_from=2026-01-01')->assertOk()->getContent();

        $this->assertStringContainsString('value="Court"', $html);
        $this->assertStringContainsString('<option value="all" selected', $html);
        $this->assertStringContainsString('value="2026-01-01"', $html);
    }

    #[Test]
    public function every_booking_cell_is_labelled_for_the_phone_cards(): void
    {
        app()->setLocale('de');
        $me = User::factory()->create();
        $this->booking($me, 'single', 3);

        $html = $this->actingAs($me)->get('/my-bookings?searched=1')->assertOk()->getContent();

        $this->assertStringContainsString('ds-table--stack', $html);
        preg_match_all('/<td\b[^>]*>/', $html, $m);
        $this->assertNotEmpty($m[0]);
        foreach ($m[0] as $td) {
            $this->assertTrue(
                str_contains($td, 'data-label="') || str_contains($td, 'ds-cell--title') || str_contains($td, 'ds-cell--actions'),
                $td,
            );
        }
        foreach (['date', 'time', 'status'] as $key) {
            $this->assertStringContainsString('data-label="'.e(__('booking.account.'.$key)).'"', $html);
        }
        $this->assertStringContainsString('<span class="sr-only">'.e(__('booking.account.actions')).'</span>', $html);
    }

    #[Test]
    public function status_badges_use_the_shared_tone_map(): void
    {
        app()->setLocale('de');
        $me = User::factory()->create();
        $this->booking($me, 'single', 3);
        $this->booking($me, 'subscription', 4);
        $this->booking($me, 'cancelled', 5);

        $html = $this->actingAs($me)->get('/my-bookings?searched=1&status=all')->assertOk()->getContent();

        $this->assertStringContainsString('class="ds-badge ds-badge--good">'.e(__('booking.account.status_active')), $html);
        $this->assertStringContainsString('class="ds-badge ds-badge--neutral">'.e(__('booking.account.status_series')), $html);
        $this->assertStringContainsString('class="ds-badge ds-badge--problem">'.e(__('booking.account.status_cancelled')), $html);
    }

    #[Test]
    public function cancellable_booking_has_a_danger_submit_with_confirm(): void
    {
        $me = User::factory()->create();
        $b = $this->booking($me, 'single', 5, ['range_cancel' => 3600]);

        $html = $this->actingAs($me)->get('/my-bookings?searched=1')->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('bookings.destroy', $b).'"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('onsubmit="return confirm(', $html);
        $this->assertStringContainsString('ds-btn--danger', $html);
    }

    #[Test]
    public function past_booking_cancel_is_disabled_with_reason(): void
    {
        app()->setLocale('de');
        $me = User::factory()->create();
        $b = $this->booking($me, 'single', -1, ['range_cancel' => 3600]);

        $html = $this->actingAs($me)->get('/my-bookings?searched=1')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button type="button"[^>]*class="ds-btn ds-btn--danger[^"]*"[^>]*>/', $html);
        preg_match('/<button type="button"[^>]*class="ds-btn ds-btn--danger[^"]*"[^>]*>/', $html, $m);
        $this->assertStringContainsString(' disabled', $m[0]);
        $this->assertStringContainsString('title="'.e(__('booking.account.cancel_disabled')).'"', $m[0]);
        $this->assertStringNotContainsString(route('bookings.destroy', $b), $html);
    }

    #[Test]
    public function empty_search_offers_the_calendar(): void
    {
        app()->setLocale('de');
        $html = $this->actingAs(User::factory()->create())->get('/my-bookings?searched=1')->assertOk()->getContent();

        $this->assertStringContainsString(e(__('booking.messages.no_active_bookings')), $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('calendar.index'), '/').'"[^>]*ds-btn--secondary/', $html);
    }

    #[Test]
    public function account_fields_are_labelled(): void
    {
        app()->setLocale('de');
        $html = $this->actingAs(User::factory()->create())->get('/my-account')->assertOk()->getContent();

        foreach (['alias', 'firstname', 'lastname', 'email', 'phone', 'street', 'zip', 'city', 'gender', 'current_password', 'password', 'password_confirmation'] as $id) {
            $this->assertStringContainsString('for="'.$id.'"', $html);
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
        $this->assertStringContainsString(e(__('booking.account.eyebrow')), $html);
        $this->assertSame(2, substr_count($html, 'ds-btn--dark'));
    }

    #[Test]
    public function profile_validation_error_is_shown_at_the_field(): void
    {
        $html = $this->actingAs(User::factory()->create())->from(route('account.edit'))->followingRedirects()
            ->put(route('account.update'), ['alias' => 'A', 'email' => 'not-an-email'])->getContent();

        $this->assertStringContainsString('id="email-error"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
    }

    #[Test]
    public function password_change_never_echoes_passwords(): void
    {
        $html = $this->actingAs(User::factory()->create())->from(route('account.edit'))->followingRedirects()
            ->put(route('account.password'), [
                'current_password' => 'Wrong-Old-111',
                'password' => 'New-Pass-222',
                'password_confirmation' => 'New-Pass-222',
            ])->getContent();

        $this->assertStringNotContainsString('Wrong-Old-111', $html);
        $this->assertStringNotContainsString('New-Pass-222', $html);
        $this->assertStringContainsString('id="current_password-error"', $html);
    }

    #[Test]
    public function gender_selection_is_kept(): void
    {
        $user = User::factory()->create();
        $user->setMeta('gender', 'female');

        $html = $this->actingAs($user)->get('/my-account')->assertOk()->getContent();

        $this->assertStringContainsString('<option value="female" selected', $html);
    }
}
