<?php

declare(strict_types=1);

return [
    'notifications' => [
        // Version of the WhatsApp consent notice; raise it when the wording changes materially.
        'consent_text_version' => 'wa-2026-11-v1',

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
