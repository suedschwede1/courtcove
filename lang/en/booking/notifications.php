<?php

declare(strict_types=1);

return [
    'notifications' => [
        // Version of the WhatsApp consent notice; raise it when the wording changes materially.
        'consent_text_version' => 'wa-2026-11-v1',

        // Consent notice shown next to the opt-in (account card, Task 11). Mentions: purpose, number from the account,
        // own number only, withdrawal at any time (account or STOP), and that the searching person's name is in the message.
        'consent_text_v1' => 'I want to be notified by WhatsApp when someone is looking for players (player search). The message goes to the mobile number stored in my account; I only enter my own number. The message contains the name of the person looking for players. I can withdraw my consent at any time in my account or by replying STOP to a message.',

        // Privacy sentence; processors are named on the club's external privacy page, not here.
        'privacy_notice' => 'With your consent we use the mobile number from your account to send you notifications (first: player searches) by WhatsApp. The messaging service we engage and the operator of WhatsApp are involved. You can withdraw your consent at any time in your account or by replying STOP.',

        // Why a member cannot switch WhatsApp notifications on (gate reasons, shown in the account card and after a refused request).
        'refused' => [
            'disabled' => 'WhatsApp notifications are not available at the moment.',
            'account_inactive' => 'Your account is not active, so you cannot switch on WhatsApp notifications.',
            'no_member' => 'Your account is not linked to a member. Please contact the club.',
            'member_inactive' => 'Your membership is not active, so you cannot switch on WhatsApp notifications.',
            'no_phone' => 'There is no mobile number in your profile yet. Enter it in your profile and save.',
            'phone_invalid' => 'The mobile number in your profile is not valid. Please correct it in your profile.',
            'phone_not_mobile' => 'The number in your profile is not a mobile number. Please enter a mobile number in your profile.',
            'exchange_off' => 'The player exchange is switched off for you. First switch on the player exchange in your profile and save, then you can switch on WhatsApp.',
            'conflict' => 'Your consent could not be saved just now. Please try again.',
        ],

        // Account card "Benachrichtigungen" (consent, filters, withdrawal).
        'account_card' => [
            'title' => 'WhatsApp notifications',
            'intro' => 'Get a WhatsApp message when someone looks for players in the player exchange.',
            'number_label' => 'Number from your profile: :number',
            'own_number_hint' => 'The message contains the first and last name of the person looking for players. It goes to the mobile number in your profile, and that number must be your own.',
            'profile_link' => 'Go to profile',
            'privacy_link' => 'Privacy policy',
            'locked_button' => 'Activate WhatsApp',
            'consent_required' => 'Please confirm your consent to activate WhatsApp.',
            'enable' => 'Activate WhatsApp',
            'enabled_flash' => 'WhatsApp notifications are activated.',
            'revoke' => 'Unsubscribe from WhatsApp',
            'revoked_flash' => 'You are unsubscribed from WhatsApp notifications.',
            'badge_active' => 'Active',
            'consented_at' => 'Consent given on :date',
            'search_switch' => 'Notify me about player searches',
            'search_help' => 'Switching this off only pauses the player search messages, your consent stays. "Unsubscribe from WhatsApp" withdraws it.',
            'paused' => 'No messages are being sent to you at the moment. :reason',
            'filters' => 'Filters',
            'filters_help' => 'Without a selection you are notified about every player search.',
            'weekdays' => [
                1 => 'Mon',
                2 => 'Tue',
                3 => 'Wed',
                4 => 'Thu',
                5 => 'Fri',
                6 => 'Sat',
                7 => 'Sun',
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
            'no_consent_flash' => 'Please activate WhatsApp notifications first.',
            'notice_stop' => 'You unsubscribed with STOP on :date. You can switch WhatsApp on again here at any time.',
            'notice_phone_changed' => 'You changed your mobile number. Please switch WhatsApp on again for the new number.',
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
        ],

        // Answers to inbound messages (returned in the webhook response, sent as free text inside the 24-hour window).
        'webhook' => [
            'stop_confirmed' => 'Unsubscribed: you will no longer get WhatsApp notifications from the booking system. You can subscribe again at any time in your account under Notifications: :url',
            'start' => 'There is no automatic subscription by reply. You can switch WhatsApp notifications on in your account under Notifications: :url',
            'help' => 'This number does not read messages. Manage your notifications in your account: :url Reply STOP to unsubscribe.',
        ],
    ],
];
