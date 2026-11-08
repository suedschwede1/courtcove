<?php

declare(strict_types=1);

return [
    'notifications' => [
        // Version of the WhatsApp consent notice; raise it when the wording changes materially.
        'consent_text_version' => 'wa-2026-11-v1',

        // Consent notice shown next to the opt-in (account card, Task 11). Mentions: purpose, number from the account,
        // own number only, withdrawal at any time (account or STOP), and that the searching person's name is in the message.
        'consent_text_v1' => 'Ich möchte per WhatsApp benachrichtigt werden, wenn jemand Mitspieler sucht (Spielersuche). Die Nachricht geht an die Handynummer, die in meinem Konto hinterlegt ist; ich gebe nur meine eigene Nummer an. In der Nachricht steht der Name der Person, die Mitspieler sucht. Meine Einwilligung kann ich jederzeit im Konto widerrufen oder indem ich auf eine Nachricht mit STOP antworte.',

        // Privacy sentence; processors are named on the club's external privacy page, not here.
        'privacy_notice' => 'Mit deiner Einwilligung verwenden wir die Handynummer aus deinem Konto, um dir Benachrichtigungen (zuerst: Spielersuchen) per WhatsApp zu senden. Dabei sind der von uns beauftragte Messaging-Dienst und der Betreiber von WhatsApp eingebunden. Du kannst die Einwilligung jederzeit im Konto oder per STOP widerrufen.',

        'budget_alert' => [
            'subject_80' => 'WhatsApp-Budget zu 80 % verbraucht',
            'subject_100' => 'WhatsApp-Budget aufgebraucht, Versand gestoppt',
            'heading_80' => 'WhatsApp-Budget fast verbraucht',
            'heading_100' => 'WhatsApp-Budget aufgebraucht',
            'body_80' => 'Das Monatsbudget für WhatsApp-Benachrichtigungen ist zu :percent % verbraucht.',
            'body_100' => 'Das Monatsbudget für WhatsApp-Benachrichtigungen ist aufgebraucht. Es werden bis zum Monatsende keine weiteren Nachrichten gesendet.',
            'hint_80' => 'Sie können das Monatsbudget in den Einstellungen für WhatsApp-Benachrichtigungen erhöhen.',
            'hint_100' => 'Sie können das Monatsbudget in den Einstellungen für WhatsApp-Benachrichtigungen erhöhen, dann wird der Versand fortgesetzt.',
            'month' => 'Monat',
            'messages' => 'Gesendete Nachrichten',
            'spent' => 'Geschätzte Kosten',
            'budget' => 'Monatsbudget',
        ],

        // Message texts; in sandbox mode they are sent as free text, otherwise the approved template is used.
        'whatsapp' => [
            'test' => 'Testnachricht aus dem Buchungssystem: der WhatsApp-Versand funktioniert.',
        ],

        // Answers to inbound messages (returned in the webhook response, sent as free text inside the 24-hour window).
        'webhook' => [
            'stop_confirmed' => 'Abmeldung bestätigt: Du bekommst keine WhatsApp-Benachrichtigungen mehr vom Buchungssystem. Wieder anmelden kannst du dich jederzeit im Konto unter Benachrichtigungen: :url',
            'start' => 'Eine automatische Anmeldung per Antwort gibt es nicht. Du kannst WhatsApp-Benachrichtigungen im Konto unter Benachrichtigungen einschalten: :url',
            'help' => 'Diese Nummer nimmt keine Nachrichten entgegen. Benachrichtigungen verwaltest du im Konto: :url Mit STOP meldest du dich ab.',
        ],
    ],
];
