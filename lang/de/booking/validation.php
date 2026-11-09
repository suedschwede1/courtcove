<?php

declare(strict_types=1);

return [
    'validation' => [
        'event_booking_conflicts' => 'Die Sperre überschneidet sich mit :count Buchung(en). Bitte diese zuerst verschieben oder stornieren und danach die Sperre erneut speichern.',
        'occurrence_missing' => 'Dieser Serientermin ist nicht mehr vorhanden. Bitte Kalender neu laden.',
        'end_time_after_start' => 'Die Endzeit muss nach der Startzeit liegen.',
        'repeat_end_date_after_start' => 'Das Enddatum der Wiederholung muss am oder nach dem Startdatum liegen.',
        'no_valid_repeat' => 'Es konnte keine gültige Wiederholung erzeugt werden.',
        'custom_repeat_not_editable' => 'Bei unregelmäßigen Serien müssen Datum und Uhrzeit unverändert bleiben. Verschiebe Datum oder Uhrzeit über einen einzelnen Termin.',
        'current_password_incorrect' => 'Das aktuelle Passwort ist nicht korrekt.',
    ],
];
