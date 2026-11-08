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

        // Why an account cannot switch WhatsApp notifications on (gate reasons, shown in the account card and after a refused request).
        // Only the booking account counts (status, mobile number, player exchange); a member record is not required.
        'refused' => [
            'disabled' => 'WhatsApp-Benachrichtigungen sind derzeit nicht verfügbar.',
            'account_inactive' => 'Dein Konto ist nicht aktiv, deshalb kannst du WhatsApp-Benachrichtigungen nicht einschalten.',
            'no_phone' => 'In deinem Profil ist noch keine Handynummer hinterlegt. Trage sie im Profil ein und speichere.',
            'phone_invalid' => 'Die Handynummer in deinem Profil ist ungültig. Bitte korrigiere sie im Profil.',
            'phone_not_mobile' => 'Die Nummer in deinem Profil ist keine Handynummer. Bitte trage im Profil eine Handynummer ein.',
            'exchange_off' => 'Die Spielerbörse ist bei dir ausgeschaltet. Aktiviere zuerst die Spielerbörse im Profil und speichere, dann kannst du WhatsApp einschalten.',
            'conflict' => 'Die Einwilligung konnte gerade nicht gespeichert werden. Bitte versuche es noch einmal.',
        ],

        // Account card "Benachrichtigungen" (consent, filters, withdrawal).
        'account_card' => [
            'title' => 'WhatsApp-Benachrichtigungen',
            'intro' => 'Lass dich per WhatsApp benachrichtigen, wenn jemand über die Spielerbörse Mitspieler sucht.',
            'number_label' => 'Nummer aus deinem Profil: :number',
            'own_number_hint' => 'In der Nachricht stehen der Vor- und Nachname der suchenden Person. Die Nachricht geht an die Handynummer aus deinem Profil, und diese muss deine eigene sein.',
            'profile_link' => 'Zum Profil',
            'privacy_link' => 'Datenschutzerklärung',
            'locked_button' => 'WhatsApp aktivieren',
            'consent_required' => 'Bitte bestätige die Einwilligung, um WhatsApp zu aktivieren.',
            'enable' => 'WhatsApp aktivieren',
            'enabled_flash' => 'WhatsApp-Benachrichtigungen sind aktiviert.',
            'revoke' => 'WhatsApp abmelden',
            'revoked_flash' => 'Du bist von den WhatsApp-Benachrichtigungen abgemeldet.',
            'badge_active' => 'Aktiv',
            'consented_at' => 'Eingewilligt am :date',
            'search_switch' => 'Bei Spielersuchen benachrichtigen',
            'search_help' => 'Ausschalten pausiert nur die Spielersuche-Nachrichten, deine Einwilligung bleibt bestehen. Mit „WhatsApp abmelden“ widerrufst du sie.',
            'paused' => 'Zurzeit werden dir keine Nachrichten geschickt. :reason',
            'filters' => 'Filter',
            'filters_help' => 'Ohne Auswahl wirst du bei jeder Spielersuche benachrichtigt.',
            'weekdays' => [
                1 => 'Mo',
                2 => 'Di',
                3 => 'Mi',
                4 => 'Do',
                5 => 'Fr',
                6 => 'Sa',
                7 => 'So',
            ],
            // Full names for screen readers (aria-label of the short weekday tiles).
            'weekdays_full' => [
                1 => 'Montag',
                2 => 'Dienstag',
                3 => 'Mittwoch',
                4 => 'Donnerstag',
                5 => 'Freitag',
                6 => 'Samstag',
                7 => 'Sonntag',
            ],
            'weekdays_label' => 'Wochentage',
            'time_from' => 'Spielbeginn ab',
            'time_to' => 'Spielbeginn bis',
            'time_help' => 'Uhrzeit von und bis angeben, zum Beispiel 17:00 bis 21:00.',
            'quantities_label' => 'Spielart',
            'quantities' => [
                2 => 'Einzel (2 Spieler)',
                4 => 'Doppel (4 Spieler)',
            ],
            'save' => 'Speichern',
            'saved_flash' => 'Die Einstellungen für WhatsApp sind gespeichert.',
            'no_consent_flash' => 'Bitte aktiviere zuerst WhatsApp-Benachrichtigungen.',
            'notice_stop' => 'Du hast dich am :date per STOP abgemeldet. Du kannst WhatsApp hier jederzeit wieder einschalten.',
            'notice_phone_changed' => 'Du hast deine Handynummer geändert. Bitte schalte WhatsApp für die neue Nummer neu ein.',
        ],

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
            // Variable 1 of the player search templates (ISO format of the date, e.g. "Sa, 14.11.").
            'date_format' => 'dd, DD.MM.',
            'player_search_opened' => 'Neue Spielersuche im Buchungssystem: :date, :time Uhr, :court. Gesucht: :count Mitspieler, gesucht von :name. Mitspielen kannst du hier: :url Abmelden: im Konto unter Benachrichtigungen oder mit STOP antworten.',
            'player_search_closed' => 'Die Spielersuche am :date um :time Uhr auf :court ist abgeschlossen. Danke fürs Interesse.',
        ],

        // Answers to inbound messages (returned in the webhook response, sent as free text inside the 24-hour window).
        'webhook' => [
            'stop_confirmed' => 'Abmeldung bestätigt: Du bekommst keine WhatsApp-Benachrichtigungen mehr vom Buchungssystem. Wieder anmelden kannst du dich jederzeit im Konto unter Benachrichtigungen: :url',
            'start' => 'Eine automatische Anmeldung per Antwort gibt es nicht. Du kannst WhatsApp-Benachrichtigungen im Konto unter Benachrichtigungen einschalten: :url',
            'help' => 'Diese Nummer nimmt keine Nachrichten entgegen. Benachrichtigungen verwaltest du im Konto: :url Mit STOP meldest du dich ab.',
        ],
    ],
];
