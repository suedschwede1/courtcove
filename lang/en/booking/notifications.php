<?php

declare(strict_types=1);

return [
    'notifications' => [
        // Version of the WhatsApp consent notice; raise it when the wording changes materially.
        'consent_text_version' => 'wa-2026-11-v1',

        // Consent notice shown next to the opt-in (account card, Task 11). Mentions: purpose, number from the account,
        // own number only, withdrawal at any time (account or STOP), and that the searching person's name is in the message.
        'consent_text_v1' => 'I want to be notified by WhatsApp when someone is looking for players (player search). The message goes to the mobile number stored in my account; I only enter my own number. The message contains the name of the person looking for players. I can withdraw my consent at any time in my account or by replying STOP to a message.',

        // Names and descriptions of the notification types (NotificationRegistry; keyed by template key).
        'types' => [
            'player_search_opened' => [
                'label' => 'New player search',
                'description' => 'A message when someone looks for players in the player exchange.',
                'variables' => [
                    1 => 'Date (for example "Sat, 14.11.")',
                    2 => 'Start time',
                    3 => 'Court',
                    4 => 'Places searched',
                    5 => 'First and last name of the searching person',
                    6 => 'Ending of the button link (calendar with date)',
                ],
            ],
            'player_search_closed' => [
                'label' => 'Search closed',
                'description' => 'Follow-up when a search you were told about has found its players or ended.',
                'variables' => [
                    1 => 'Date',
                    2 => 'Start time',
                    3 => 'Court',
                ],
            ],
            'test' => [
                'label' => 'Test message',
                'description' => 'Test message of the admin page to the admin\'s own number.',
            ],
        ],

        // Consent purposes (NotificationRegistry): one consent per purpose, with its own text and version.
        'purposes' => [
            'player_search' => [
                'label' => 'Player exchange',
                // Row text in the account card; purpose specific hints (what the message contains) belong here.
                'description' => 'A message when someone looks for players in the player exchange. It contains the first and last name of the person looking.',
            ],
        ],

        // Privacy sentence, once under the list of the account card for every purpose; processors are named on the club's
        // external privacy page, not here.
        'privacy_notice' => 'With your consent we use the mobile number from your account to send you the notifications you choose by WhatsApp. The messaging service we engage and the operator of WhatsApp are involved. You can unsubscribe at any time here or by replying STOP.',

        // Why an account cannot switch WhatsApp notifications on (gate reasons, shown in the account card and after a refused request).
        // Only the booking account counts (status, mobile number, player exchange); a member record is not required.
        'refused' => [
            'disabled' => 'WhatsApp notifications are not available at the moment.',
            'account_inactive' => 'Your account is not active, so you cannot switch on WhatsApp notifications.',
            'no_phone' => 'There is no mobile number in your profile yet. Enter it in your profile and save.',
            'phone_invalid' => 'The mobile number in your profile is not valid. Please correct it in your profile.',
            'phone_not_mobile' => 'The number in your profile is not a mobile number. Please enter a mobile number in your profile.',
            'exchange_off' => 'The player exchange is switched off for you. First switch on the player exchange in your profile and save, then you can switch on WhatsApp.',
            'conflict' => 'Your consent could not be saved just now. Please try again.',
            // Any other reason (the gate condition of a further purpose without a text of its own).
            'unavailable' => 'You do not meet the requirements for these notifications at the moment.',
        ],

        // Account card "WhatsApp notifications": number section, one row per purpose (switch, consent, filters), common note.
        'account_card' => [
            'title' => 'WhatsApp notifications',
            'intro' => 'Get notified on WhatsApp. Choose what for.',
            'number_heading' => 'Your number',
            'number_label' => 'Number from your profile: :number.',
            'own_number_hint' => 'It must be your own number.',
            'profile_link' => 'Change in your profile',
            'list_heading' => 'Notifications',
            'switch_on' => 'On',
            'switch_off' => 'Off',
            'privacy_link' => 'Privacy policy',
            'confirm' => 'Confirm',
            'cancel' => 'Cancel',
            'consent_required' => 'Please confirm your consent to switch this notification on.',
            'enabled_flash' => 'The notification is switched on.',
            'revoke_confirm' => 'If you switch off ":purpose", you withdraw your consent for it. You will no longer get these messages and can switch them on again here at any time.',
            'revoke' => 'Switch off',
            'revoked_flash' => 'The notification is switched off and your consent is withdrawn.',
            'consented_at' => 'Consent given on :date',
            'paused' => 'None of these messages are being sent to you at the moment. :reason',
            'paused_all' => 'Until then no messages are sent to you.',
            'settings' => 'Messages and filters',
            'filters' => 'Filters',
            'filters_help' => 'Without a selection you are notified about every one of these messages.',
            'weekdays' => [
                1 => 'Mon',
                2 => 'Tue',
                3 => 'Wed',
                4 => 'Thu',
                5 => 'Fri',
                6 => 'Sat',
                7 => 'Sun',
            ],
            // Full names for screen readers (aria-label of the short weekday tiles).
            'weekdays_full' => [
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
                7 => 'Sunday',
            ],
            'weekdays_label' => 'Weekdays',
            'time_from' => 'Start time from',
            'time_to' => 'Start time to',
            'time_help' => 'Enter a time from and to, for example 17:00 to 21:00.',
            'quantities_label' => 'Type of game',
            'quantities' => [
                2 => 'Singles (2 players)',
                4 => 'Doubles (4 players)',
            ],
            'save' => 'Save',
            'saved_flash' => 'Your WhatsApp settings are saved.',
            'no_consent_flash' => 'Please switch the notification on first.',
            'notice_stop' => 'You unsubscribed with STOP on :date.',
            // Added to notice_stop only while account and number allow switching on again.
            'notice_stop_again' => 'You can switch the notifications on again here at any time.',
            // A row that is on but has no message type on (its types are not switched on with the consent).
            'choose_types' => 'Choose which messages you want to get, then save.',
            'notice_phone_changed' => 'Your mobile number has changed. Switch the notifications on again for the new number.',
        ],

        'budget_alert' => [
            'subject_80' => 'WhatsApp budget 80% used',
            'subject_100' => 'WhatsApp budget used up, sending stopped',
            'heading_80' => 'WhatsApp budget almost used up',
            'heading_100' => 'WhatsApp budget used up',
            'body_80' => 'The monthly budget for WhatsApp notifications is :percent% used.',
            'body_100' => 'The monthly budget for WhatsApp notifications is used up. No more messages will be sent until the end of the month.',
            'hint_80' => 'You can raise the monthly budget in the WhatsApp notification settings.',
            'hint_100' => 'You can raise the monthly budget in the WhatsApp notification settings, then sending resumes.',
            'month' => 'Month',
            'messages' => 'Messages sent',
            'spent' => 'Estimated cost',
            'budget' => 'Monthly budget',
        ],

        // Message texts; in sandbox mode they are sent as free text, otherwise the approved template is used.
        'whatsapp' => [
            'test' => 'Test message from the booking system: WhatsApp sending works.',
            // Variable 1 of the player search templates (ISO format of the date, e.g. "Sat, 14.11.").
            'date_format' => 'ddd, DD.MM.',
            'player_search_opened' => 'New player search in the booking system: :date, :time, :court. Players needed: :count, searched by :name. You can join here: :url To unsubscribe, use Notifications in your account or reply STOP.',
            'player_search_closed' => 'The player search on :date at :time on :court is closed. Thanks for your interest.',
        ],

        // Answers to inbound messages (returned in the webhook response, sent as free text inside the 24-hour window).
        'webhook' => [
            'stop_confirmed' => 'Unsubscribed: you will no longer get WhatsApp notifications from the booking system. You can subscribe again at any time in your account under Notifications: :url',
            'start' => 'There is no automatic subscription by reply. You can switch WhatsApp notifications on in your account under Notifications: :url',
            'help' => 'This number does not read messages. Manage your notifications in your account: :url Reply STOP to unsubscribe.',
        ],
    ],
];
