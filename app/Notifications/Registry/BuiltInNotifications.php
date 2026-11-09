<?php

declare(strict_types=1);

namespace App\Notifications\Registry;

use App\Models\NotificationConsent;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\Conditions\PlayerExchangeRequired;
use App\Services\Notifications\Resolvers\AdminTestResolver;
use App\Services\Notifications\Resolvers\PlayerSearchClosedSubjectCheck;
use App\Services\Notifications\Resolvers\PlayerSearchSubjectCheck;

/**
 * The notification types the app ships with: the player search messages and the admin test. New types
 * are added here (or from a module's service provider) with one register() call each.
 */
final class BuiltInNotifications
{
    public static function register(NotificationRegistry $registry): void
    {
        $registry->registerPurpose(new NotificationPurposeDefinition(
            key: NotificationConsent::PURPOSE_PLAYER_SEARCH,
            labelKey: 'booking.notifications.purposes.player_search.label',
            descriptionKey: 'booking.notifications.purposes.player_search.description',
            // The switch is the consent (short sentence, version v2 since 2026-11-09); the full v1 text stays behind
            // "Mehr erfahren". Consents given under v1 keep their version and stay valid.
            consentTextKey: 'booking.notifications.consent_text_v1',
            consentVersionKey: 'booking.notifications.purposes.player_search.consent_version',
            gateCondition: PlayerExchangeRequired::class,
            consentShortKey: 'booking.notifications.purposes.player_search.consent_short',
        ));

        // The admin test needs the admin's own player search consent (see the admin page).
        $registry->register(new NotificationTypeDefinition(
            key: NotificationTypes::ADMIN_TEST,
            labelKey: 'booking.notifications.types.test.label',
            descriptionKey: 'booking.notifications.types.test.description',
            templateKey: 'test',
            resolver: AdminTestResolver::class,
            purpose: NotificationConsent::PURPOSE_PLAYER_SEARCH,
            priority: 10,
            testType: true,
        ));

        $registry->register(new NotificationTypeDefinition(
            key: NotificationTypes::PLAYER_SEARCH_OPENED,
            labelKey: 'booking.notifications.types.player_search_opened.label',
            descriptionKey: 'booking.notifications.types.player_search_opened.description',
            templateKey: 'player_search_opened',
            resolver: PlayerSearchSubjectCheck::class,
            purpose: NotificationConsent::PURPOSE_PLAYER_SEARCH,
            priority: 20,
            filters: [
                'weekdays' => ['kind' => 'weekdays'],
                'time_window' => ['kind' => 'time_range', 'from' => 'time_from', 'to' => 'time_to'],
                'quantities' => [
                    'kind' => 'choice',
                    'options' => [2, 4],
                    'labelKey' => 'booking.notifications.account_card.quantities_label',
                    'optionsKey' => 'booking.notifications.account_card.quantities',
                ],
            ],
            optIn: true,
            variablesKey: 'booking.notifications.types.player_search_opened.variables',
        ));

        // Follow-up "search closed": off unless switched on (old option whatsapp.followup_closed still counts).
        $registry->register(new NotificationTypeDefinition(
            key: NotificationTypes::PLAYER_SEARCH_CLOSED,
            labelKey: 'booking.notifications.types.player_search_closed.label',
            descriptionKey: 'booking.notifications.types.player_search_closed.description',
            templateKey: 'player_search_closed',
            resolver: PlayerSearchClosedSubjectCheck::class,
            purpose: NotificationConsent::PURPOSE_PLAYER_SEARCH,
            priority: 30,
            defaultEnabled: false,
            followupOf: NotificationTypes::PLAYER_SEARCH_OPENED,
            legacyEnabledOption: 'whatsapp.followup_closed',
            variablesKey: 'booking.notifications.types.player_search_closed.variables',
        ));
    }
}
