<?php

declare(strict_types=1);

namespace Tests\Feature\PlayerExchange;

use App\Models\Booking;
use App\Models\PlayerSearch;
use App\Models\PlayerSignup;
use App\Models\Square;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Joining the player exchange without the detour via "My account": in the join dialog ("join the
 * exchange and play") and in the booking dialog ("seek players"). The consent is recorded with
 * time, source and the version of the notice.
 */
class OneClickOptInTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Square $court;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));
        $this->court = Square::factory()->create();
    }

    private function exchange(): PlayerExchangeService
    {
        return app(PlayerExchangeService::class);
    }

    private function searchingBooking(): Booking
    {
        $owner = User::factory()->create(['alias' => 'Olga Owner']);
        $start = Carbon::parse('2026-11-08 10:00:00');

        return app(BookingService::class)->createSingle($owner, $this->court, 2, $start, $start->copy()->addHour(), [], 1);
    }

    /** @return array<string, mixed> */
    private function payload(array $override = []): array
    {
        return array_merge([
            'sid' => $this->court->sid,
            'date' => '2026-11-10',
            'time_start' => 36000,
            'time_end' => 39600,
            'quantity' => 2,
        ], $override);
    }

    private function ajaxPost(User $user, array $data)
    {
        return $this->actingAs($user)->post(route('bookings.store'), $data, ['X-Requested-With' => 'XMLHttpRequest']);
    }

    #[Test]
    public function opt_in_records_time_source_and_text_version_and_keeps_the_first_record(): void
    {
        $user = User::factory()->create();

        $this->exchange()->optIn($user, 'join');
        $record = $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY);

        $this->assertMatchesRegularExpression('/^2026-11-06T08:00:00[+-]\d\d:\d\d\|join\|v1$/', $record);

        $this->travelTo(Carbon::parse('2026-11-07 09:00:00'));
        $this->exchange()->optIn($user, 'account');

        $this->assertSame($record, $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY));
    }

    #[Test]
    public function opt_out_deletes_the_consent_record(): void
    {
        $user = User::factory()->create();
        $this->exchange()->optIn($user);

        $this->exchange()->optOut($user);

        $this->assertNull($user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY));
        $this->assertFalse($this->exchange()->isOptedIn($user));
    }

    #[Test]
    public function saving_the_account_form_again_does_not_overwrite_the_consent(): void
    {
        $user = User::factory()->create();
        $this->exchange()->optIn($user, 'join');
        $record = $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY);

        $this->travelTo(Carbon::parse('2026-11-07 09:00:00'));
        $this->exchange()->optIn($user->fresh());

        $this->assertSame($record, $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY));
    }

    #[Test]
    public function join_with_opt_in_flag_opts_the_member_in_and_signs_him_up(): void
    {
        $booking = $this->searchingBooking();
        $user = User::factory()->create(['alias' => 'Max Muster']);

        $this->actingAs($user)
            ->post(route('player-exchange.join', $booking), ['opt_in' => '1'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue($this->exchange()->isOptedIn($user));
        $this->assertStringEndsWith('|join|v1', (string) $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY));
        $this->assertSame(1, PlayerSignup::query()->where('uid', $user->uid)->count());
    }

    #[Test]
    public function join_without_the_flag_still_requires_the_opt_in(): void
    {
        $booking = $this->searchingBooking();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('player-exchange.join', $booking), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJson(['error' => __('booking.messages.player_exchange.not_opted_in')]);

        $this->assertFalse($this->exchange()->isOptedIn($user));
        $this->assertSame(0, PlayerSignup::query()->count());
    }

    #[Test]
    public function booking_with_a_search_and_the_flag_opts_the_booker_in(): void
    {
        $user = User::factory()->create();

        $this->ajaxPost($user, $this->payload(['search_players' => 1, 'player_exchange_opt_in' => '1']))
            ->assertOk()
            ->assertJson(['search' => true, 'opted_in' => true]);

        $this->assertTrue($this->exchange()->isOptedIn($user));
        $this->assertStringEndsWith('|booking|v1', (string) $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY));
        $this->assertSame(1, PlayerSearch::query()->count());
    }

    #[Test]
    public function booking_without_a_search_never_opts_in_even_with_the_flag(): void
    {
        $user = User::factory()->create();

        $this->ajaxPost($user, $this->payload(['mitspieler' => 'Partner', 'player_exchange_opt_in' => '1']))
            ->assertOk()
            ->assertJson(['search' => false, 'opted_in' => false]);

        $this->assertFalse($this->exchange()->isOptedIn($user));
    }

    #[Test]
    public function booking_with_a_search_without_the_flag_does_not_opt_in(): void
    {
        $user = User::factory()->create();

        $this->ajaxPost($user, $this->payload(['search_players' => 1]))->assertOk()->assertJson(['opted_in' => false]);

        $this->assertFalse($this->exchange()->isOptedIn($user));
    }

    #[Test]
    public function a_refused_booking_does_not_opt_in(): void
    {
        $user = User::factory()->create();

        $this->ajaxPost($user, $this->payload(['date' => '2026-11-01', 'search_players' => 1, 'player_exchange_opt_in' => '1']))
            ->assertStatus(422);

        $this->assertFalse($this->exchange()->isOptedIn($user));
    }

    #[Test]
    public function an_already_opted_in_booker_gets_no_notice_and_no_new_record(): void
    {
        $user = User::factory()->create();
        $this->exchange()->optIn($user, 'account');
        $record = $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY);

        $html = $this->actingAs($user)->get(route('calendar.index', ['date' => '2026-11-10']))->assertOk()->getContent();
        $this->assertStringContainsString('optedIn: true', $html);

        $this->ajaxPost($user, $this->payload(['search_players' => 1, 'player_exchange_opt_in' => '1']))
            ->assertOk()->assertJson(['opted_in' => false]);
        $this->assertSame($record, $user->fresh()->getMeta(PlayerExchangeService::CONSENT_META_KEY));
    }

    #[Test]
    public function the_dialogs_carry_the_consent_notices(): void
    {
        $user = User::factory()->create();

        $html = html_entity_decode($this->actingAs($user)->get(route('calendar.index', ['date' => '2026-11-10']))->assertOk()->getContent(), ENT_QUOTES);

        $this->assertStringContainsString(__('booking.modal.search_opt_in_hint'), $html);
        $this->assertStringContainsString(__('booking.calendar.join.opt_in_hint'), $html);
        $this->assertStringContainsString(__('booking.calendar.join.join_and_enter'), $html);
        $this->assertStringContainsString('name="player_exchange_opt_in"', $html);
        $this->assertStringContainsString('opted_in', $html);
        $this->assertStringNotContainsString('open-join-info', $html);
    }

    #[Test]
    public function only_the_join_dialog_sends_the_opt_in_and_its_script_survives_the_attribute(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('calendar.index', ['date' => '2026-11-10']))->assertOk()->getContent();

        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xp = new DOMXPath($doc);

        // A double quote inside the x-data script would cut the attribute: the script would be gone.
        // The join dialog is the only one that knows whether the member has joined.
        $join = null;
        foreach ($xp->query('//*[@x-data]') as $node) {
            if (str_contains($node->getAttribute('x-data'), 'detail.joined === true')) {
                $join = $node;
            }
        }
        $this->assertNotNull($join);
        $this->assertStringContainsString("opt_in: '1'", $join->getAttribute('x-data'));
        $this->assertStringContainsString('finally', $join->getAttribute('x-data'));

        foreach ($xp->query('//*[@x-data]') as $node) {
            if ($node !== $join) {
                $this->assertStringNotContainsString('opt_in', $node->getAttribute('x-data'));
            }
        }
    }
}
