<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

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

    private function dummyCard(User $user): string
    {
        $html = (string) $this->actingAs($user->fresh())->get(route('account.edit'))->assertOk()->getContent();
        preg_match('/<section[^>]*data-whatsapp-purpose="'.self::PURPOSE.'"[^>]*>.*?<\/section>/s', $html, $m);

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
        $this->assertStringContainsString('data-whatsapp-card="ready"', $card);
        $this->assertStringContainsString('WhatsApp-Benachrichtigungen: Dummy-Zweck', $card);
        $this->assertStringContainsString('Benachrichtigungen des Dummy-Zwecks.', $card);
        $this->assertStringContainsString('Ich stimme den Dummy-Nachrichten zu.', $card);
        $this->assertStringContainsString(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]), $card);
        // The player search card is its own, locked by its own prerequisite.
        $this->assertMatchesRegularExpression('/data-whatsapp-card="locked" data-whatsapp-purpose="player_search"/', $html);
    }

    #[Test]
    public function opt_in_and_revoke_only_touch_their_purpose(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]))->assertSessionHasErrors('consent');

        $this->actingAs($user)->post(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]), ['consent' => '1'])
            ->assertRedirect(route('account.edit'))->assertSessionHas('success');

        $this->assertSame('dummy-v7', $this->consentOf($user, self::PURPOSE)?->consent_text_version);
        $this->assertNull($this->consentOf($user, NotificationConsent::PURPOSE_PLAYER_SEARCH));
        $this->assertTrue(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::PLAIN)->exists());
        $this->assertFalse(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::FILTERED)->exists());

        // A player search consent next to it stays when the dummy purpose is withdrawn.
        $user->setMeta('player-exchange', '1');
        $this->consents()->optIn($user->fresh(), NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');
        $this->actingAs($user)->delete(route('account.whatsapp.revoke', ['purpose' => self::PURPOSE]))->assertRedirect(route('account.edit'));

        $this->assertNull($this->consentOf($user, self::PURPOSE));
        $this->assertNotNull($this->consentOf($user, NotificationConsent::PURPOSE_PLAYER_SEARCH));
        $this->assertFalse(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', self::PLAIN)->exists());
        $this->assertTrue(NotificationSubscription::query()->where('user_id', $user->uid)->where('type', 'player_search.opened')->exists());
    }

    #[Test]
    public function an_unregistered_purpose_is_not_found(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('account.whatsapp.enable', ['purpose' => 'nope']), ['consent' => '1'])->assertNotFound();
        $this->actingAs($user)->put(route('account.whatsapp.update', ['purpose' => 'nope']))->assertNotFound();
        $this->actingAs($user)->delete(route('account.whatsapp.revoke', ['purpose' => 'nope']))->assertNotFound();
    }

    #[Test]
    public function active_card_shows_switches_and_filters_only_where_defined(): void
    {
        $user = $this->user();
        $this->consents()->optIn($user, self::PURPOSE, 'de');

        $card = $this->dummyCard($user);

        $this->assertStringContainsString('data-whatsapp-card="active"', $card);
        $this->assertMatchesRegularExpression('/name="on\[dummy-reminder\]"[^>]*checked/', $card);
        $this->assertStringContainsString('name="on[dummy-digest]"', $card);
        $this->assertStringNotContainsString('filters[dummy-reminder]', $card);
        $this->assertStringContainsString('name="filters[dummy-digest][sizes][]"', $card);
        $this->assertStringContainsString('Klein', $card);
        $this->assertSame(1, substr_count($card, '<details'));
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
        $this->actingAs($user)->post(route('account.whatsapp.enable', ['purpose' => self::PURPOSE]), ['consent' => '1'])->assertNotFound();
        $this->assertNull($this->consentOf($user, self::PURPOSE));
    }

    #[Test]
    public function a_purpose_with_consent_but_without_switched_on_types_only_offers_withdrawal(): void
    {
        $user = $this->user();
        $this->consents()->optIn($user, self::PURPOSE, 'de');
        $this->onlyTheFollowupIsOn();

        $card = $this->dummyCard($user);

        $this->assertStringContainsString('data-whatsapp-card="active"', $card);
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
