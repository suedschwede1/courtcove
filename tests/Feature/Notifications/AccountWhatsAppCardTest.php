<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationSubscription;
use App\Models\Option;
use App\Models\User;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class AccountWhatsAppCardTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    private const TYPE = 'player_search.opened';

    /** @return array<string, array{0: string}> */
    public static function lockedReasons(): array
    {
        $reasons = ['account_inactive', 'no_member', 'member_inactive', 'no_phone', 'phone_invalid', 'phone_not_mobile', 'exchange_off'];

        return array_combine($reasons, array_map(fn (string $r): array => [$r], $reasons));
    }

    private function breakGate(User $user, string $reason): void
    {
        match ($reason) {
            'account_inactive' => $user->forceFill(['status' => 'blocked'])->save(),
            'no_member' => Member::query()->where('user_id', $user->uid)->delete(),
            'member_inactive' => Member::query()->where('user_id', $user->uid)->update(['status' => 'passiv']),
            'no_phone' => $user->setMeta('phone', null),
            'phone_invalid' => $user->setMeta('phone', '12'),
            'phone_not_mobile' => $user->setMeta('phone', '+43 1 5880000'),
            'exchange_off' => $user->setMeta('player-exchange', null),
        };
    }

    private function card(User $user, string $locale = 'de'): string
    {
        $user->setMeta('locale', $locale);
        $html = (string) $this->actingAs($user->fresh())->get(route('account.edit'))->assertOk()->getContent();
        preg_match('/<section[^>]*data-whatsapp-card.*?<\/section>/s', $html, $m);

        return $m[0] ?? '';
    }

    #[Test]
    public function card_is_hidden_when_switch_off(): void
    {
        $user = User::factory()->create();
        $user->setMeta('phone', '+43 660 0000000');

        $this->actingAs($user)->get(route('account.edit'))->assertOk()->assertDontSee('data-whatsapp-card', false);
    }

    #[Test]
    #[DataProvider('lockedReasons')]
    public function locked_states_show_reason_and_disable_the_switch(string $reason): void
    {
        $user = $this->eligibleUser();
        $this->breakGate($user, $reason);

        $card = $this->card($user);

        $this->assertStringContainsString('data-whatsapp-card="locked"', $card);
        $this->assertStringContainsString('data-whatsapp-reason="'.$reason.'"', $card);
        $this->assertStringContainsString(e(trans('booking.notifications.refused.'.$reason, [], 'de')), $card);
        $this->assertStringContainsString('disabled', $card);
        $this->assertStringNotContainsString('name="consent"', $card);
        $this->assertStringNotContainsString(route('account.whatsapp.enable'), $card);
        if (in_array($reason, ['no_phone', 'phone_invalid', 'phone_not_mobile'], true)) {
            $this->assertStringContainsString(route('account.edit').'#phone', $card);
        }
    }

    #[Test]
    public function ready_state_shows_masked_number_hints_and_unticked_checkbox(): void
    {
        $user = $this->eligibleUser('+43 660 0000042');

        $card = $this->card($user);

        $this->assertStringContainsString('data-whatsapp-card="ready"', $card);
        $this->assertStringContainsString('+43 … 42', $card);
        $this->assertStringNotContainsString('0000042', $card);
        $this->assertStringContainsString('name="consent"', $card);
        $this->assertDoesNotMatchRegularExpression('/name="consent"[^>]*checked/', $card);
        $this->assertStringContainsString(e(trans('booking.notifications.consent_text_v1', [], 'de')), $card);
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.own_number_hint', [], 'de')), $card);
        $this->assertStringContainsString(route('account.whatsapp.enable'), $card);
    }

    #[Test]
    public function enable_requires_the_ticked_consent_checkbox(): void
    {
        $user = $this->eligibleUser();

        $this->actingAs($user)->post(route('account.whatsapp.enable'))->assertSessionHasErrors('consent');
        $this->actingAs($user)->post(route('account.whatsapp.enable'), ['consent' => '0'])->assertSessionHasErrors('consent');

        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame(0, NotificationSubscription::query()->count());
    }

    #[Test]
    public function enable_creates_consent_with_text_version_and_locale(): void
    {
        $user = $this->eligibleUser();
        $user->setMeta('locale', 'en');

        $this->actingAs($user->fresh())->post(route('account.whatsapp.enable'), ['consent' => '1'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('success');

        $consent = $this->consents()->activeConsent($user);
        $this->assertNotNull($consent);
        $this->assertSame('en', $consent->locale);
        $this->assertSame('wa-2026-11-v1', $consent->consent_text_version);
        $this->assertSame(1, NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::TYPE)->count());
    }

    #[Test]
    #[DataProvider('lockedReasons')]
    public function enable_is_refused_with_a_member_text_for_every_gate_reason(string $reason): void
    {
        $user = $this->eligibleUser();
        $this->breakGate($user, $reason);

        $this->actingAs($user->fresh())->post(route('account.whatsapp.enable'), ['consent' => '1'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('error', trans('booking.notifications.refused.'.$reason, [], 'de'));

        $this->assertSame(0, $this->activeConsentCount($user));
    }

    #[Test]
    public function enable_is_refused_when_the_main_switch_is_off(): void
    {
        $user = $this->eligibleUser();
        Option::query()->where('key', 'whatsapp.enabled')->update(['value' => '0']);

        $this->actingAs($user)->post(route('account.whatsapp.enable'), ['consent' => '1'])
            ->assertSessionHas('error', trans('booking.notifications.refused.disabled', [], 'de'));

        $this->assertSame(0, $this->activeConsentCount($user));
    }

    #[Test]
    public function every_gate_reason_and_the_conflict_have_a_member_text_in_both_languages(): void
    {
        foreach (['account_inactive', 'no_member', 'member_inactive', 'no_phone', 'phone_invalid', 'phone_not_mobile', 'exchange_off', 'disabled', 'conflict'] as $reason) {
            foreach (['de', 'en'] as $locale) {
                $key = 'booking.notifications.refused.'.$reason;
                $this->assertNotSame($key, trans($key, [], $locale), "$locale $reason");
            }
        }
    }

    #[Test]
    public function active_state_shows_badge_date_switch_filters_and_revoke(): void
    {
        Carbon::setTestNow('2026-11-08 10:00:00');
        $user = $this->optedInUser('+43 660 0000042');

        $card = $this->card($user);

        $this->assertStringContainsString('data-whatsapp-card="active"', $card);
        $this->assertStringContainsString('Aktiv', $card);
        $this->assertStringContainsString('08.11.2026', $card);
        $this->assertStringContainsString('+43 … 42', $card);
        $this->assertStringContainsString('name="search"', $card);
        $this->assertStringContainsString('name="weekdays[]"', $card);
        $this->assertStringContainsString('name="time_from"', $card);
        $this->assertStringContainsString('name="quantities[]"', $card);
        $this->assertStringContainsString(route('account.whatsapp.revoke'), $card);
        $this->assertStringContainsString('WhatsApp abmelden', $card);
    }

    #[Test]
    public function active_state_with_the_exchange_off_explains_the_pause(): void
    {
        $user = $this->optedInUser();
        $user->setMeta('player-exchange', null);

        $card = $this->card($user);

        $this->assertStringContainsString('data-whatsapp-card="active"', $card);
        $this->assertStringContainsString('data-whatsapp-paused="exchange_off"', $card);
        $this->assertSame(1, $this->activeConsentCount($user));
    }

    #[Test]
    public function revoke_in_account_sets_revoked_via_account(): void
    {
        $user = $this->optedInUser();

        $this->actingAs($user)->delete(route('account.whatsapp.revoke'))
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('success');

        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame('account', $this->lastRevokedVia($user));
        $this->assertSame(0, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function revoke_works_even_when_the_main_switch_is_off(): void
    {
        $user = $this->optedInUser();
        Option::query()->where('key', 'whatsapp.enabled')->update(['value' => '0']);

        $this->actingAs($user)->delete(route('account.whatsapp.revoke'))->assertRedirect(route('account.edit'));

        $this->assertSame(0, $this->activeConsentCount($user));
    }

    #[Test]
    public function filters_are_validated_and_saved(): void
    {
        $user = $this->optedInUser();
        $url = route('account.whatsapp.update');
        $this->actingAs($user);

        $this->put($url, ['search' => '1', 'weekdays' => ['8']])->assertSessionHasErrors('weekdays.0');
        $this->put($url, ['search' => '1', 'weekdays' => ['0']])->assertSessionHasErrors('weekdays.0');
        $this->put($url, ['search' => '1', 'quantities' => ['3']])->assertSessionHasErrors('quantities.0');
        $this->put($url, ['search' => '1', 'time_from' => '25:00', 'time_to' => '21:00'])->assertSessionHasErrors('time_from');
        $this->put($url, ['search' => '1', 'time_from' => '21:00', 'time_to' => '17:00'])->assertSessionHasErrors('time_from');
        $this->put($url, ['search' => '1', 'time_from' => '17:00', 'time_to' => '17:00'])->assertSessionHasErrors('time_from');
        $this->put($url, ['search' => '1', 'time_from' => '17:00'])->assertSessionHasErrors('time_to');
        $this->assertNull(NotificationSubscription::query()->where('user_id', $user->uid)->first()->filters);

        $this->put($url, [
            'search' => '1',
            'weekdays' => ['6', '1', '6', '3'],
            'time_from' => '17:00',
            'time_to' => '21:30',
            'quantities' => ['4', '2'],
        ])->assertSessionHasNoErrors()->assertRedirect(route('account.edit'));

        $this->assertSame(
            ['weekdays' => [1, 3, 6], 'time_from' => '17:00', 'time_to' => '21:30', 'quantities' => [2, 4]],
            NotificationSubscription::query()->where('user_id', $user->uid)->first()->filters,
        );

        $this->put($url, ['search' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(NotificationSubscription::query()->where('user_id', $user->uid)->first()->filters);
        $this->assertSame(1, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function saved_filters_show_in_the_card(): void
    {
        $user = $this->optedInUser();
        NotificationSubscription::query()->where('user_id', $user->uid)->first()->update(['filters' => ['weekdays' => [2], 'time_from' => '18:00', 'time_to' => '20:00', 'quantities' => [4]]]);

        $card = $this->card($user);

        $this->assertMatchesRegularExpression('/name="weekdays\[\]" value="2"[^>]*checked/', $card);
        $this->assertDoesNotMatchRegularExpression('/name="weekdays\[\]" value="1"[^>]*checked/', $card);
        $this->assertStringContainsString('value="18:00"', $card);
        $this->assertMatchesRegularExpression('/name="quantities\[\]" value="4"[^>]*checked/', $card);
        $this->assertMatchesRegularExpression('/name="search"[^>]*checked/', $card);
    }

    #[Test]
    public function switch_off_deletes_subscription_and_keeps_consent(): void
    {
        $user = $this->optedInUser();

        $this->actingAs($user)->put(route('account.whatsapp.update'), ['weekdays' => ['1']])->assertRedirect(route('account.edit'));

        $this->assertSame(0, NotificationSubscription::query()->where('user_id', $user->uid)->count());
        $this->assertSame(1, $this->activeConsentCount($user));

        $card = $this->card($user);
        $this->assertDoesNotMatchRegularExpression('/name="search"[^>]*checked/', $card);

        $this->put(route('account.whatsapp.update'), ['search' => '1'])->assertRedirect();
        $this->assertSame(1, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function update_without_an_active_consent_changes_nothing(): void
    {
        $user = $this->eligibleUser();

        $this->actingAs($user)->put(route('account.whatsapp.update'), ['search' => '1'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('error');

        $this->assertSame(0, NotificationSubscription::query()->count());
    }

    #[Test]
    public function the_routes_only_touch_the_logged_in_users_own_data(): void
    {
        $me = $this->optedInUser('+43 660 0000042');
        $other = $this->optedInUser('+43 660 0000043');

        $this->actingAs($me)->delete(route('account.whatsapp.revoke'), ['user_id' => $other->uid]);

        $this->assertSame(0, $this->activeConsentCount($me));
        $this->assertSame(1, $this->activeConsentCount($other));
    }

    #[Test]
    public function consent_checkbox_links_to_the_privacy_page_option(): void
    {
        Option::create(['key' => 'client.website.privacy', 'value' => 'https://club.example.test/datenschutz', 'locale' => null]);
        $user = $this->eligibleUser();

        $card = $this->card($user);

        $this->assertMatchesRegularExpression('/<a [^>]*href="https:\/\/club\.example\.test\/datenschutz"/', $card);
        $this->assertStringContainsString(e(trans('booking.notifications.privacy_notice', [], 'de')), $card);
    }

    #[Test]
    public function without_a_privacy_option_there_is_no_empty_link(): void
    {
        $card = $this->card($this->eligibleUser());

        $this->assertStringNotContainsString('href="#"', $card);
        $this->assertStringContainsString(e(trans('booking.notifications.privacy_notice', [], 'de')), $card);
    }

    #[Test]
    public function after_phone_change_card_shows_re_enable_notice(): void
    {
        $user = $this->optedInUser('+43 660 0000042');
        $user->setMeta('phone', '+43 660 0000099');

        $card = $this->card($user->fresh());

        $this->assertSame('phone_changed', $this->lastRevokedVia($user));
        $this->assertStringContainsString('data-whatsapp-card="ready"', $card);
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.notice_phone_changed', [], 'de')), $card);
    }

    #[Test]
    public function after_stop_card_shows_stop_notice_with_date(): void
    {
        Carbon::setTestNow('2026-11-09 12:00:00');
        $user = $this->optedInUser('+43 660 0000042');
        $this->consents()->revoke($user, 'stop', NotificationConsent::PURPOSE_PLAYER_SEARCH);

        $card = $this->card($user);

        $this->assertStringContainsString(e(trans('booking.notifications.account_card.notice_stop', ['date' => '09.11.2026'], 'de')), $card);
        $this->assertStringContainsString('data-whatsapp-card="ready"', $card);
    }

    #[Test]
    public function after_a_revoke_in_the_account_no_notice_is_shown(): void
    {
        $user = $this->optedInUser();
        $this->consents()->revoke($user, 'account', NotificationConsent::PURPOSE_PLAYER_SEARCH);

        $card = $this->card($user);

        $this->assertStringContainsString('data-whatsapp-card="ready"', $card);
        $this->assertStringNotContainsString('data-whatsapp-notice', $card);
    }

    #[Test]
    public function no_code_input_or_modal_in_markup(): void
    {
        $cards = [
            $this->card($this->optedInUser('+43 660 0000043')),
            $this->card($this->eligibleUser('+43 660 0000044')),
        ];
        foreach ($cards as $card) {
            $this->assertNotSame('', $card);
            $this->assertDoesNotMatchRegularExpression('/name="(code|otp|verification[a-z_]*)"/i', $card);
            $this->assertStringNotContainsString('<dialog', $card);
            $this->assertStringNotContainsString('data-modal', $card);
        }
    }

    #[Test]
    public function de_and_en_key_sets_are_identical(): void
    {
        $de = array_keys(Arr::dot(require lang_path('de/booking/notifications.php')));
        $en = array_keys(Arr::dot(require lang_path('en/booking/notifications.php')));
        sort($de);
        sort($en);

        $this->assertSame($de, $en);
    }

    #[Test]
    public function the_english_card_renders_english_texts(): void
    {
        $card = $this->card($this->eligibleUser(), 'en');

        $this->assertStringContainsString(e(trans('booking.notifications.consent_text_v1', [], 'en')), $card);
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.title', [], 'en')), $card);
    }

    #[Test]
    public function routes_are_throttled_and_need_login(): void
    {
        foreach (['account.whatsapp.enable' => 'POST', 'account.whatsapp.update' => 'PUT', 'account.whatsapp.revoke' => 'DELETE'] as $name => $method) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains($method, $route->methods());
            $this->assertSame('my-account/whatsapp', $route->uri());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('throttle:10,1', $middleware, $name);
            $this->assertContains('auth', $middleware, $name);
        }

        $this->post(route('account.whatsapp.enable'), ['consent' => '1'])->assertRedirect('/login');
        $this->put(route('account.whatsapp.update'), ['search' => '1'])->assertRedirect('/login');
        $this->delete(route('account.whatsapp.revoke'))->assertRedirect('/login');
    }
}
