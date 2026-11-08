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
