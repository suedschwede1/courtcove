<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Models\User;
use App\Notifications\Registry\NotificationPurposeDefinition;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Registry\NotificationTypeDefinition;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\Resolvers\AdminTestResolver;
use App\Services\Notifications\Resolvers\PlayerSearchClosedSubjectCheck;
use App\Services\Notifications\Resolvers\PlayerSearchSubjectCheck;
use App\Services\Notifications\SubjectDeadline;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The central registry of notification types and consent purposes, with the built-in Spielerbörse types. */
class NotificationRegistryTest extends TestCase
{
    private function registry(): NotificationRegistry
    {
        return app(NotificationRegistry::class);
    }

    #[Test]
    public function registry_is_a_singleton(): void
    {
        $this->assertSame($this->registry(), app(NotificationRegistry::class));
    }

    #[Test]
    public function built_in_types_keep_their_stored_keys_and_settings(): void
    {
        $this->assertSame(
            [NotificationTypes::ADMIN_TEST, NotificationTypes::PLAYER_SEARCH_OPENED, NotificationTypes::PLAYER_SEARCH_CLOSED],
            array_keys($this->registry()->types()),
        );
        $this->assertSame('player_search.opened', NotificationTypes::PLAYER_SEARCH_OPENED);
        $this->assertSame('player_search.closed', NotificationTypes::PLAYER_SEARCH_CLOSED);
        $this->assertSame('whatsapp.test', NotificationTypes::ADMIN_TEST);

        $opened = $this->registry()->type(NotificationTypes::PLAYER_SEARCH_OPENED);
        $this->assertNotNull($opened);
        $this->assertSame('player_search_opened', $opened->templateKey);
        $this->assertSame(20, $opened->priority);
        $this->assertSame(NotificationConsent::PURPOSE_PLAYER_SEARCH, $opened->purpose);
        $this->assertSame(PlayerSearchSubjectCheck::class, $opened->resolver);
        $this->assertTrue($opened->defaultEnabled);
        $this->assertTrue($opened->optIn);
        $this->assertFalse($opened->isFollowup());
        $this->assertFalse($opened->testType);
        $this->assertSame(['weekdays', 'time_window', 'quantities'], array_keys($opened->filters));

        $closed = $this->registry()->type(NotificationTypes::PLAYER_SEARCH_CLOSED);
        $this->assertNotNull($closed);
        $this->assertSame('player_search_closed', $closed->templateKey);
        $this->assertSame(30, $closed->priority);
        $this->assertSame(PlayerSearchClosedSubjectCheck::class, $closed->resolver);
        $this->assertFalse($closed->defaultEnabled);
        $this->assertFalse($closed->optIn);
        $this->assertTrue($closed->isFollowup());
        $this->assertSame(NotificationTypes::PLAYER_SEARCH_OPENED, $closed->followupOf);
        $this->assertSame('whatsapp.followup_closed', $closed->legacyEnabledOption);

        $test = $this->registry()->type(NotificationTypes::ADMIN_TEST);
        $this->assertNotNull($test);
        $this->assertSame('test', $test->templateKey);
        $this->assertSame(10, $test->priority);
        $this->assertTrue($test->testType);
        $this->assertFalse($test->optIn);
        $this->assertSame(AdminTestResolver::class, $test->resolver);

        $this->assertNull($this->registry()->type('unknown.type'));
        $this->assertFalse($this->registry()->has('unknown.type'));
    }

    #[Test]
    public function player_search_purpose_keeps_text_and_version(): void
    {
        $purpose = $this->registry()->purpose(NotificationConsent::PURPOSE_PLAYER_SEARCH);
        $this->assertNotNull($purpose);
        $this->assertSame('booking.notifications.consent_text_v1', $purpose->consentTextKey);
        $this->assertSame('booking.notifications.consent_text_version', $purpose->consentVersionKey);
        $this->assertSame('wa-2026-11-v1', $purpose->consentVersion());
        $this->assertSame(
            [NotificationTypes::ADMIN_TEST, NotificationTypes::PLAYER_SEARCH_OPENED, NotificationTypes::PLAYER_SEARCH_CLOSED],
            $this->registry()->typesOfPurpose(NotificationConsent::PURPOSE_PLAYER_SEARCH),
        );
        $this->assertSame([NotificationTypes::PLAYER_SEARCH_OPENED], $this->registry()->optInTypesOfPurpose(NotificationConsent::PURPOSE_PLAYER_SEARCH));
        $this->assertSame([], $this->registry()->typesOfPurpose('nope'));
    }

    #[Test]
    public function player_search_purpose_requires_the_player_exchange(): void
    {
        $purpose = $this->registry()->purpose(NotificationConsent::PURPOSE_PLAYER_SEARCH);
        $this->assertNotNull($purpose);
        $user = new User;
        $user->setRelation('meta', collect());

        $this->assertSame('exchange_off', $purpose->gateFailure($user));
    }

    #[Test]
    public function labels_and_descriptions_exist_in_both_languages(): void
    {
        foreach (['de', 'en'] as $locale) {
            foreach ($this->registry()->types() as $type) {
                foreach ([$type->labelKey, $type->descriptionKey] as $key) {
                    $this->assertTrue(trans()->has($key, $locale, false), "$key ($locale)");
                }
            }
            foreach ($this->registry()->purposes() as $purpose) {
                foreach ([$purpose->labelKey, $purpose->descriptionKey, $purpose->consentTextKey, $purpose->consentVersionKey] as $key) {
                    $this->assertTrue(trans()->has($key, $locale, false), "$key ($locale)");
                }
            }
        }
    }

    #[Test]
    public function resolvers_are_resolved_from_the_definition_and_can_be_swapped(): void
    {
        $this->assertInstanceOf(PlayerSearchSubjectCheck::class, $this->registry()->resolver(NotificationTypes::PLAYER_SEARCH_OPENED));
        $this->assertInstanceOf(SubjectDeadline::class, $this->registry()->resolver(NotificationTypes::PLAYER_SEARCH_OPENED));
        $this->assertInstanceOf(PlayerSearchClosedSubjectCheck::class, $this->registry()->resolver(NotificationTypes::PLAYER_SEARCH_CLOSED));
        $this->assertNull($this->registry()->resolver('unknown.type'));

        $swap = new AdminTestResolver;
        $this->registry()->useResolver(NotificationTypes::PLAYER_SEARCH_OPENED, $swap);
        $this->assertSame($swap, $this->registry()->resolver(NotificationTypes::PLAYER_SEARCH_OPENED));
    }

    #[Test]
    public function a_type_needs_a_registered_purpose(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry()->register($this->type('reg.orphan', 'missing_purpose'));
    }

    #[Test]
    public function the_passed_purpose_must_match_the_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry()->register($this->type('reg.mismatch', 'one'), $this->purpose('two'));
    }

    #[Test]
    public function type_and_purpose_are_registered_together(): void
    {
        $this->registry()->register($this->type('reg.one', 'reg_purpose'), $this->purpose('reg_purpose'));
        $this->registry()->register($this->type('reg.two', 'reg_purpose', optIn: false));

        $this->assertSame(['reg.one', 'reg.two'], $this->registry()->typesOfPurpose('reg_purpose'));
        $this->assertSame(['reg.one'], $this->registry()->optInTypesOfPurpose('reg_purpose'));
        $this->assertNull($this->registry()->purpose('reg_purpose')?->gateFailure(new User));
    }

    #[Test]
    public function keys_are_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->type('Bad Key!', 'reg_purpose');
    }

    #[Test]
    public function filter_definitions_are_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NotificationTypeDefinition(
            key: 'reg.filtered',
            labelKey: 'booking.notifications.types.player_search_opened.label',
            descriptionKey: 'booking.notifications.types.player_search_opened.description',
            templateKey: 'reg_filtered',
            resolver: AdminTestResolver::class,
            purpose: null,
            filters: ['window' => ['kind' => 'time_range', 'from' => 'start']], // "to" missing
        );
    }

    #[Test]
    public function built_in_types_name_their_template_variables_in_both_languages(): void
    {
        $opened = $this->registry()->type(NotificationTypes::PLAYER_SEARCH_OPENED);
        $this->assertNotNull($opened?->variablesKey);
        foreach (['de', 'en'] as $locale) {
            $this->assertCount(6, (array) trans((string) $opened->variablesKey, [], $locale));
            $this->assertCount(3, (array) trans((string) $this->registry()->type(NotificationTypes::PLAYER_SEARCH_CLOSED)?->variablesKey, [], $locale));
        }
        // The admin test has no variables (the admin page shows "none").
        $this->assertNull($this->registry()->type(NotificationTypes::ADMIN_TEST)?->variablesKey);
    }

    #[Test]
    public function a_followup_names_a_registered_type(): void
    {
        $this->registry()->register($this->type('reg.base', 'reg_purpose'), $this->purpose('reg_purpose'));
        $this->registry()->register($this->type('reg.follow', 'reg_purpose', followupOf: 'reg.base'));
        $this->assertTrue($this->registry()->type('reg.follow')?->isFollowup());

        $this->expectException(InvalidArgumentException::class);
        $this->registry()->register($this->type('reg.follow2', 'reg_purpose', followupOf: 'reg.unknown'));
    }

    private function type(string $key, string $purpose, bool $optIn = true, ?string $followupOf = null): NotificationTypeDefinition
    {
        return new NotificationTypeDefinition(
            key: $key,
            labelKey: 'booking.notifications.types.player_search_opened.label',
            descriptionKey: 'booking.notifications.types.player_search_opened.description',
            templateKey: str_replace('.', '_', $key),
            resolver: AdminTestResolver::class,
            purpose: $purpose,
            optIn: $optIn,
            followupOf: $followupOf,
        );
    }

    private function purpose(string $key): NotificationPurposeDefinition
    {
        return new NotificationPurposeDefinition(
            key: $key,
            labelKey: 'booking.notifications.purposes.player_search.label',
            descriptionKey: 'booking.notifications.purposes.player_search.description',
            consentTextKey: 'booking.notifications.consent_text_v1',
            consentVersionKey: 'booking.notifications.consent_text_version',
        );
    }
}
