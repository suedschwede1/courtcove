<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Http\Controllers\NotificationSettingsController;
use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSubscription;
use App\Models\Option;
use App\Models\User;
use App\Notifications\Registry\NotificationPurposeDefinition;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Registry\NotificationTypeDefinition;
use App\Services\Notifications\MessageContent;
use App\Services\Notifications\SubjectResolver;
use App\Services\Notifications\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

/**
 * Proof of extensibility on the web side: a type and purpose registered from the test show up in the account
 * card (own consent text and consent, switch, filters only where defined) and on the admin page (switch,
 * template fields per locale, variables, price override, figures) without any change to card or admin page.
 */
class DummyNotificationWebTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    private const PURPOSE = 'dummy_purpose';

    private const PLAIN = 'dummy.reminder';

    private const FILTERED = 'dummy.digest';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['de' => 'Dummy-Erinnerung', 'en' => 'Dummy reminder'] as $locale => $label) {
            app('translator')->addLines([
                'texts.purpose' => 'Dummy-Zweck',
                'texts.purpose_description' => 'Benachrichtigungen des Dummy-Zwecks.',
                'texts.consent' => 'Ich stimme den Dummy-Nachrichten zu.',
                'texts.consent_short' => 'Mit dem Einschalten stimmst du den Dummy-Nachrichten zu.',
                'texts.version' => 'dummy-v7',
                'texts.plain' => $label,
                'texts.plain_description' => 'Dummy ohne Filter.',
                'texts.plain_variables' => ['Erster Wert', 'Zweiter Wert'],
                'texts.digest' => 'Dummy-Übersicht',
                'texts.digest_description' => 'Dummy mit Filter.',
                'texts.size' => 'Größe',
                'texts.sizes' => ['s' => 'Klein', 'l' => 'Groß'],
            ], $locale, 'dummyweb');
        }

        $registry = app(NotificationRegistry::class);
        $registry->register(
            new NotificationTypeDefinition(
                key: self::PLAIN,
                labelKey: 'dummyweb::texts.plain',
                descriptionKey: 'dummyweb::texts.plain_description',
                templateKey: 'dummy_reminder',
                resolver: WebDummyResolver::class,
                purpose: self::PURPOSE,
                priority: 25,
                optIn: true,
                variablesKey: 'dummyweb::texts.plain_variables',
            ),
            new NotificationPurposeDefinition(
                key: self::PURPOSE,
                labelKey: 'dummyweb::texts.purpose',
                descriptionKey: 'dummyweb::texts.purpose_description',
                consentTextKey: 'dummyweb::texts.consent',
                consentVersionKey: 'dummyweb::texts.version',
                consentShortKey: 'dummyweb::texts.consent_short',
            ),
        );
        $registry->register(new NotificationTypeDefinition(
            key: self::FILTERED,
            labelKey: 'dummyweb::texts.digest',
            descriptionKey: 'dummyweb::texts.digest_description',
            templateKey: 'dummy_digest',
            resolver: WebDummyResolver::class,
            purpose: self::PURPOSE,
            filters: ['sizes' => ['kind' => 'choice', 'options' => ['s', 'l'], 'labelKey' => 'dummyweb::texts.size', 'optionsKey' => 'dummyweb::texts.sizes']],
        ));
    }

    /** Account with a mobile number but without the player exchange (the dummy purpose needs none). */
    private function user(): User
    {
        $user = $this->eligibleUser();
        $user->setMeta('player-exchange', null);

        return $user->fresh();
    }

    /** The dummy purpose's row of the list (query: e.g. `['whatsapp' => purpose]` asks for the row's panel). */
    private function dummyCard(User $user, array $query = []): string
    {
        $html = (string) $this->actingAs($user->fresh())->get(route('account.edit', $query))->assertOk()->getContent();
        preg_match('/<li[^>]*data-whatsapp-purpose="'.self::PURPOSE.'"[^>]*>.*?<\/li>/s', $html, $m);

        return $m[0] ?? '';
    }

    /** The whole card (number section, list, common note). */
    private function wholeCard(User $user): string
    {
        $html = (string) $this->actingAs($user->fresh())->get(route('account.edit'))->assertOk()->getContent();
        preg_match('/<section[^>]*data-whatsapp-card.*?<\/section>/s', $html, $m);

        return $m[0] ?? '';
    }

    private function consentOf(User $user, string $purpose): ?NotificationConsent
    {
        return NotificationConsent::query()->where('user_id', $user->uid)->where('purpose', $purpose)->whereNull('revoked_at')->first();
    }

    #[Test]
    public function the_card_shows_the_purpose_with_its_own_consent_text(): void
    {
        $user = $this->user();
        $html = (string) $this->actingAs($user)->get(route('account.edit'))->assertOk()->getContent();

        $card = $this->dummyCard($user);
        $this->assertStringContainsString('data-whatsapp-row="ready"', $card);
        $this->assertMatchesRegularExpression('/id="whatsapp-title-'.self::PURPOSE.'"[^>]*>Dummy-Zweck</', $card);
        $this->assertMatchesRegularExpression('/id="whatsapp-desc-'.self::PURPOSE.'"[^>]*>Benachrichtigungen des Dummy-Zwecks\.</', $card);
        $this->assertMatchesRegularExpression('/<button type="submit" name="consent" value="1"[^>]*role="switch"[^>]*aria-checked="false"[^>]*aria-labelledby="whatsapp-title-'.self::PURPOSE.'"[^>]*aria-describedby="whatsapp-desc-'.self::PURPOSE.' whatsapp-consent-'.self::PURPOSE.'"/', $card);
        // Its own consent sentence next to the switch, its own full text behind "Mehr erfahren", its own version.
        $this->assertMatchesRegularExpression('/id="whatsapp-consent-'.self::PURPOSE.'"[^>]*>Mit dem Einschalten stimmst du den Dummy-Nachrichten zu\.</', $card);
        $this->assertMatchesRegularExpression('/<details[^>]*>\s*<summary[^>]*>Mehr erfahren<\/summary>.*?Ich stimme den Dummy-Nachrichten zu\./s', $card);
        $this->assertStringContainsString('data-whatsapp-consent-version="dummy-v7"', $card);
        $this->assertStringContainsString(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]), $card);
        // No filters before the row is on (the only fold-out is "Mehr erfahren").
        $this->assertSame(1, substr_count($card, '<details'));
        $this->assertStringNotContainsString('name="filters[', $card);

        // One card, one list: the dummy purpose is the second row next to the player search, which is locked by its
        // own prerequisite (said in its row, not in the number section).
        $this->assertSame(1, substr_count($html, 'data-whatsapp-card'));
        $this->assertSame(1, substr_count($html, 'data-whatsapp-number'));
        $this->assertMatchesRegularExpression('/data-whatsapp-purpose="player_search" data-whatsapp-row="locked".*data-whatsapp-purpose="'.self::PURPOSE.'" data-whatsapp-row="ready"/s', $html);
        $this->assertStringNotContainsString('WhatsApp-Benachrichtigungen: ', $html);
        $whole = $this->wholeCard($user);
        $this->assertSame(1, substr_count($whole, 'data-whatsapp-reason="exchange_off"'));
        $this->assertMatchesRegularExpression('/<li[^>]*data-whatsapp-purpose="player_search"(?:(?!<\/li>).)*data-whatsapp-reason="exchange_off"/s', $whole);
        $this->assertSame(1, substr_count($whole, 'id="whatsapp-privacy"'));
    }

    #[Test]
    public function the_dummy_switch_consents_and_withdraws_its_own_purpose_directly(): void
    {
        $user = $this->user();
        $user->setMeta('player-exchange', '1');
        $this->consents()->optIn($user->fresh(), NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');

        // The dummy switch posts to the dummy purpose only, with what the button sends.
        $this->assertMatchesRegularExpression('/<form[^>]*method="POST"[^>]*action="'.preg_quote(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]), '/').'"/', $this->dummyCard($user));
        $this->actingAs($user)->post(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]), $this->switchOn(self::PURPOSE))->assertSessionHas('whatsapp_row.tone', 'good');
        $this->assertSame(app(NotificationRegistry::class)->purpose(self::PURPOSE)?->consentVersion(), $this->consentOf($user, self::PURPOSE)?->consent_text_version);

        $active = $this->dummyCard($user);
        $this->assertStringContainsString('data-whatsapp-row="active"', $active);
        $this->assertMatchesRegularExpression('/action="'.preg_quote(route('account.whatsapp.revoke', ['purpose' => self::PURPOSE]), '/').'"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="_method" value="DELETE">\s*<button type="submit"[^>]*role="switch"[^>]*aria-checked="true"/', $active);

        $this->actingAs($user)->delete(route('account.whatsapp.revoke', ['purpose' => self::PURPOSE]))->assertRedirect(NotificationSettingsController::rowUrl(self::PURPOSE));
        $this->assertNull($this->consentOf($user, self::PURPOSE));
        $this->assertNotNull($this->consentOf($user, NotificationConsent::PURPOSE_PLAYER_SEARCH));
    }

    #[Test]
    public function a_row_without_filter_definitions_has_no_filter_area(): void
    {
        Option::create(['key' => 'whatsapp.type.'.self::FILTERED.'.enabled', 'value' => '0', 'locale' => null]);
        $user = $this->user();
        $this->consents()->optIn($user, self::PURPOSE, 'de');

        $card = $this->dummyCard($user);

        $this->assertStringContainsString('data-whatsapp-row="active"', $card);
        $this->assertStringNotContainsString('data-whatsapp-settings', $card);
        $this->assertStringNotContainsString('name="filters[', $card);
        $this->assertStringNotContainsString('name="_method" value="PUT"', $card);
    }

    #[Test]
    public function the_dummy_registration_is_complete_and_a_row_without_subscription_asks_for_a_choice(): void
    {
        // As registered in setUp, the dummy purpose subscribes its opt-in type when switched on.
        $this->assertSame([], app(NotificationRegistry::class)->purposesWithoutOptInType());

        // With the opt-in type off only the manual type is left: switching on subscribes nothing, so the row's
        // settings stand open with a hint to choose.
        Option::create(['key' => 'whatsapp.type.'.self::PLAIN.'.enabled', 'value' => '0', 'locale' => null]);
        $user = $this->user();
        $this->consents()->optIn($user, self::PURPOSE, 'de');
        $this->assertFalse(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::FILTERED)->exists());

        $card = $this->dummyCard($user);
        $this->assertMatchesRegularExpression('/<details[^>]*data-whatsapp-settings[^>]*open/', $card);
        $this->assertStringContainsString('data-whatsapp-choose', $card);
        $this->assertStringContainsString(e(trans('booking.notifications.account_card.choose_types', [], 'de')), $card);

        // Once a type is on, the hint goes.
        NotificationSubscription::create(['user_id' => $user->uid, 'type' => self::FILTERED, 'channel' => 'whatsapp', 'filters' => null]);
        $this->assertStringNotContainsString('data-whatsapp-choose', $this->dummyCard($user));
    }

    #[Test]
    public function a_locked_number_is_said_once_and_locks_every_row(): void
    {
        $user = $this->user();
        $user->setMeta('phone', null);

        $card = $this->wholeCard($user);

        $this->assertSame(1, substr_count($card, e(trans('booking.notifications.refused.no_phone', [], 'de'))));
        $this->assertMatchesRegularExpression('/<div[^>]*data-whatsapp-number[^>]*>(?:(?!data-whatsapp-list).)*data-whatsapp-reason="no_phone"/s', $card);
        $this->assertSame(2, substr_count($card, 'data-whatsapp-row="locked"'));
        $this->assertSame(2, preg_match_all('/<button[^>]*role="switch"[^>]*disabled/', $card));
        $this->assertStringNotContainsString('name="consent"', $card);
        // The player search row does not repeat its own prerequisite while the number blocks everything.
        $this->assertStringNotContainsString('data-whatsapp-reason="exchange_off"', $card);
    }

    #[Test]
    public function opt_in_and_revoke_only_touch_their_purpose(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]))->assertSessionHasErrors('consent');

        $this->actingAs($user)->post(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]), $this->switchOn(self::PURPOSE))
            ->assertRedirect(NotificationSettingsController::rowUrl(self::PURPOSE))->assertSessionHas('whatsapp_row.tone', 'good');

        $this->assertSame('dummy-v7', $this->consentOf($user, self::PURPOSE)?->consent_text_version);
        $this->assertNull($this->consentOf($user, NotificationConsent::PURPOSE_PLAYER_SEARCH));
        $this->assertTrue(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::PLAIN)->exists());
        $this->assertFalse(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::FILTERED)->exists());

        // A player search consent next to it stays when the dummy purpose is withdrawn.
        $user->setMeta('player-exchange', '1');
        $this->consents()->optIn($user->fresh(), NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');
        $this->actingAs($user)->delete(route('account.whatsapp.revoke', ['purpose' => self::PURPOSE]))->assertRedirect(NotificationSettingsController::rowUrl(self::PURPOSE));

        $this->assertNull($this->consentOf($user, self::PURPOSE));
        $this->assertNotNull($this->consentOf($user, NotificationConsent::PURPOSE_PLAYER_SEARCH));
        $this->assertFalse(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::PLAIN)->exists());
        $this->assertTrue(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', 'player_search.opened')->exists());
    }

    #[Test]
    public function an_unregistered_purpose_is_not_found(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('account.whatsapp.enable', ['purpose' => 'nope']), $this->switchOn('nope'))->assertNotFound();
        $this->actingAs($user)->put(route('account.whatsapp.update', ['purpose' => 'nope']))->assertNotFound();
        $this->actingAs($user)->delete(route('account.whatsapp.revoke', ['purpose' => 'nope']))->assertNotFound();
    }

    #[Test]
    public function active_card_shows_switches_and_filters_only_where_defined(): void
    {
        $user = $this->user();
        $this->consents()->optIn($user, self::PURPOSE, 'de');

        $card = $this->dummyCard($user);

        $this->assertStringContainsString('data-whatsapp-row="active"', $card);
        // Two types in one purpose: each gets its own box inside the row's settings.
        $this->assertMatchesRegularExpression('/type="checkbox" name="on\[dummy-reminder\]"[^>]*checked/', $card);
        $this->assertStringContainsString('name="on[dummy-digest]"', $card);
        $this->assertStringNotContainsString('filters[dummy-reminder]', $card);
        $this->assertStringContainsString('name="filters[dummy-digest][sizes][]"', $card);
        $this->assertStringContainsString('Klein', $card);
        $this->assertSame(1, substr_count($card, 'data-whatsapp-settings'));
        $this->assertStringContainsString(route('account.whatsapp.revoke', ['purpose' => self::PURPOSE]), $card);
    }

    #[Test]
    public function filters_of_the_purpose_are_validated_from_the_definition_and_saved(): void
    {
        $user = $this->user();
        $this->consents()->optIn($user, self::PURPOSE, 'de');
        $url = route('account.whatsapp.update', ['purpose' => self::PURPOSE]);
        $this->actingAs($user);

        $this->put($url, ['on' => ['dummy-digest' => '1'], 'filters' => ['dummy-digest' => ['sizes' => ['xl']]]])
            ->assertSessionHasErrors('filters.dummy-digest.sizes.0');
        $this->flushSession();

        $this->put($url, ['on' => ['dummy-digest' => '1'], 'filters' => ['dummy-digest' => ['sizes' => ['s', 'l']]]])
            ->assertSessionHasNoErrors()->assertRedirect(route('account.edit'));

        $this->assertSame(['sizes' => ['l', 's']], NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::FILTERED)->value('filters'));
        // The plain type was not ticked in this form, so it is switched off.
        $this->assertFalse(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::PLAIN)->exists());
    }

    #[Test]
    public function a_purpose_without_switched_on_types_and_without_consent_is_not_shown(): void
    {
        $this->onlyTheFollowupIsOn();
        $user = $this->user();

        // A switched-on follow-up alone does not offer the purpose: it has no subscription of its own.
        $this->assertSame('', $this->dummyCard($user));

        // Nor can the purpose be consented to through its URL.
        $this->actingAs($user)->post(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]), $this->switchOn(self::PURPOSE))->assertNotFound();
        $this->assertNull($this->consentOf($user, self::PURPOSE));
    }

    #[Test]
    public function a_purpose_with_consent_but_without_switched_on_types_only_offers_withdrawal(): void
    {
        $user = $this->user();
        $this->consents()->optIn($user, self::PURPOSE, 'de');
        $this->onlyTheFollowupIsOn();

        $card = $this->dummyCard($user);

        $this->assertStringContainsString('data-whatsapp-row="active"', $card);
        $this->assertStringContainsString(route('account.whatsapp.revoke', ['purpose' => self::PURPOSE]), $card);
        $this->assertStringContainsString('name="_method" value="DELETE"', $card);
        $this->assertStringNotContainsString('name="on[', $card);
        $this->assertStringNotContainsString('name="_method" value="PUT"', $card);
    }

    /** Both subscribable dummy types off, a dummy follow-up on. */
    private function onlyTheFollowupIsOn(): void
    {
        app(NotificationRegistry::class)->register(new NotificationTypeDefinition(
            key: 'dummy.followup',
            labelKey: 'dummyweb::texts.plain',
            descriptionKey: 'dummyweb::texts.plain_description',
            templateKey: 'dummy_followup',
            resolver: WebDummyResolver::class,
            purpose: self::PURPOSE,
            followupOf: self::PLAIN,
        ));
        Option::create(['key' => 'whatsapp.type.'.self::PLAIN.'.enabled', 'value' => '0', 'locale' => null]);
        Option::create(['key' => 'whatsapp.type.'.self::FILTERED.'.enabled', 'value' => '0', 'locale' => null]);
    }

    #[Test]
    public function admin_page_shows_and_stores_the_type_block(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        Option::create(['key' => 'whatsapp.template.dummy_reminder.de', 'value' => 'HXtest-dummy-de', 'locale' => null]);
        NotificationLogEntry::create([
            'type' => self::PLAIN, 'channel' => 'whatsapp', 'user_id' => null, 'subject_type' => 'dummy', 'subject_id' => 1,
            'dedupe_key' => 'web:'.Str::uuid()->toString(), 'status' => NotificationLogEntry::DELIVERED, 'priority' => 25,
            'attempts' => 0, 'not_before' => Carbon::now(), 'sent_at' => Carbon::now(),
        ]);

        $html = (string) $this->actingAsWithoutAdminMode($admin)->get('/admin/whatsapp')->assertOk()->getContent();
        preg_match('/<section[^>]*data-wa-type="'.preg_quote(self::PLAIN, '/').'"[^>]*>.*?<\/section>/s', $html, $m);
        $block = $m[0] ?? '';

        $this->assertStringContainsString('Dummy-Erinnerung', $block);
        $this->assertStringContainsString('name="type_dummy-reminder_shown"', $block);
        $this->assertMatchesRegularExpression('/name="type_dummy-reminder_enabled"[^>]*checked/', $block);
        // The switch names its type (several "active" switches on one page).
        $this->assertMatchesRegularExpression('/name="type_dummy-reminder_enabled"[^>]*>\s*<span class="ds-option__label">[^<]*Dummy-Erinnerung/', $block);
        $this->assertStringContainsString('value="HXtest-dummy-de"', $block);
        $this->assertStringContainsString('name="type_dummy-reminder_tpl_en"', $block);
        $this->assertStringContainsString('name="type_dummy-reminder_price"', $block);
        $this->assertStringContainsString('{{1}} Erster Wert', html_entity_decode($block));
        $this->assertStringContainsString('{{2}} Zweiter Wert', html_entity_decode($block));
        $this->assertMatchesRegularExpression('/data-wa-stats="dummy\.reminder"[^>]*>\s*Gesendet in diesem Monat: 1 \(davon 1 zugestellt/', $block);
        // English falls back to the German template: nothing is skipped, so no hint; the other dummy type has none at all.
        $this->assertStringNotContainsString('data-wa-template-missing="dummy.reminder', $block);
        // One hint per type, naming every language without a template.
        $this->assertSame(1, substr_count($html, 'data-wa-template-missing="dummy.digest"'));
        $this->assertMatchesRegularExpression('/data-wa-template-missing="dummy\.digest"[^>]*>[^<]*DE, EN/', $html);

        $this->actingAsWithoutAdminMode($admin)->put('/admin/whatsapp', [
            'type_dummy-reminder_shown' => '1',
            'type_dummy-reminder_price' => '0,12',
            'type_dummy-reminder_tpl_en' => 'HXtest-dummy-en',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('0', Option::getValue('whatsapp.type.dummy.reminder.enabled'));
        $this->assertSame('0.12', Option::getValue('whatsapp.price.dummy.reminder'));
        $this->assertSame('HXtest-dummy-en', Option::getValue('whatsapp.template.dummy_reminder.en'));
        $this->assertSame(120_000, app(WhatsAppSettings::class)->priceEstimateMicros(self::PLAIN));

        $this->actingAsWithoutAdminMode($admin)->put('/admin/whatsapp', ['type_dummy-reminder_price' => 'abc'])
            ->assertSessionHasErrors('type_dummy-reminder_price');
        // The error is shown under the field, with the id the input points to.
        $html = (string) $this->actingAsWithoutAdminMode($admin)->followingRedirects()
            ->put('/admin/whatsapp', ['type_dummy-reminder_price' => 'abc'])->assertOk()->getContent();
        $this->assertStringContainsString('id="wa-type_dummy-reminder_price-error"', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="[^"]*wa-type_dummy-reminder_price-error/', $html);
    }
}

/** Resolver of the web dummy types (never sends in these tests). */
final class WebDummyResolver implements SubjectResolver
{
    public function stillRelevant(NotificationLogEntry $row): bool
    {
        return true;
    }

    public function build(NotificationLogEntry $row, User $user, string $locale): MessageContent
    {
        return new MessageContent([], null, 'Dummy');
    }
}
