<?php

declare(strict_types=1);

return [
    'notifications' => [
        // Version of consent_text_v1 as the visible consent wording (consents given until 2026-11-09 carry it and stay
        // valid). New player search consents use purposes.player_search.consent_version.
        'consent_text_version' => 'wa-2026-11-v1',

        // Full consent text; since v2 shown behind "Mehr erfahren" in the account card (was the opt-in text of v1). Mentions: purpose, number from the account,
        // own number only, withdrawal at any time (account or STOP), and that the searching person's name is in the message.
        'consent_text_v1' => 'Ich möchte per WhatsApp benachrichtigt werden, wenn jemand Mitspieler sucht (Spielersuche). Die Nachricht geht an die Handynummer, die in meinem Konto hinterlegt ist; ich gebe nur meine eigene Nummer an. In der Nachricht steht der Name der Person, die Mitspieler sucht. Meine Einwilligung kann ich jederzeit im Konto widerrufen oder indem ich auf eine Nachricht mit STOP antworte.',

        // Names and descriptions of the notification types (NotificationRegistry; keyed by template key).
        'types' => [
            'player_search_opened' => [
                'label' => 'Neue Spielersuche',
                'description' => 'Nachricht, wenn jemand über die Spielerbörse Mitspieler sucht.',
                'variables' => [
                    1 => 'Datum (zum Beispiel „Sa, 14.11.“)',
                    2 => 'Beginn',
                    3 => 'Platz',
                    4 => 'gesuchte Plätze',
                    5 => 'Vor- und Nachname der suchenden Person',
                    6 => 'Endung des Button-Links (Kalender mit Datum)',
                ],
            ],
            'player_search_closed' => [
                'label' => 'Suche beendet',
                'description' => 'Folgenachricht, wenn eine gemeldete Suche voll ist oder beendet wurde.',
                'variables' => [
                    1 => 'Datum',
                    2 => 'Beginn',
                    3 => 'Platz',
                ],
            ],
            'test' => [
                'label' => 'Testnachricht',
                'description' => 'Testnachricht der Admin-Seite an die eigene Nummer des Administrators.',
            ],
        ],

        // Consent purposes (NotificationRegistry): one consent per purpose, with its own text and version.
        'purposes' => [
            'player_search' => [
                'label' => 'Spielerbörse',
                // Row text in the account card.
                'description' => 'Nachricht, wenn jemand über die Spielerbörse Mitspieler sucht.',
                // Consent sentence next to the switch (the switch is the consent); purpose specific hints belong here.
                // To be approved by the club and its data protection contact.
                'consent_short' => 'Mit dem Einschalten stimmst du zu, dass wir dir per WhatsApp an die Handynummer aus deinem Profil schreiben, wenn jemand Mitspieler sucht. In der Nachricht stehen Vor- und Nachname der suchenden Person; Ausschalten oder eine Antwort mit STOP widerruft deine Zustimmung.',
                // Version of consent_short, stored with every new consent (v1 = the earlier opt-in with consent_text_v1).
                'consent_version' => 'wa-2026-11-v2',
            ],
        ],

        // Privacy sentence, once under the list of the account card for every purpose; processors are named on the club's
        // external privacy page, not here.
        'privacy_notice' => 'Mit deiner Einwilligung verwenden wir die Handynummer aus deinem Konto, um dir die gewählten Benachrichtigungen per WhatsApp zu senden. Dabei sind der von uns beauftragte Messaging-Dienst und der Betreiber von WhatsApp eingebunden. Abmelden kannst du dich jederzeit hier oder per Antwort STOP.',

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
            // Any other reason (the gate condition of a further purpose without a text of its own).
            'unavailable' => 'Die Voraussetzungen für diese Benachrichtigungen sind bei dir derzeit nicht erfüllt.',
        ],

        // Account card "WhatsApp-Benachrichtigungen": number section, one row per purpose (switch, consent, filters), common note.
        'account_card' => [
            'title' => 'WhatsApp-Benachrichtigungen',
            'intro' => 'Lass dich per WhatsApp benachrichtigen. Wähle, wofür.',
            'number_heading' => 'Deine Nummer',
            'number_label' => 'Nummer aus deinem Profil: :number.',
            'own_number_hint' => 'Die Nummer muss deine eigene sein.',
            'profile_link' => 'Im Profil ändern',
            'list_heading' => 'Benachrichtigungen',
            'switch_on' => 'An',
            'switch_off' => 'Aus',
            'privacy_link' => 'Datenschutzerklärung',
            'consent_more' => 'Mehr erfahren',
            'consent_required' => 'Bitte schalte die Benachrichtigung mit dem Schalter ein.',
            'enabled_flash' => 'Die Benachrichtigung ist eingeschaltet.',
            'revoked_flash' => 'Die Benachrichtigung ist ausgeschaltet, deine Einwilligung ist widerrufen.',
            'consented_at' => 'Eingewilligt am :date',
            'paused' => 'Zurzeit werden dir keine dieser Nachrichten geschickt. :reason',
            'paused_all' => 'Bis dahin werden dir keine Nachrichten geschickt.',
            'settings' => 'Nachrichten und Filter',
            'filters' => 'Filter',
            'filters_help' => 'Ohne Auswahl wirst du bei jeder dieser Nachrichten benachrichtigt.',
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
            'no_consent_flash' => 'Bitte schalte die Benachrichtigung zuerst ein.',
            'notice_stop' => 'Du hast dich am :date per STOP abgemeldet.',
            // Added to notice_stop only while account and number allow switching on again.
            'notice_stop_again' => 'Du kannst die Benachrichtigungen hier jederzeit wieder einschalten.',
            // A row that is on but has no message type on (its types are not switched on with the consent).
            'choose_types' => 'Wähle, welche Nachrichten du bekommen möchtest, und speichere.',
            'notice_phone_changed' => 'Deine Handynummer hat sich geändert. Schalte die Benachrichtigungen für die neue Nummer wieder ein.',
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
