<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Http\Controllers\NotificationSettingsController;
use App\Models\NotificationConsent;
use App\Models\NotificationSubscription;
use App\Models\Option;
use App\Models\User;
use App\Modules\Members\Models\Member;
use App\Notifications\Registry\NotificationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
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
        $reasons = ['account_inactive', 'no_phone', 'phone_invalid', 'phone_not_mobile', 'exchange_off'];

        return array_combine($reasons, array_map(fn (string $r): array => [$r], $reasons));
    }

    private function breakGate(User $user, string $reason): void
    {
        match ($reason) {
            'account_inactive' => $user->forceFill(['status' => 'blocked'])->save(),
            'no_phone' => $user->setMeta('phone', null),
            'phone_invalid' => $user->setMeta('phone', '12'),
            'phone_not_mobile' => $user->setMeta('phone', '+43 1 5880000'),
            'exchange_off' => $user->setMeta('player-exchange', null),
        };
    }

    /**
     * The update form in the generic field names: `search` becomes the switch `on[<slug>]`, the filter fields
     * move under `filters[<slug>]` (player search type).
     *
     * @param  array<string, mixed>  $flat
     * @return array<string, mixed>
     */
    private function form(array $flat): array
    {
        $slug = 'player_search-opened';
        $data = [];
        foreach ($flat as $key => $value) {
            match ($key) {
                'search' => $data['on'][$slug] = $value,
                'weekdays', 'time_from', 'time_to', 'quantities' => $data['filters'][$slug][$key] = $value,
                default => $data[$key] = $value,
            };
        }

        return $data;
    }

    private function card(User $user, string $locale = 'de'): string
    {
        $user->setMeta('locale', $locale);
        $html = (string) $this->actingAs($user->fresh())->get(route('account.edit'))->assertOk()->getContent();
        preg_match('/<section[^>]*data-whatsapp-card.*?<\/section>/s', $html, $m);

        return $m[0] ?? '';
    }

    /** The account page on the player search row (where enable and revoke answer). */
    private function row(): string
    {
        return route('account.edit').'#whatsapp-player_search';
    }

    #[Test]
    public function enable_and_revoke_answer_on_the_row_in_success_and_failure(): void
    {
        $user = $this->eligibleUser();
        $this->actingAs($user);

        // Failure: back on the row with the error and the old input.
        $this->post(route('account.whatsapp.enable'), ['_whatsapp_form' => 'player_search'])
            ->assertRedirect($this->row())->assertSessionHasErrors('consent')->assertSessionHasInput('_whatsapp_form', 'player_search');
        $this->flushSession();
        $this->post(route('account.whatsapp.enable'), $this->switchOn())->assertRedirect($this->row())->assertSessionHas('whatsapp_row.tone', 'good');
        $this->delete(route('account.whatsapp.revoke'))->assertRedirect($this->row())->assertSessionHas('whatsapp_row.tone', 'good');

        // A refused gate answers on the row too.
        $user->setMeta('player-exchange', null);
        $this->actingAs($user->fresh())->post(route('account.whatsapp.enable'), $this->switchOn())->assertRedirect($this->row())->assertSessionHas('whatsapp_row.tone', 'problem');

        // The row is a focus target for the anchor and keeps a margin above it when jumped to.
        $this->assertMatchesRegularExpression('/<li id="whatsapp-player_search"[^>]*tabindex="-1"[^>]*class="[^"]*scroll-mt-6/', $this->card($user));
    }

    #[Test]
    public function switching_answers_inline_in_the_row_without_the_modal_flash(): void
    {
        $user = $this->eligibleUser();
        $user->setMeta('locale', 'de');
        $status = fn (string $html): string => preg_match('/<li id="whatsapp-player_search".*?<\/li>/s', $html, $m) === 1 ? $m[0] : '';

        $on = $this->actingAs($user->fresh())->post(route('account.whatsapp.enable'), $this->switchOn());
        $on->assertSessionMissing('success')->assertSessionMissing('error')
            ->assertSessionHas('whatsapp_row', ['purpose' => 'player_search', 'tone' => 'good', 'text' => trans('booking.notifications.account_card.enabled_flash', [], 'de')]);
        $html = (string) $this->followRedirects($on)->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/data-modal|x-data="dsModal/', $html);
        $this->assertMatchesRegularExpression('/<p[^>]*role="status"[^>]*aria-live="polite"[^>]*data-whatsapp-status="good"[^>]*>'.preg_quote(e(trans('booking.notifications.account_card.enabled_flash', [], 'de')), '/').'</', $status($html));

        $off = $this->actingAs($user->fresh())->delete(route('account.whatsapp.revoke'));
        $off->assertSessionMissing('success')->assertSessionHas('whatsapp_row.text', trans('booking.notifications.account_card.revoked_flash', [], 'de'));
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.revoked_flash', [], 'de')), $status((string) $this->followRedirects($off)->getContent()));

        // A refused gate is an error at the row, not a modal either.
        $user->setMeta('player-exchange', null);
        $refused = $this->actingAs($user->fresh())->post(route('account.whatsapp.enable'), $this->switchOn());
        $refused->assertSessionMissing('error');
        $this->assertMatchesRegularExpression('/data-whatsapp-status="problem"[^>]*>'.preg_quote(e(trans('booking.notifications.refused.exchange_off', [], 'de')), '/').'</', $status((string) $this->followRedirects($refused)->getContent()));

        $this->assertSame('Eingeschaltet.', trans('booking.notifications.account_card.enabled_flash', [], 'de'));
        $this->assertNotSame('booking.notifications.account_card.enabled_flash', trans('booking.notifications.account_card.enabled_flash', [], 'en'));
    }

    #[Test]
    public function dates_in_the_card_use_an_unambiguous_format_per_language(): void
    {
        Carbon::setTestNow('2026-11-09 12:00:00');
        $user = $this->optedInUser();

        $this->assertStringContainsString('09.11.2026', $this->card($user, 'de'));
        $en = $this->card($user, 'en');
        $this->assertStringContainsString('9 Nov 2026', $en);
        $this->assertStringNotContainsString('11/09/2026', $en);

        $this->consents()->revoke($user, 'stop', NotificationConsent::PURPOSE_PLAYER_SEARCH);
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.notice_stop', ['date' => '9 Nov 2026'], 'en')), $this->card($user, 'en'));
        $this->assertSame('Get notified on WhatsApp. Choose which messages you want.', trans('booking.notifications.account_card.intro', [], 'en'));
    }

    #[Test]
    public function a_missing_number_carries_the_profile_link_in_its_reason_box(): void
    {
        $user = $this->eligibleUser();
        $user->setMeta('phone', null);

        $card = $this->card($user);

        $this->assertMatchesRegularExpression('/data-whatsapp-reason="no_phone"[^>]*>(?:(?!<\/div>).)*href="'.preg_quote(route('account.edit').'#phone', '/').'"/s', $card);
    }

    #[Test]
    public function the_switch_sends_the_version_it_shows_and_an_outdated_one_is_refused(): void
    {
        $user = $this->eligibleUser();
        $version = app(NotificationRegistry::class)->purpose(NotificationConsent::PURPOSE_PLAYER_SEARCH)?->consentVersion();

        $card = $this->card($user);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="consent_version" value="'.preg_quote((string) $version, '/').'">/', $card);

        // Seen under an older text: nothing stored, the user is asked to switch on again.
        $this->actingAs($user)->post(route('account.whatsapp.enable'), ['consent' => '1', 'consent_version' => 'wa-2026-11-v1'])
            ->assertRedirect($this->row())
            ->assertSessionHasErrors(['consent' => trans('booking.notifications.account_card.consent_outdated', [], 'de')]);
        $this->assertSame(0, NotificationConsent::query()->where('user_id', $user->uid)->count());
        $this->assertSame(0, NotificationSubscription::query()->count());
        $this->flushSession();

        // Without the version (old clients) it is refused like a missing consent.
        $this->actingAs($user)->post(route('account.whatsapp.enable'), ['consent' => '1'])
            ->assertSessionHasErrors(['consent' => trans('booking.notifications.account_card.consent_required', [], 'de')]);
        $this->assertSame(0, NotificationConsent::query()->where('user_id', $user->uid)->count());
        $this->flushSession();

        $this->actingAs($user)->post(route('account.whatsapp.enable'), $this->switchOn())->assertSessionHasNoErrors();
        $this->assertSame($version, $this->consents()->activeConsent($user)?->consent_text_version);

        foreach (['de', 'en'] as $locale) {
            $this->assertNotSame('booking.notifications.account_card.consent_outdated', trans('booking.notifications.account_card.consent_outdated', [], $locale));
        }
    }

    #[Test]
    public function the_phone_change_notice_offers_switching_on_again_only_when_the_number_allows_it(): void
    {
        $user = $this->optedInUser('+43 660 0000042');
        $user->setMeta('phone', '+43 660 0000099');
        $again = e(trans('booking.notifications.account_card.notice_phone_changed_again', [], 'de'));

        $card = $this->card($user->fresh());
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.notice_phone_changed', [], 'de')), $card);
        $this->assertStringContainsString($again, $card);

        $user->setMeta('phone', null);
        $card = $this->card($user->fresh());
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.notice_phone_changed', [], 'de')), $card);
        $this->assertStringNotContainsString($again, $card);
        $this->assertStringContainsString('data-whatsapp-reason="no_phone"', $card);
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

        $this->assertStringContainsString('data-whatsapp-row="locked"', $card);
        $this->assertSame(1, substr_count($card, 'data-whatsapp-reason="'.$reason.'"'));
        // The reason is said once: in the number section for the account and the number, else in the row.
        $this->assertSame(1, substr_count($card, e(trans('booking.notifications.refused.'.$reason, [], 'de'))));
        $accountLevel = $reason !== 'exchange_off';
        $this->assertSame($accountLevel, preg_match('/<div[^>]*data-whatsapp-number[^>]*>(?:(?!data-whatsapp-list).)*data-whatsapp-reason="'.$reason.'"/s', $card) === 1);
        $this->assertMatchesRegularExpression('/<button[^>]*role="switch"[^>]*aria-checked="false"[^>]*disabled/', $card);
        $this->assertStringNotContainsString('name="consent"', $card);
        $this->assertStringNotContainsString(route('account.whatsapp.enable'), $card);
        if (in_array($reason, ['no_phone', 'phone_invalid', 'phone_not_mobile'], true)) {
            $this->assertStringContainsString(route('account.edit').'#phone', $card);
        }
    }

    #[Test]
    public function ready_state_shows_masked_number_switch_and_its_consent_sentence(): void
    {
        $user = $this->eligibleUser('+43 660 0000042');

        $card = $this->card($user);

        $this->assertStringContainsString('data-whatsapp-row="ready"', $card);
        $this->assertStringContainsString('+43 … 42', $card);
        $this->assertStringNotContainsString('0000042', $card);
        // The switch is the consent: a submit button of a POST form (CSRF) that sends consent=1, named by the row
        // title and described by the short text and the consent sentence.
        $this->assertMatchesRegularExpression('/<form[^>]*method="POST"[^>]*action="'.preg_quote(route('account.whatsapp.enable', ['purpose' => 'player_search']), '/').'"[^>]*>\s*<input type="hidden" name="_token"/', $card);
        $this->assertMatchesRegularExpression('/<button type="submit" name="consent" value="1"[^>]*role="switch"[^>]*aria-checked="false"[^>]*aria-labelledby="whatsapp-title-player_search"[^>]*aria-describedby="whatsapp-desc-player_search whatsapp-consent-player_search"/', $card);
        $this->assertStringContainsString('id="whatsapp-title-player_search"', $card);
        $this->assertMatchesRegularExpression('/id="whatsapp-consent-player_search"[^>]*>'.preg_quote(e(trans('booking.notifications.purposes.player_search.consent_short', [], 'de')), '/').'</', $card);
        // No separate consent box, tick or confirm button; the full text stands behind "Mehr erfahren".
        $this->assertStringNotContainsString('type="checkbox" name="consent"', $card);
        $this->assertMatchesRegularExpression('/<details[^>]*>\s*<summary[^>]*>'.preg_quote(e(trans('booking.notifications.account_card.consent_more', [], 'de')), '/').'<\/summary>.*?'.preg_quote(e(trans('booking.notifications.consent_text_v1', [], 'de')), '/').'/s', $card);
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.own_number_hint', [], 'de')), $card);
    }

    #[Test]
    public function the_card_is_general_with_the_number_once_and_no_activate_buttons(): void
    {
        $user = $this->eligibleUser('+43 660 0000042');

        $card = $this->card($user);
        $text = $this->text($card);

        $intro = trans('booking.notifications.account_card.intro', [], 'de');
        $this->assertSame('Lass dich per WhatsApp benachrichtigen. Wähle, wofür.', $intro);
        $this->assertStringContainsString(e($intro), $card);
        $this->assertSame(1, substr_count($card, 'data-whatsapp-number'));
        $this->assertSame(1, substr_count($card, '+43 … 42'));
        $this->assertSame(1, substr_count($card, e(trans('booking.notifications.account_card.own_number_hint', [], 'de'))));
        $this->assertSame(1, substr_count($card, e(trans('booking.notifications.privacy_notice', [], 'de'))));
        // The common privacy note comes after the list.
        $this->assertGreaterThan(strpos($card, 'data-whatsapp-list'), strpos($card, 'id="whatsapp-privacy"'));
        $this->assertStringContainsString(route('account.edit').'#phone', $card);
        foreach (['WhatsApp aktivieren', 'WhatsApp abmelden', 'Bestätigen', 'Abbrechen'] as $label) {
            $this->assertStringNotContainsString($label, $text);
        }
        // Purpose specific hints live in the purpose's consent sentence, not in the general parts.
        $this->assertStringNotContainsString('Spielerbörse', $intro);
        $this->assertStringNotContainsString('suchenden Person', trans('booking.notifications.account_card.own_number_hint', [], 'de'));
        $this->assertStringContainsString('suchenden Person', trans('booking.notifications.purposes.player_search.consent_short', [], 'de'));
        $this->assertStringContainsString('STOP', trans('booking.notifications.privacy_notice', [], 'de'));
    }

    #[Test]
    public function the_switch_consents_at_once_with_the_purpose_version_locale_and_hash(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');
        $user = $this->eligibleUser('+43 660 0000042');
        $user->setMeta('locale', 'en');
        $version = app(NotificationRegistry::class)->purpose(NotificationConsent::PURPOSE_PLAYER_SEARCH)?->consentVersion();
        $this->assertSame('wa-2026-11-v2', $version);

        // Exactly what the switch button sends (name="consent" value="1"), nothing else.
        $this->actingAs($user->fresh())->post(route('account.whatsapp.enable', ['purpose' => 'player_search']), $this->switchOn())
            ->assertRedirect($this->row())
            ->assertSessionHas('whatsapp_row.tone', 'good');

        $consent = $this->consents()->activeConsent($user);
        $this->assertNotNull($consent);
        $this->assertSame($version, $consent->consent_text_version);
        $this->assertSame('en', $consent->locale);
        $this->assertSame(NotificationConsent::PURPOSE_PLAYER_SEARCH, $consent->purpose);
        $this->assertSame($this->consents()->phoneHash('+436600000042'), $consent->phone_hash);
        $this->assertSame('2026-11-09 10:00:00', $consent->consented_at->format('Y-m-d H:i:s'));
        $subscription = NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::TYPE);
        $this->assertTrue($subscription->exists());
        $this->assertNull($subscription->value('filters'));

        $card = $this->card($user, 'de');
        $this->assertStringContainsString('data-whatsapp-row="active"', $card);
        $this->assertMatchesRegularExpression('/role="switch"[^>]*aria-checked="true"/', $card);
    }

    #[Test]
    public function a_post_without_the_switch_value_is_refused_with_the_error_at_the_row(): void
    {
        $user = $this->eligibleUser();
        $user->setMeta('locale', 'de');

        $html = (string) $this->actingAs($user->fresh())->from(route('account.edit'))->followingRedirects()
            ->post(route('account.whatsapp.enable', ['purpose' => 'player_search']), ['_whatsapp_form' => 'player_search'])
            ->assertOk()->getContent();
        preg_match('/<section[^>]*data-whatsapp-card.*?<\/section>/s', $html, $m);
        $card = $m[0] ?? '';

        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertMatchesRegularExpression('/role="switch"[^>]*aria-describedby="[^"]*whatsapp-consent-error-player_search/', $card);
        $this->assertMatchesRegularExpression('/<p class="ds-field__error" id="whatsapp-consent-error-player_search">'.preg_quote(e(trans('booking.notifications.account_card.consent_required', [], 'de')), '/').'/', $card);
    }

    #[Test]
    public function the_switch_of_an_active_row_withdraws_at_once(): void
    {
        $user = $this->optedInUser();

        $card = $this->card($user);

        // A DELETE form (CSRF) with the switch as its submit button: no question, no cancel.
        $this->assertMatchesRegularExpression('/<form[^>]*method="POST"[^>]*action="'.preg_quote(route('account.whatsapp.revoke', ['purpose' => 'player_search']), '/').'"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="_method" value="DELETE">\s*<button type="submit"[^>]*role="switch"[^>]*aria-checked="true"/', $card);
        $this->assertStringNotContainsString('data-whatsapp-revoke-confirm', $card);
        $this->assertStringNotContainsString('name="consent"', $card);

        $this->actingAs($user)->delete(route('account.whatsapp.revoke', ['purpose' => 'player_search']))->assertRedirect($this->row());
        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame('account', $this->lastRevokedVia($user));
        $this->assertSame(0, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function consents_of_the_earlier_text_version_stay_valid(): void
    {
        $user = $this->optedInUser('+43 660 0000042');
        NotificationConsent::query()->where('user_id', $user->uid)->update(['consent_text_version' => 'wa-2026-11-v1']);

        $this->assertSame('wa-2026-11-v1', $this->consents()->activeConsent($user)?->consent_text_version);
        $this->assertTrue($this->consents()->gate($user)->passed);
        $this->assertStringContainsString('data-whatsapp-row="active"', $this->card($user));

        // Switching on again (idempotent) neither replaces nor upgrades it.
        $this->actingAs($user)->post(route('account.whatsapp.enable'), $this->switchOn())->assertSessionHas('whatsapp_row.tone', 'good');
        $this->assertSame(1, NotificationConsent::query()->where('user_id', $user->uid)->count());
        $this->assertSame('wa-2026-11-v1', $this->consents()->activeConsent($user)?->consent_text_version);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function oddQueries(): array
    {
        return [
            'purpose' => [['whatsapp' => 'player_search']],
            'unknown purpose' => [['whatsapp' => 'unknown']],
            'array' => [['whatsapp' => ['x']]],
            'empty' => [['whatsapp' => '']],
        ];
    }

    /** @param  array<string, mixed>  $query */
    #[Test]
    #[DataProvider('oddQueries')]
    public function a_get_with_any_query_changes_no_state(array $query): void
    {
        $ready = $this->eligibleUser('+43 660 0000042');
        $active = $this->optedInUser('+43 660 0000043');
        $before = [NotificationConsent::query()->count(), NotificationConsent::query()->whereNull('revoked_at')->count(), NotificationSubscription::query()->count()];

        foreach ([$ready, $active] as $user) {
            $this->actingAs($user)->get(route('account.edit', $query))->assertOk();
        }

        $this->assertSame($before, [NotificationConsent::query()->count(), NotificationConsent::query()->whereNull('revoked_at')->count(), NotificationSubscription::query()->count()]);
        $this->assertSame(0, $this->activeConsentCount($ready));
        $this->assertSame(1, $this->activeConsentCount($active));
    }

    #[Test]
    public function the_stop_notice_offers_switching_on_again_only_when_the_number_allows_it(): void
    {
        Carbon::setTestNow('2026-11-09 12:00:00');
        $user = $this->optedInUser('+43 660 0000042');
        $this->consents()->revoke($user, 'stop', NotificationConsent::PURPOSE_PLAYER_SEARCH);
        $again = e(trans('booking.notifications.account_card.notice_stop_again', [], 'de'));

        $this->assertStringContainsString($again, $this->card($user));

        $user->setMeta('phone', null);
        $card = $this->card($user);
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.notice_stop', ['date' => '09.11.2026'], 'de')), $card);
        $this->assertStringNotContainsString($again, $card);
        $this->assertStringContainsString('data-whatsapp-reason="no_phone"', $card);
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

        $this->actingAs($user->fresh())->post(route('account.whatsapp.enable'), $this->switchOn())
            ->assertRedirect($this->row())
            ->assertSessionHas('whatsapp_row.tone', 'good');

        $consent = $this->consents()->activeConsent($user);
        $this->assertNotNull($consent);
        $this->assertSame('en', $consent->locale);
        $this->assertSame(app(NotificationRegistry::class)->purpose(NotificationConsent::PURPOSE_PLAYER_SEARCH)?->consentVersion(), $consent->consent_text_version);
        $this->assertSame(1, NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::TYPE)->count());
    }

    #[Test]
    #[DataProvider('lockedReasons')]
    public function enable_is_refused_with_a_member_text_for_every_gate_reason(string $reason): void
    {
        $user = $this->eligibleUser();
        $this->breakGate($user, $reason);

        $this->actingAs($user->fresh())->post(route('account.whatsapp.enable'), $this->switchOn())
            ->assertRedirect($this->row())
            ->assertSessionHas('whatsapp_row.text', trans('booking.notifications.refused.'.$reason, [], 'de'));

        $this->assertSame(0, $this->activeConsentCount($user));
    }

    #[Test]
    public function enable_is_refused_when_the_main_switch_is_off(): void
    {
        $user = $this->eligibleUser();
        Option::query()->where('key', 'whatsapp.enabled')->update(['value' => '0']);

        $this->actingAs($user)->post(route('account.whatsapp.enable'), $this->switchOn())
            ->assertSessionHas('whatsapp_row.text', trans('booking.notifications.refused.disabled', [], 'de'));

        $this->assertSame(0, $this->activeConsentCount($user));
    }

    #[Test]
    public function every_gate_reason_and_the_conflict_have_a_member_text_in_both_languages(): void
    {
        foreach (['account_inactive', 'no_phone', 'phone_invalid', 'phone_not_mobile', 'exchange_off', 'disabled', 'conflict'] as $reason) {
            foreach (['de', 'en'] as $locale) {
                $key = 'booking.notifications.refused.'.$reason;
                $this->assertNotSame($key, trans($key, [], $locale), "$locale $reason");
            }
        }

        // The member rules are gone: no texts left for them, and the same reasons in both languages.
        foreach (['de', 'en'] as $locale) {
            $refused = trans('booking.notifications.refused', [], $locale);
            $this->assertIsArray($refused);
            $this->assertArrayNotHasKey('no_member', $refused, $locale);
            $this->assertArrayNotHasKey('member_inactive', $refused, $locale);
        }
        $this->assertSame(
            array_keys(trans('booking.notifications.refused', [], 'de')),
            array_keys(trans('booking.notifications.refused', [], 'en')),
        );
    }

    /** Only the booking account counts: an account without a member record (or with a departed one) may consent. */
    #[Test]
    public function an_account_without_a_member_record_can_consent(): void
    {
        $user = $this->eligibleUser('+43 660 0000042');
        $this->assertSame(0, Member::query()->where('user_id', $user->uid)->count());

        $this->assertStringContainsString('data-whatsapp-row="ready"', $this->card($user));

        $this->actingAs($user->fresh())->post(route('account.whatsapp.enable'), $this->switchOn())
            ->assertRedirect($this->row())
            ->assertSessionHas('whatsapp_row.tone', 'good');

        $this->assertSame(1, $this->activeConsentCount($user));
        $this->assertStringContainsString('data-whatsapp-row="active"', $this->card($user));

        $departed = $this->eligibleUser('+43 660 0000043');
        $this->linkMember($departed, ['status' => 'ausgetreten', 'left_at' => '2026-10-31']);
        $this->actingAs($departed->fresh())->post(route('account.whatsapp.enable'), $this->switchOn())
            ->assertSessionHas('whatsapp_row.tone', 'good');
        $this->assertSame(1, $this->activeConsentCount($departed));
    }

    #[Test]
    public function active_state_shows_switch_on_date_filters_and_revoke(): void
    {
        Carbon::setTestNow('2026-11-08 10:00:00');
        $user = $this->optedInUser('+43 660 0000042');

        $card = $this->card($user);

        $this->assertStringContainsString('data-whatsapp-row="active"', $card);
        $this->assertMatchesRegularExpression('/<button[^>]*role="switch"[^>]*aria-checked="true"/', $card);
        $this->assertStringContainsString('08.11.2026', $card);
        $this->assertStringContainsString('+43 … 42', $card);
        // The only type of the purpose follows the row switch: no second switch, it stays on when filters are saved.
        $this->assertStringContainsString('<input type="hidden" name="on[player_search-opened]" value="1">', $card);
        $this->assertStringNotContainsString('type="checkbox" name="on[player_search-opened]"', $card);
        $this->assertStringContainsString('name="filters[player_search-opened][weekdays][]"', $card);
        $this->assertStringContainsString('name="filters[player_search-opened][time_from]"', $card);
        $this->assertStringContainsString('name="filters[player_search-opened][quantities][]"', $card);
        $this->assertStringContainsString(route('account.whatsapp.revoke'), $card);
    }

    #[Test]
    public function active_state_with_the_exchange_off_explains_the_pause(): void
    {
        $user = $this->optedInUser();
        $user->setMeta('player-exchange', null);

        $card = $this->card($user);

        $this->assertStringContainsString('data-whatsapp-row="active"', $card);
        $this->assertStringContainsString('data-whatsapp-paused="exchange_off"', $card);
        $this->assertSame(1, $this->activeConsentCount($user));
    }

    #[Test]
    public function revoke_in_account_sets_revoked_via_account(): void
    {
        $user = $this->optedInUser();

        $this->actingAs($user)->delete(route('account.whatsapp.revoke'))
            ->assertRedirect($this->row())
            ->assertSessionHas('whatsapp_row.tone', 'good');

        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame('account', $this->lastRevokedVia($user));
        $this->assertSame(0, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function revoke_works_even_when_the_main_switch_is_off(): void
    {
        $user = $this->optedInUser();
        Option::query()->where('key', 'whatsapp.enabled')->update(['value' => '0']);

        $this->actingAs($user)->delete(route('account.whatsapp.revoke'))->assertRedirect($this->row());

        $this->assertSame(0, $this->activeConsentCount($user));
    }

    #[Test]
    public function filters_are_validated_and_saved(): void
    {
        $user = $this->optedInUser();
        $url = route('account.whatsapp.update');
        $this->actingAs($user);

        $this->put($url, $this->form(['search' => '1', 'weekdays' => ['8']]))->assertSessionHasErrors('filters.player_search-opened.weekdays.0');
        $this->put($url, $this->form(['search' => '1', 'weekdays' => ['0']]))->assertSessionHasErrors('filters.player_search-opened.weekdays.0');
        $this->put($url, $this->form(['search' => '1', 'quantities' => ['3']]))->assertSessionHasErrors('filters.player_search-opened.quantities.0');
        $this->put($url, $this->form(['search' => '1', 'time_from' => '25:00', 'time_to' => '21:00']))->assertSessionHasErrors('filters.player_search-opened.time_from');
        $this->put($url, $this->form(['search' => '1', 'time_from' => '21:00', 'time_to' => '17:00']))->assertSessionHasErrors('filters.player_search-opened.time_from');
        $this->put($url, $this->form(['search' => '1', 'time_from' => '17:00', 'time_to' => '17:00']))->assertSessionHasErrors('filters.player_search-opened.time_from');
        $this->put($url, $this->form(['search' => '1', 'time_from' => '17:00']))->assertSessionHasErrors('filters.player_search-opened.time_to');
        $this->assertNull(NotificationSubscription::query()->where('user_id', $user->uid)->first()->filters);

        $this->put($url, $this->form([
            'search' => '1',
            'weekdays' => ['6', '1', '6', '3'],
            'time_from' => '17:00',
            'time_to' => '21:30',
            'quantities' => ['4', '2'],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('account.edit'));

        $this->assertSame(
            ['weekdays' => [1, 3, 6], 'time_from' => '17:00', 'time_to' => '21:30', 'quantities' => [2, 4]],
            NotificationSubscription::query()->where('user_id', $user->uid)->first()->filters,
        );

        $this->put($url, $this->form(['search' => '1']))->assertSessionHasNoErrors();
        $this->assertNull(NotificationSubscription::query()->where('user_id', $user->uid)->first()->filters);
        $this->assertSame(1, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function saved_filters_show_in_the_card(): void
    {
        $user = $this->optedInUser();
        NotificationSubscription::query()->where('user_id', $user->uid)->first()->update(['filters' => ['weekdays' => [2], 'time_from' => '18:00', 'time_to' => '20:00', 'quantities' => [4]]]);

        $card = $this->card($user);

        $this->assertMatchesRegularExpression('/name="filters\[player_search-opened\]\[weekdays\]\[\]" value="2"[^>]*checked/', $card);
        $this->assertDoesNotMatchRegularExpression('/name="filters\[player_search-opened\]\[weekdays\]\[\]" value="1"[^>]*checked/', $card);
        $this->assertStringContainsString('value="18:00"', $card);
        $this->assertMatchesRegularExpression('/name="filters\[player_search-opened\]\[quantities\]\[\]" value="4"[^>]*checked/', $card);
        $this->assertStringContainsString('<input type="hidden" name="on[player_search-opened]" value="1">', $card);
    }

    #[Test]
    public function switch_off_deletes_subscription_and_keeps_consent(): void
    {
        $user = $this->optedInUser();

        $this->actingAs($user)->put(route('account.whatsapp.update'), $this->form(['weekdays' => ['1']]))->assertRedirect(route('account.edit'));

        $this->assertSame(0, NotificationSubscription::query()->where('user_id', $user->uid)->count());
        $this->assertSame(1, $this->activeConsentCount($user));

        // A type that is off while the consent stays (from before the switch per row) shows its own unticked box.
        $card = $this->card($user);
        $this->assertStringContainsString('type="checkbox" name="on[player_search-opened]"', $card);
        $this->assertStringNotContainsString('<input type="hidden" name="on[player_search-opened]"', $card);
        $this->assertDoesNotMatchRegularExpression('/name="on\[player_search-opened\]"[^>]*checked/', $card);

        $this->put(route('account.whatsapp.update'), $this->form(['search' => '1']))->assertRedirect();
        $this->assertSame(1, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function update_without_an_active_consent_changes_nothing(): void
    {
        $user = $this->eligibleUser();

        $this->actingAs($user)->put(route('account.whatsapp.update'), $this->form(['search' => '1']))
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
    public function errors_of_every_weekday_and_quantity_element_are_shown(): void
    {
        $user = $this->optedInUser();
        $this->actingAs($user)->from(route('account.edit'))->put(route('account.whatsapp.update'), $this->form([
            '_whatsapp_form' => 'player_search', 'search' => '1', 'weekdays' => ['1', '9'], 'quantities' => ['2', '3'],
        ]))->assertRedirect(route('account.edit'));
        $bag = session('errors');
        // The session stores the bag as JSON (session serialisation "json"), so it reads back as an array.
        $messages = $bag instanceof ViewErrorBag ? $bag->getBag('default')->toArray() : ($bag['default']['messages'] ?? []);

        // Same login as the request before; the flashed errors are rendered on the account page.
        $html = (string) $this->get(route('account.edit'))->assertOk()->getContent();
        preg_match('/<section[^>]*data-whatsapp-card.*?<\/section>/s', $html, $m);
        $card = $m[0] ?? '';

        foreach (['filters.player_search-opened.weekdays.1', 'filters.player_search-opened.quantities.1'] as $key) {
            $this->assertNotEmpty($messages[$key][0] ?? null, $key);
            $this->assertStringContainsString(e($messages[$key][0]), $card, $key);
        }
        $this->assertStringContainsString('data-whatsapp-settings', $card);
        $this->assertMatchesRegularExpression('/<details[^>]*data-whatsapp-settings[^>]*open/', $card);
    }

    #[Test]
    public function a_privacy_option_that_is_not_http_gets_no_link(): void
    {
        Option::create(['key' => 'client.website.privacy', 'value' => 'javascript:alert(1)', 'locale' => null]);

        $card = $this->card($this->eligibleUser());

        $this->assertStringNotContainsString('javascript:', $card);
        $this->assertStringNotContainsString(e(trans('booking.notifications.account_card.privacy_link', [], 'de')).'</a>', $card);
        $this->assertStringContainsString(e(trans('booking.notifications.privacy_notice', [], 'de')), $card);
    }

    #[Test]
    public function weekday_tiles_carry_the_full_weekday_name(): void
    {
        $de = $this->card($this->optedInUser());
        $this->assertMatchesRegularExpression('/name="filters\[player_search-opened\]\[weekdays\]\[\]" value="1"[^>]*aria-label="Montag"/', $de);
        $this->assertMatchesRegularExpression('/name="filters\[player_search-opened\]\[weekdays\]\[\]" value="7"[^>]*aria-label="Sonntag"/', $de);

        $en = $this->card($this->optedInUser('+43 660 0000001'), 'en');
        $this->assertMatchesRegularExpression('/name="filters\[player_search-opened\]\[weekdays\]\[\]" value="1"[^>]*aria-label="Monday"/', $en);
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
        $this->assertStringContainsString('data-whatsapp-row="ready"', $card);
        $this->assertSame(1, substr_count($card, e(trans('booking.notifications.account_card.notice_phone_changed', [], 'de'))));
    }

    #[Test]
    public function after_stop_card_shows_stop_notice_with_date(): void
    {
        Carbon::setTestNow('2026-11-09 12:00:00');
        $user = $this->optedInUser('+43 660 0000042');
        $this->consents()->revoke($user, 'stop', NotificationConsent::PURPOSE_PLAYER_SEARCH);

        $card = $this->card($user);

        $this->assertStringContainsString(e(trans('booking.notifications.account_card.notice_stop', ['date' => '09.11.2026'], 'de')), $card);
        $this->assertStringContainsString('data-whatsapp-row="ready"', $card);
        // Every row is affected, so the notice stands once above the list; switching on again stays possible.
        $this->assertSame(1, substr_count($card, 'data-whatsapp-notice="stop"'));
        $this->assertMatchesRegularExpression('/data-whatsapp-notice="stop".*data-whatsapp-list/s', $card);
        $this->assertMatchesRegularExpression('/<button[^>]*role="switch"[^>]*aria-checked="false"(?![^>]*disabled)[^>]*>/', $card);
    }

    #[Test]
    public function after_a_revoke_in_the_account_no_notice_is_shown(): void
    {
        $user = $this->optedInUser();
        $this->consents()->revoke($user, 'account', NotificationConsent::PURPOSE_PLAYER_SEARCH);

        $card = $this->card($user);

        $this->assertStringContainsString('data-whatsapp-row="ready"', $card);
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

        $this->assertStringContainsString(e(trans('booking.notifications.purposes.player_search.consent_short', [], 'en')), $card);
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
            $this->assertSame('my-account/whatsapp/{purpose?}', $route->uri());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('throttle:10,1', $middleware, $name);
            $this->assertContains('auth', $middleware, $name);
        }

        $this->post(route('account.whatsapp.enable'), $this->switchOn())->assertRedirect('/login');
        $this->put(route('account.whatsapp.update'), $this->form(['search' => '1']))->assertRedirect('/login');
        $this->delete(route('account.whatsapp.revoke'))->assertRedirect('/login');
    }

    #[Test]
    public function saving_the_card_keeps_filter_keys_no_card_field_owns(): void
    {
        $user = $this->optedInUser();
        NotificationSubscription::query()->where('user_id', $user->uid)->update(['filters' => json_encode(['square_ids' => [3], 'weekdays' => [2]])]);

        $this->actingAs($user)->put(route('account.whatsapp.update'), $this->form(['search' => '1', 'weekdays' => ['5']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['square_ids' => [3], 'weekdays' => [5]], NotificationSubscription::query()->where('user_id', $user->uid)->first()->filters);
    }

    #[Test]
    public function an_unknown_refusal_reason_falls_back_to_the_general_text(): void
    {
        $this->assertSame('booking.notifications.refused.unavailable', NotificationSettingsController::refusedKey('dummy_off'));
        $this->assertSame('booking.notifications.refused.unavailable', NotificationSettingsController::refusedKey(null));
        $this->assertSame('booking.notifications.refused.exchange_off', NotificationSettingsController::refusedKey('exchange_off'));
        foreach (['de', 'en'] as $locale) {
            $this->assertNotSame('booking.notifications.refused.unavailable', trans('booking.notifications.refused.unavailable', [], $locale));
        }
    }

    /** Visible text of a fragment: tags become single spaces (texts split by markup still read in one piece). */
    private function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/<[^>]+>/', ' ', $html)));
    }
}
