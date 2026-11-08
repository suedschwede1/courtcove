<?php

declare(strict_types=1);

return [
    'notifications' => [
        // Version of the WhatsApp consent notice; raise it when the wording changes materially.
        'consent_text_version' => 'wa-2026-11-v1',

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
    ],
];
